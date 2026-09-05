<?php

namespace App\Ark\Operations\Settings;

use App\Ark\LegacyInstallation\LegacyInstallationCommunications;

final class ShopIntegrationCredentials
{
    public function __construct(
        private readonly ShopSettings $settings,
    ) {}

    public static function forCurrentShop(): self
    {
        return new self(ShopSettings::current());
    }

    public function messagingConfigured(): bool
    {
        return app(\App\Ark\Operations\Messaging\OutboundSmsTransport::class)->isConfigured();
    }

    public function twilioConfigured(): bool
    {
        return LegacyInstallationCommunications::legacyTwilioConfigured($this->settings)
            || $this->messagingConfigured();
    }

    public function twilioAccountSid(): ?string
    {
        return LegacyInstallationCommunications::legacyTwilioAccountSid($this->settings);
    }

    public function twilioAuthToken(): ?string
    {
        return LegacyInstallationCommunications::legacyTwilioAuthToken($this->settings);
    }

    public function hasStoredTwilioAuthToken(): bool
    {
        foreach (['legacy_twilio_auth_token', 'twilio_auth_token'] as $column) {
            if (filled($this->settings->{$column} ?? null)) {
                return true;
            }
        }

        return false;
    }

    public function twilioCredentialSource(): string
    {
        if ($this->hasStoredTwilioAuthToken() || filled($this->settings->legacy_twilio_account_sid ?? $this->settings->twilio_account_sid ?? null)) {
            return 'database';
        }

        if (filled(config('services.twilio.auth_token')) || filled(config('services.twilio.account_sid'))) {
            return 'env';
        }

        return $this->messagingConfigured() ? 'transport' : 'none';
    }

    public function postmarkToken(): ?string
    {
        return LegacyInstallationCommunications::legacyPostmarkToken($this->settings);
    }

    public function postmarkReplyTo(): ?string
    {
        return $this->resolve($this->settings->postmark_reply_to, config('mail.reply_to.address'));
    }

    public function postmarkReplyToName(): ?string
    {
        return $this->resolve($this->settings->postmark_reply_to_name, config('mail.reply_to.name'));
    }

    public function postmarkMessageStreamId(): ?string
    {
        return LegacyInstallationCommunications::legacyPostmarkMessageStreamId($this->settings);
    }

    public function postmarkConfigured(): bool
    {
        return filled($this->postmarkToken());
    }

    public function hasStoredPostmarkToken(): bool
    {
        foreach (['legacy_postmark_token', 'postmark_token'] as $column) {
            if (filled($this->settings->{$column} ?? null)) {
                return true;
            }
        }

        return false;
    }

    public function postmarkCredentialSource(): string
    {
        if ($this->hasStoredPostmarkToken()) {
            return 'database';
        }

        if (filled(config('services.postmark.token'))) {
            return 'env';
        }

        return 'none';
    }

    public function partsTechBaseUrl(): string
    {
        return '';
    }

    public function partsTechCatalogPath(): string
    {
        return '';
    }

    public function partsTechUsername(): ?string
    {
        return null;
    }

    public function partsTechApiKey(): ?string
    {
        return null;
    }

    public function partsTechPassword(): ?string
    {
        return null;
    }

    public function partsTechCatalogConfigured(): bool
    {
        return false;
    }

    public function partsTechQuoteImportConfigured(): bool
    {
        return false;
    }

    public function hasStoredPartsTechApiKey(): bool
    {
        return false;
    }

    public function hasStoredPartsTechPassword(): bool
    {
        return false;
    }

    public function partsTechCredentialSource(): string
    {
        return 'none';
    }

    public function mailReplyTo(): ?string
    {
        return $this->postmarkReplyTo();
    }

    public function mailReplyToName(): ?string
    {
        return $this->postmarkReplyToName();
    }

    public function transactionalEmailConfigured(): bool
    {
        return app(\App\Ark\Mail\OutboundTransactionalMail::class)->isReady();
    }

    public function openaiApiKey(): ?string
    {
        return null;
    }

    public function openaiConfigured(): bool
    {
        return false;
    }

    public function hasStoredOpenaiApiKey(): bool
    {
        return false;
    }

    public function openaiTranscriptionModel(): string
    {
        return 'whisper-1';
    }

    public function openaiAnalysisModel(): string
    {
        return 'gpt-4o-mini';
    }

    public function openaiCredentialSource(): string
    {
        return 'none';
    }

    public function credentialSourceLabel(string $source): string
    {
        return match ($source) {
            'database' => 'Currently loaded from shop settings.',
            'env' => 'Currently loaded from server environment fallback.',
            'transport' => 'Currently loaded from legacy transport configuration.',
            default => 'Not configured yet.',
        };
    }

    private function resolve(?string $databaseValue, mixed $environmentValue): ?string
    {
        $database = trim((string) ($databaseValue ?? ''));

        if ($database !== '') {
            return $database;
        }

        $environment = trim((string) ($environmentValue ?? ''));

        return $environment !== '' ? $environment : null;
    }
}
