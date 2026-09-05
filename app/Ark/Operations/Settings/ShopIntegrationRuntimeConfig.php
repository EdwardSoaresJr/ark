<?php

namespace App\Ark\Operations\Settings;

use App\Ark\LegacyInstallation\LegacyInstallationCommunications;
use Throwable;

/**
 * Applies shop runtime configuration for legacy installations and reply-to identity.
 */
final class ShopIntegrationRuntimeConfig
{
    public static function apply(): void
    {
        try {
            $credentials = ShopIntegrationCredentials::forCurrentShop();
        } catch (Throwable) {
            return;
        }

        if (LegacyInstallationCommunications::legacyPostmarkConfigured()) {
            $postmarkToken = $credentials->postmarkToken();

            if ($postmarkToken !== null) {
                config(['services.postmark.token' => $postmarkToken]);
            }

            $messageStreamId = $credentials->postmarkMessageStreamId();

            if ($messageStreamId !== null) {
                config(['services.postmark.message_stream_id' => $messageStreamId]);
            }
        }

        if (LegacyInstallationCommunications::legacyTwilioConfigured()) {
            config([
                'services.twilio.account_sid' => $credentials->twilioAccountSid(),
                'services.twilio.auth_token' => $credentials->twilioAuthToken(),
                'services.twilio.api_key_sid' => LegacyInstallationCommunications::legacyTwilioApiKeySid(),
                'services.twilio.api_key_secret' => LegacyInstallationCommunications::legacyTwilioApiKeySecret(),
                'services.twilio.voice_twiml_app_sid' => LegacyInstallationCommunications::legacyTwilioVoiceTwimlAppSid(),
            ]);
        }

        $replyTo = $credentials->mailReplyTo();

        if ($replyTo !== null) {
            config(['mail.reply_to.address' => $replyTo]);
        }

        $replyToName = $credentials->mailReplyToName();

        if ($replyToName !== null) {
            config(['mail.reply_to.name' => $replyToName]);
        }
    }
}
