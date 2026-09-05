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
use App\Mail\DepositRequestCustomerMail;
use App\Models\User;
use Illuminate\Support\Str;

final class SendDepositRequestEmailDelivery
{
    public function __construct(
        private readonly DepositPortalLinkContext $depositLink,
        private readonly ConversationRecorder $conversations,
        private readonly CommunicationEventRecorder $communicationEvents,
        private readonly OutboundTransactionalMail $outboundMail,
    ) {}

    public function send(
        RepairOrder $repairOrder,
        User $actor,
        string $recipientEmail,
        int $amountCents,
    ): ConversationMessage {
        $context = $this->depositLink->forRepairOrder($repairOrder, $amountCents);
        $settings = ShopSettings::current();
        $shopName = $settings->shop_name ?: config('app.name', 'ARK-SMS');

        $mailResult = $this->outboundMail->sendMailable(
            TransactionalMailOperation::DepositRequestSend,
            $recipientEmail,
            new DepositRequestCustomerMail(
                repairOrder: $repairOrder,
                shopName: $shopName,
                portalUrl: $context['url'],
                amountDisplay: $context['amount_display'],
            ),
            'deposit-request-'.$repairOrder->repair_order_id.'-'.$amountCents.'-'.Str::uuid(),
            'repair_order',
            (string) $repairOrder->repair_order_id,
        );

        if (! $mailResult->ok()) {
            throw new TransactionalMailException($mailResult);
        }

        $summary = 'Deposit request emailed to '.$recipientEmail.'. Amount '.$context['amount_display'].'.';

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
