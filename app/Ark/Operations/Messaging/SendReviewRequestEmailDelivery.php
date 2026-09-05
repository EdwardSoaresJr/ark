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
use App\Mail\ReviewRequestCustomerMail;
use App\Models\User;
use Illuminate\Support\Str;

final class SendReviewRequestEmailDelivery
{
    public function __construct(
        private readonly ConversationRecorder $conversations,
        private readonly CommunicationEventRecorder $communicationEvents,
        private readonly OutboundTransactionalMail $outboundMail,
    ) {}

    public function send(RepairOrder $repairOrder, User $actor, string $recipientEmail): ConversationMessage
    {
        $shopName = ReviewRequestCopy::shopName();
        $reviewUrl = ReviewRequestCopy::reviewUrl();
        $contactUrl = ReviewRequestCopy::contactUrl();

        $mailResult = $this->outboundMail->sendMailable(
            TransactionalMailOperation::ReviewRequestSend,
            $recipientEmail,
            new ReviewRequestCustomerMail(
                repairOrder: $repairOrder,
                shopName: $shopName,
                reviewUrl: $reviewUrl,
                contactUrl: $contactUrl,
                shopPhone: ReviewRequestCopy::shopPhoneDisplay(),
            ),
            'review-request-'.$repairOrder->repair_order_id.'-'.Str::uuid(),
            'repair_order',
            (string) $repairOrder->repair_order_id,
        );

        if (! $mailResult->ok()) {
            throw new TransactionalMailException($mailResult);
        }

        $summary = 'Review request emailed to '.$recipientEmail.'.';

        $message = $this->conversations->recordRepairOrderEmail(
            $repairOrder,
            $actor,
            $recipientEmail,
            $summary,
            metadata: [
                'kind' => ReviewRequestAuthority::METADATA_KIND,
                'review_url' => $reviewUrl,
                'contact_url' => $contactUrl,
            ],
        );

        $this->communicationEvents->record(
            $repairOrder,
            OperationalCommunicationType::ApprovalFollowUp,
            OperationalCommunicationChannel::Email,
            OperationalCommunicationDirection::Outbound,
            $summary,
            actor: $actor,
            message: $message,
        );

        return $message;
    }
}
