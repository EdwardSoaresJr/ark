<?php

namespace App\Ark\LegacyInstallation;

use App\Ark\Operations\Messaging\OutboundSmsResult;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Messaging\TwilioMessagingSender;

final class LegacyTwilioOutboundSmsTransport implements OutboundSmsTransport
{
    public function __construct(
        private readonly TwilioMessagingSender $sender,
    ) {}

    public function isConfigured(): bool
    {
        return LegacyInstallationCommunications::legacyTwilioConfigured();
    }

    public function send(string $toPhone, string $body, array $mediaUrls = []): OutboundSmsResult
    {
        $result = $this->sender->send($toPhone, $body, $mediaUrls);

        return new OutboundSmsResult(
            providerMessageId: $result->messageSid,
            status: $result->status,
        );
    }
}
