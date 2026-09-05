<?php

use App\Ark\LegacyInstallation\LegacyInstallationCommunications;
use App\Ark\LegacyInstallation\LegacyInstallationCommunicationsMigration;
use App\Ark\Mail\OutboundTransactionalMail;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyProviderManager;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

test('fresh install migrations do not expose turnkey postmark product columns', function () {
    expect(Schema::hasColumn('shop_settings', 'postmark_token'))->toBeFalse()
        ->and(Schema::hasColumn('shop_settings', 'email_provider'))->toBeFalse()
        ->and(Schema::hasColumn('shop_settings', 'twilio_account_sid'))->toBeFalse();
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
        ->and(LegacyInstallationCommunications::legacyPostmarkToken())->toBe('legacy-postmark-token-value')
        ->and(LegacyInstallationCommunications::legacyPostmarkMessageStreamId())->toBe('outbound')
        ->and(LegacyInstallationCommunications::legacyTwilioAccountSid())->toBe('AClegacyaccountsiddemotest00001')
        ->and(LegacyInstallationCommunications::legacyTwilioAuthToken())->toBe('legacy-auth-token-value');
});

test('legacy runtime activates postmark and twilio without cloud mail entitlement', function () {
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

    expect(LegacyInstallationCommunications::active())->toBeTrue()
        ->and(LegacyInstallationCommunications::legacyPostmarkConfigured())->toBeTrue()
        ->and(LegacyInstallationCommunications::legacyTwilioConfigured())->toBeTrue();

    ShopSettings::forgetCurrent();
    \App\Ark\Operations\Settings\ShopIntegrationRuntimeConfig::apply();

    expect(app(OutboundSmsTransport::class)->isConfigured())->toBeTrue()
        ->and(app(TelephonyProviderManager::class)->currentType())->toBe(TelephonyProviderType::Twilio);

    $this->app['env'] = 'production';
    config(['mail.default' => 'postmark']);

    $outbound = app(OutboundTransactionalMail::class);

    expect($outbound->providerMode())->toBe('legacy_postmark')
        ->and($outbound->isReady())->toBeTrue()
        ->and(config('services.postmark.token'))->toBe('legacy-postmark-token-value');

    Mail::fake();

    $result = $outbound->sendMailable(
        \App\Ark\Mail\TransactionalMailOperation::DocumentSend,
        'customer@example.test',
        new class extends \Illuminate\Mail\Mailable
        {
            public function content(): \Illuminate\Mail\Mailables\Content
            {
                return new \Illuminate\Mail\Mailables\Content(htmlString: '<p>legacy</p>');
            }

            public function envelope(): \Illuminate\Mail\Mailables\Envelope
            {
                return new \Illuminate\Mail\Mailables\Envelope(subject: 'Legacy');
            }
        },
        'legacy-idem-'.uniqid(),
    );

    expect($result->ok())->toBeTrue();
    Mail::assertSent(\Illuminate\Mail\Mailable::class);
});
