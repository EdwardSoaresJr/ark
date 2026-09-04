<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\TelephonyBusinessHoursLabel;
use App\Support\Mail\ShopMailBranding;

final class WebsiteLeadConfirmationCopy
{
    public static function smsBody(Lead $lead): string
    {
        $shopName = ShopMailBranding::shopName();
        $responseHint = self::responseTimeHint();
        $firstName = self::firstName($lead);

        $greeting = filled($firstName) ? "Hi {$firstName}, " : '';

        $body = sprintf(
            '%s%s: We received your request. A service advisor will review it and follow up soon.',
            $greeting,
            $shopName,
        );

        if (filled($responseHint)) {
            $body .= ' '.$responseHint;
        }

        $body .= ' Reply STOP to opt out.';

        return $body;
    }

    public static function emailSubject(?Lead $lead = null): string
    {
        return sprintf('%s — request received', ShopMailBranding::shopName());
    }

    /**
     * @return array{intro: string, response_hint: string|null, phone_display: string|null}
     */
    public static function emailViewData(Lead $lead): array
    {
        $shop = ShopSettings::current();

        return [
            'intro' => sprintf(
                '%s we received your vehicle concern and a service advisor will review it soon.',
                filled(self::firstName($lead)) ? self::firstName($lead).',' : 'Hi,',
            ),
            'response_hint' => self::responseTimeHint(),
            'phone_display' => PhoneNumber::display($shop->phone),
        ];
    }

    private static function responseTimeHint(): ?string
    {
        $hours = TelephonyBusinessHoursLabel::fromCallFlow();

        if ($hours === '' || $hours === 'Closed') {
            return null;
        }

        return 'Shop hours: '.$hours.'.';
    }

    private static function firstName(Lead $lead): ?string
    {
        $name = trim((string) ($lead->contact_name ?? ''));

        if ($name === '') {
            return null;
        }

        return strtok($name, ' ') ?: null;
    }
}
