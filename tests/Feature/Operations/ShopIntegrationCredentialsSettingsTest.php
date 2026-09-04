<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Schema;

test('customer messaging settings save message actions without transport credential fields', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    ShopSettings::current()->persistTrusted([
        'learn_training_gate_enabled' => false,
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.customer-messaging.update'), [
            'message_actions' => [
                'tow_company' => 'Pinkys Towing',
                'tow_phone' => '7195550100',
            ],
            'postmark_reply_to' => 'service@example.com',
            'postmark_reply_to_name' => 'Example Shop',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'customer-messaging',
        ]));

    $settings = ShopSettings::current()->fresh();

    expect($settings->postmark_reply_to)->toBe('service@example.com')
        ->and($settings->postmark_reply_to_name)->toBe('Example Shop')
        ->and($settings->message_actions['tow_company'] ?? null)->toBe('Pinkys Towing')
        ->and(Schema::hasColumn('shop_settings', 'postmark_token'))->toBeFalse()
        ->and(Schema::hasColumn('shop_settings', 'email_provider'))->toBeFalse();
});

test('integration settings pages hide cloud transport credential fields', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.settings.shop.edit', ['section' => 'ark-cloud']))
        ->assertOk()
        ->assertDontSee('Account SID', false)
        ->assertDontSee('Auth token', false);

    $this->get(route('operations.settings.shop.edit', ['section' => 'customer-messaging']))
        ->assertOk()
        ->assertSee('Reply-to address');

    $this->get(route('operations.settings.shop.edit'))
        ->assertOk()
        ->assertDontSee('Square Payments', false)
        ->assertDontSee('Application ID', false);
});


test('stock core integration credentials report messaging not configured', function () {
    $credentials = \App\Ark\Operations\Settings\ShopIntegrationCredentials::forCurrentShop();

    expect($credentials->messagingConfigured())->toBeFalse()
        ->and($credentials->twilioCredentialSource())->toBe('none')
        ->and($credentials->partsTechCredentialSource())->toBe('none')
        ->and($credentials->partsTechCatalogConfigured())->toBeFalse();
});
