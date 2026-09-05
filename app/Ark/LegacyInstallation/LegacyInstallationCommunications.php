<?php

namespace App\Ark\LegacyInstallation;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Schema;

/**
 * Detects whether legacy Postmark/Twilio credential columns or env values are present.
 * Diagnostics and migration awareness only — does not inject config or activate providers.
 */
final class LegacyInstallationCommunications
{
    private const POSTMARK_COLUMNS = [
        'legacy_postmark_token',
        'postmark_token',
    ];

    private const TWILIO_SID_COLUMNS = [
        'legacy_twilio_account_sid',
        'twilio_account_sid',
    ];

    private const TWILIO_TOKEN_COLUMNS = [
        'legacy_twilio_auth_token',
        'twilio_auth_token',
    ];

    public static function active(?ShopSettings $settings = null): bool
    {
        return self::hasLegacyPostmarkColumns($settings)
            || self::hasLegacyTwilioColumns($settings);
    }

    public static function hasLegacyPostmarkColumns(?ShopSettings $settings = null): bool
    {
        if (self::columnPopulated(self::POSTMARK_COLUMNS, $settings)) {
            return true;
        }

        return self::nonEmptyEnv('POSTMARK_TOKEN') !== null
            || self::nonEmptyEnv('POSTMARK_API_KEY') !== null;
    }

    public static function hasLegacyTwilioColumns(?ShopSettings $settings = null): bool
    {
        $sidPresent = self::columnPopulated(self::TWILIO_SID_COLUMNS, $settings)
            || self::nonEmptyEnv('TWILIO_ACCOUNT_SID') !== null;
        $tokenPresent = self::columnPopulated(self::TWILIO_TOKEN_COLUMNS, $settings)
            || self::nonEmptyEnv('TWILIO_AUTH_TOKEN') !== null;

        return $sidPresent && $tokenPresent;
    }

    /**
     * @param  list<string>  $columns
     */
    private static function columnPopulated(array $columns, ?ShopSettings $settings): bool
    {
        $settings ??= self::safeCurrentSettings();

        if ($settings === null) {
            return false;
        }

        foreach ($columns as $column) {
            if (! self::hasColumn($column)) {
                continue;
            }

            if (trim((string) ($settings->{$column} ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function safeCurrentSettings(): ?ShopSettings
    {
        try {
            return ShopSettings::current();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function hasColumn(string $column): bool
    {
        return Schema::hasTable('shop_settings')
            && Schema::hasColumn('shop_settings', $column);
    }

    private static function nonEmptyEnv(string $key): ?string
    {
        $value = trim((string) env($key, ''));

        return $value !== '' ? $value : null;
    }
}
