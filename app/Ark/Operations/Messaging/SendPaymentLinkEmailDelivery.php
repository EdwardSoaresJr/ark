<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Mail\OutboundTransactionalMail;
use App\Ark\Mail\TransactionalMailException;
use App\Ark\Mail\TransactionalMailOperation;
use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;
use App\Mail\InvoicePaymentCustomerMail;
use App\Models\User;
use Illuminate\Support\Str;

final class SendPaymentLinkEmailDelivery
{
    public function __construct(
        private readonly PaymentPortalLinkContext $paymentLink,
        private readonly ConversationRecorder $conversations,
        private readonly CommunicationEventRecorder $communicationEvents,
        private readonly OutboundTransactionalMail $outboundMail,
    ) {}

    public function send(RepairOrder $repairOrder, User $actor, string $recipientEmail): ConversationMessage
    {
        $context = $this->paymentLink->forRepairOrder($repairOrder);
        $settings = ShopSettings::current();
        $shopName = $settings->shop_name ?: config('app.name', 'ARK-SMS');

        $mailResult = $this->outboundMail->sendMailable(
            TransactionalMailOperation::PaymentLinkSend,
            $recipientEmail,
            new InvoicePaymentCustomerMail(
                repairOrder: $repairOrder,
                shopName: $shopName,
                portalUrl: $context['url'],
                balanceDueDisplay: $context['balance_due_display'],
            ),
            'payment-link-'.$repairOrder->repair_order_id.'-'.Str::uuid(),
            'repair_order',
            (string) $repairOrder->repair_order_id,
        );

        if (! $mailResult->ok()) {
            throw new TransactionalMailException($mailResult);
        }

        $summary = 'Payment link emailed to '.$recipientEmail.'. Balance due '.$context['balance_due_display'].'.';

        $message = $this->conversations->recordRepairOrderEmail(
            $repairOrder,
            $actor,
            $recipientEmail,
            $summary,
        );

        $this->communicationEvents->record(
            $repairOrder,
            OperationalCommunicationType::InvoiceSent,
            OperationalCommunicationChannel::Email,
            OperationalCommunicationDirection::Outbound,
            $summary,
            actor: $actor,
            message: $message,
        );

        return $message;
    }
}
