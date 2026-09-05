<?php

use App\Ark\Platform\PlatformServiceCatalog;
use App\Ark\Platform\PlatformConnection;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyForwardNumber;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('legacy communications settings urls redirect to ark cloud or customer messaging', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'email',
        ]))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'ark-cloud']));

    $this->actingAs($admin)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'ring',
        ]))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'customer-messaging']));
});

test('settings ark cloud section shows connect and service catalog without provider controls', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', ['section' => 'ark-cloud']))
        ->assertOk()
        ->assertSee('ARK Platform')
        ->assertSee('Connect ARK Platform')
        ->assertSee('ARK Email')
        ->assertSee('ARK Texting')
        ->assertSee('ARK Voice')
        ->assertSee('Dragon AI')
        ->assertSee('Requires ARK Platform')
        ->assertDontSee('Account SID')
        ->assertDontSee('Auth token')
        ->assertDontSee('Test incoming call')
        ->assertDontSee('Call routing');
});

test('settings customer messaging section keeps shop-owned messaging policy', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', ['section' => 'customer-messaging']))
        ->assertOk()
        ->assertSee('Customer Messaging')
        ->assertSee('Message Actions')
        ->assertSee('Missed call text-back')
        ->assertDontSee('Add ring target')
        ->assertDontSee('Record inbound shop calls');
});

test('shop profile includes business hours', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', ['section' => 'general']))
        ->assertOk()
        ->assertSee('Business hours')
        ->assertSee('Holiday closures');
});

test('saving shop profile preserves unrelated call flow fields', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'caller_ring_tone' => 'https://cdn.example.com/promo.mp3',
            'missed_call_rescue_enabled' => true,
        ]),
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.general.update'), [
            'shop_name' => 'Demo Shop',
            'shop_timezone' => 'America/Denver',
            'telephony_call_flow' => [
                'weekly_hours' => ShopSettings::defaultTelephonyCallFlow()['weekly_hours'],
                'closed_dates' => "2026-12-25\n",
            ],
        ])
        ->assertRedirect();

    $flow = TelephonyCallFlowSettings::fromShopSettings();

    expect($flow->callerRingTone())->toBe('https://cdn.example.com/promo.mp3')
        ->and($flow->missedCallRescueEnabled())->toBeTrue()
        ->and($flow->toArray()['closed_dates'])->toBe(['2026-12-25']);
});

test('telephony forward number resolves from ring endpoint', function () {
    TelephonyEndpoint::query()->delete();

    TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '+17195550999',
        'enabled' => true,
        'position' => 0,
    ]);

    expect(TelephonyForwardNumber::resolve())->toBe('7195550999')
        ->and(TelephonyForwardNumber::sourceLabel())->toBe('Ring group');
});

test('settings managers can test incoming call route still exists', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('broadcasting.default', 'null');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->postJson(route('operations.settings.telephony.test-incoming-call'), [
            'phone' => '7195551234',
        ])
        ->assertStatus(503)
        ->assertJsonPath('message', 'Voice telephony is not configured.');

    expect(CallSession::query()->count())->toBe(0);
});

test('ark cloud service catalog renders Cloud projection when connected', function () {
    ShopSettings::current()->persistTrusted([
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'secret',
        'ark_mail_status' => 'connected',
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'cloud.example.test/api/v1/status' => \Illuminate\Support\Facades\Http::response([
            'ok' => true,
            'services' => [
                ['key' => 'mail', 'label' => 'ARK Email', 'status' => 'needs_setup', 'status_label' => 'Needs setup', 'detail' => null],
            ],
        ], 200),
    ]);

    $services = PlatformServiceCatalog::forCurrentShop()->services();
    $mail = collect($services)->firstWhere('key', 'mail');

    expect($mail['status'])->toBe('needs_setup')
        ->and($mail['status_label'])->toBe('Needs setup')
        ->and(PlatformConnection::current()->isConnected())->toBeTrue();
});
