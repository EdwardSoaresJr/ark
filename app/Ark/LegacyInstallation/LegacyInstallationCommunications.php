<?php

namespace App\Ark\LegacyInstallation;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Schema;

/**
 * Compatibility boundary for shops that adopted Postmark/Twilio before public Core
 * removed turnkey provider product surfaces. Active only when legacy authority exists.
 */
final class LegacyInstallationCommunications
{
    private const LEGACY_POSTMARK_TOKEN = 'legacy_postmark_token';

    private const LEGACY_POSTMARK_STREAM = 'legacy_postmark_message_stream_id';

    private const LEGACY_TWILIO_COLUMNS = [
        'legacy_twilio_account_sid',
        'legacy_twilio_auth_token',
        'legacy_twilio_api_key_sid',
        'legacy_twilio_api_key_secret',
        'legacy_twilio_voice_twiml_app_sid',
        'legacy_twilio_fcm_credential_sid',
        'legacy_twilio_apns_voip_credential_sid',
    ];

    private const TRANSITIONAL_TWILIO_COLUMNS = [
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_api_key_sid',
        'twilio_api_key_secret',
        'twilio_voice_twiml_app_sid',
        'twilio_fcm_credential_sid',
        'twilio_apns_voip_credential_sid',
    ];

    public static function active(?ShopSettings $settings = null): bool
    {
        return self::legacyPostmarkConfigured($settings)
            || self::legacyTwilioConfigured($settings);
    }

    public static function legacyPostmarkConfigured(?ShopSettings $settings = null): bool
    {
        return filled(self::legacyPostmarkToken($settings));
    }

    public static function legacyPostmarkToken(?ShopSettings $settings = null): ?string
    {
        $settings ??= self::safeCurrentSettings();

        if ($settings === null) {
            return self::nonEmptyEnv('POSTMARK_TOKEN') ?? self::nonEmptyEnv('POSTMARK_API_KEY');
        }

        foreach ([self::LEGACY_POSTMARK_TOKEN, 'postmark_token'] as $column) {
            if (! self::hasColumn($column)) {
                continue;
            }

            $value = trim((string) ($settings->{$column} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return self::nonEmptyEnv('POSTMARK_TOKEN') ?? self::nonEmptyEnv('POSTMARK_API_KEY');
    }

    public static function legacyPostmarkMessageStreamId(?ShopSettings $settings = null): ?string
    {
        $settings ??= self::safeCurrentSettings();

        if ($settings !== null) {
            foreach ([self::LEGACY_POSTMARK_STREAM, 'postmark_message_stream_id'] as $column) {
                if (! self::hasColumn($column)) {
                    continue;
                }

                $value = trim((string) ($settings->{$column} ?? ''));

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return self::nonEmptyEnv('POSTMARK_MESSAGE_STREAM_ID')
            ?? self::nonEmptyEnv('POSTMARK_MESSAGE_STREAM');
    }

    public static function legacyTwilioConfigured(?ShopSettings $settings = null): bool
    {
        return filled(self::legacyTwilioAccountSid($settings))
            && filled(self::legacyTwilioAuthToken($settings));
    }

    public static function legacyTwilioAccountSid(?ShopSettings $settings = null): ?string
    {
        return self::resolveTwilioColumn('legacy_twilio_account_sid', 'twilio_account_sid', 'TWILIO_ACCOUNT_SID', $settings);
    }

    public static function legacyTwilioAuthToken(?ShopSettings $settings = null): ?string
    {
        return self::resolveTwilioColumn('legacy_twilio_auth_token', 'twilio_auth_token', 'TWILIO_AUTH_TOKEN', $settings);
    }

    public static function legacyTwilioApiKeySid(?ShopSettings $settings = null): ?string
    {
        return self::resolveTwilioColumn('legacy_twilio_api_key_sid', 'twilio_api_key_sid', 'TWILIO_API_KEY_SID', $settings);
    }

    public static function legacyTwilioApiKeySecret(?ShopSettings $settings = null): ?string
    {
        return self::resolveTwilioColumn('legacy_twilio_api_key_secret', 'twilio_api_key_secret', 'TWILIO_API_KEY_SECRET', $settings);
    }

    public static function legacyTwilioVoiceTwimlAppSid(?ShopSettings $settings = null): ?string
    {
        return self::resolveTwilioColumn('legacy_twilio_voice_twiml_app_sid', 'twilio_voice_twiml_app_sid', 'TWILIO_VOICE_TWIML_APP_SID', $settings);
    }

    /**
     * @return list<string>
     */
    public static function legacyTwilioColumnNames(): array
    {
        return array_merge(self::LEGACY_TWILIO_COLUMNS, self::TRANSITIONAL_TWILIO_COLUMNS);
    }

    private static function resolveTwilioColumn(
        string $legacyColumn,
        string $transitionalColumn,
        string $envKey,
        ?ShopSettings $settings,
    ): ?string {
        $settings ??= self::safeCurrentSettings();

        if ($settings !== null) {
            foreach ([$legacyColumn, $transitionalColumn] as $column) {
                if (! self::hasColumn($column)) {
                    continue;
                }

                $value = trim((string) ($settings->{$column} ?? ''));

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return self::nonEmptyEnv($envKey);
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
