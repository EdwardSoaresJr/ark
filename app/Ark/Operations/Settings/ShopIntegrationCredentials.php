<?php

namespace App\Ark\Operations\Settings;

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
        return $this->messagingConfigured();
    }

    public function twilioAccountSid(): ?string
    {
        return null;
    }

    public function twilioAuthToken(): ?string
    {
        return null;
    }

    public function hasStoredTwilioAuthToken(): bool
    {
        return false;
    }

    public function twilioCredentialSource(): string
    {
        return $this->messagingConfigured() ? 'transport' : 'none';
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
        return $this->resolve($this->settings->postmark_reply_to, config('mail.reply_to.address'));
    }

    public function mailReplyToName(): ?string
    {
        return $this->resolve($this->settings->postmark_reply_to_name, config('mail.reply_to.name'));
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
