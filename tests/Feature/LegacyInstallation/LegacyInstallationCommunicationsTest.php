<?php

use App\Ark\LegacyInstallation\LegacyInstallationCommunications;
use App\Ark\LegacyInstallation\LegacyInstallationCommunicationsMigration;
use App\Ark\Mail\OutboundTransactionalMail;
use App\Ark\Operations\Messaging\NotConfiguredOutboundSmsTransport;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\Contracts\TelephonyProvider;
use App\Ark\Operations\Telephony\Providers\NotConfiguredTelephonyProvider;
use App\Ark\Operations\Telephony\TelephonyProviderManager;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Schema;

test('fresh install migrations drop empty turnkey credential columns', function () {
    expect(Schema::hasColumn('shop_settings', 'postmark_token'))->toBeFalse()
        ->and(Schema::hasColumn('shop_settings', 'email_provider'))->toBeFalse()
        ->and(Schema::hasColumn('shop_settings', 'twilio_account_sid'))->toBeFalse()
        ->and(Schema::hasColumn('shop_settings', 'twilio_auth_token'))->toBeFalse();
});

test('preserve helper keeps populated postmark and twilio columns in place', function () {
    if (! Schema::hasColumn('shop_settings', 'postmark_token')) {
        Schema::table('shop_settings', function ($table): void {
            $table->text('postmark_token')->nullable();
            $table->string('postmark_message_stream_id', 64)->nullable();
            $table->string('twilio_account_sid', 64)->nullable();
            $table->text('twilio_auth_token')->nullable();
        });
    }

    ShopSettings::current()->persistTrusted([
        'postmark_token' => 'legacy-postmark-token-value',
        'postmark_message_stream_id' => 'outbound',
        'twilio_account_sid' => 'AClegacyaccountsiddemotest00001',
        'twilio_auth_token' => 'legacy-auth-token-value',
    ]);

    LegacyInstallationCommunicationsMigration::preservePopulatedOrDropEmptyColumns([
        'postmark_token',
        'postmark_message_stream_id',
        'twilio_account_sid',
        'twilio_auth_token',
    ]);

    expect(Schema::hasColumn('shop_settings', 'postmark_token'))->toBeTrue()
        ->and(Schema::hasColumn('shop_settings', 'twilio_account_sid'))->toBeTrue()
        ->and(Schema::hasColumn('shop_settings', 'twilio_auth_token'))->toBeTrue();

    $settings = ShopSettings::current()->fresh();

    expect(trim((string) $settings->postmark_token))->toBe('legacy-postmark-token-value')
        ->and(trim((string) $settings->postmark_message_stream_id))->toBe('outbound')
        ->and(trim((string) $settings->twilio_account_sid))->toBe('AClegacyaccountsiddemotest00001')
        ->and(trim((string) $settings->twilio_auth_token))->toBe('legacy-auth-token-value')
        ->and(LegacyInstallationCommunications::hasLegacyPostmarkColumns($settings))->toBeTrue()
        ->and(LegacyInstallationCommunications::hasLegacyTwilioColumns($settings))->toBeTrue()
        ->and(LegacyInstallationCommunications::active($settings))->toBeTrue();
});

test('stock Core does not activate Twilio or Postmark when legacy credentials are populated', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    if (! Schema::hasColumn('shop_settings', 'postmark_token')) {
        Schema::table('shop_settings', function ($table): void {
            $table->text('postmark_token')->nullable();
            $table->string('twilio_account_sid', 64)->nullable();
            $table->text('twilio_auth_token')->nullable();
        });
    }

    ShopSettings::current()->persistTrusted([
        'postmark_token' => 'legacy-postmark-token-value',
        'twilio_account_sid' => 'AClegacyaccountsiddemotest00001',
        'twilio_auth_token' => 'legacy-auth-token-value',
        'telephony_provider' => 'twilio',
        'telephony_inbound_number' => '+17195550100',
        'cloud_status' => null,
        'cloud_credential' => null,
        'ark_mail_status' => null,
        'ark_mail_credential' => null,
    ]);

    ShopSettings::forgetCurrent();
    app()->forgetInstance(ShopIntegrationCredentials::class);
    app()->forgetInstance(OutboundTransactionalMail::class);
    app()->forgetInstance(OutboundSmsTransport::class);
    app()->forgetInstance(TelephonyProvider::class);

    expect(LegacyInstallationCommunications::active())->toBeTrue()
        ->and(LegacyInstallationCommunications::hasLegacyPostmarkColumns())->toBeTrue()
        ->and(LegacyInstallationCommunications::hasLegacyTwilioColumns())->toBeTrue();

    ShopSettings::forgetCurrent();
    \App\Ark\Operations\Settings\ShopIntegrationRuntimeConfig::apply();

    expect(app(OutboundSmsTransport::class))->toBeInstanceOf(NotConfiguredOutboundSmsTransport::class)
        ->and(app(OutboundSmsTransport::class)->isConfigured())->toBeFalse()
        ->and(app(TelephonyProvider::class))->toBeInstanceOf(NotConfiguredTelephonyProvider::class)
        ->and(app(TelephonyProviderManager::class)->currentType())->toBe(TelephonyProviderType::None)
        ->and(config('services.postmark.token'))->not->toBe('legacy-postmark-token-value')
        ->and(config('services.twilio.account_sid'))->not->toBe('AClegacyaccountsiddemotest00001');

    $this->app['env'] = 'production';
    config(['mail.default' => 'postmark']);

    $outbound = app(OutboundTransactionalMail::class);

    expect($outbound->providerMode())->toBe('none')
        ->and($outbound->isReady())->toBeFalse()
        ->and($outbound->providerMode())->not->toBe('legacy_postmark');

    expect(fn () => route('webhooks.communications.twilio.messaging.incoming', absolute: false))
        ->toThrow(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);
});
