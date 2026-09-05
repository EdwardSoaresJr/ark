<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\LegacyInstallation\LegacyInstallationCommunications;
use App\Ark\Operations\Telephony\Contracts\TelephonyProvider;
use App\Ark\Operations\Telephony\Providers\NotConfiguredTelephonyProvider;
use App\Ark\Operations\Telephony\Providers\TwilioTelephonyProvider;
use InvalidArgumentException;
use RuntimeException;

class TelephonyProviderManager
{
    public function current(): TelephonyProvider
    {
        if (LegacyInstallationCommunications::active()) {
            return app(TwilioTelephonyProvider::class);
        }

        return app(NotConfiguredTelephonyProvider::class);
    }

    public function currentType(): TelephonyProviderType
    {
        if (LegacyInstallationCommunications::legacyTwilioConfigured()) {
            return TelephonyProviderType::Twilio;
        }

        return TelephonyProviderType::None;
    }

    public function resolve(TelephonyProviderType $type): TelephonyProvider
    {
        if (LegacyInstallationCommunications::active()) {
            return app(TwilioTelephonyProvider::class);
        }

        return app(NotConfiguredTelephonyProvider::class);
    }

    /**
     * Twilio HTTP webhooks always parse Twilio payloads when legacy authority exists.
     */
    public function twilio(): TwilioTelephonyProvider
    {
        if (! LegacyInstallationCommunications::legacyTwilioConfigured()) {
            throw new RuntimeException('Voice telephony is not configured.');
        }

        $provider = $this->resolve(TelephonyProviderType::Twilio);

        if (! $provider instanceof TwilioTelephonyProvider) {
            throw new InvalidArgumentException('Twilio telephony provider is not registered.');
        }

        return $provider;
    }
}
