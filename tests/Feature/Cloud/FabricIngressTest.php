<?php

use App\Ark\Cloud\CloudConnection;
use App\Ark\Cloud\Http\VerifyCloudFabricSignature;
use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Communications\Events\CommsInterruptReceived;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\Events\IncomingCallReceived;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');

    InstallationIdentity::write((string) Str::uuid());
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Casey Auto Repair',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_shop_public_id' => (string) Str::uuid(),
        'cloud_credential' => 'test-credential-32-characters-min!!',
        'ark_mail_status' => 'connected',
    ]);
});

/**
 * @return array{0: string, 1: array<string, string>}
 */
function fabricSignedRequest(array $body, ?string $installationId = null, ?string $nonce = null, ?string $credential = null): array
{
    $raw = json_encode($body, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $nonce ??= Str::random(24);
    $installationId ??= InstallationIdentity::uuid();
    $credential ??= (string) CloudConnection::current()->credential();

    $signature = hash_hmac('sha256', implode("\n", [
        $timestamp,
        $nonce,
        'POST',
        VerifyCloudFabricSignature::PATH,
        hash('sha256', $raw),
    ]), $credential);

    return [$raw, [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_ARK_INSTALLATION_ID' => $installationId,
        'HTTP_X_ARK_TIMESTAMP' => $timestamp,
        'HTTP_X_ARK_NONCE' => $nonce,
        'HTTP_X_ARK_SIGNATURE' => $signature,
    ]];
}

test('fabric ingress rejects missing signature', function () {
    $body = [
        'operation' => 'voice.incoming.started',
        'installation_id' => InstallationIdentity::uuid(),
        'payload' => [
            'interrupt' => [
                'call_session_id' => -1,
                'display_phone' => '(719) 555-0100',
                'kind' => 'call',
            ],
        ],
    ];

    $this->call(
        'POST',
        '/webhooks/cloud/fabric/events',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ARK_INSTALLATION_ID' => InstallationIdentity::uuid(),
            'HTTP_X_ARK_TIMESTAMP' => (string) time(),
            'HTTP_X_ARK_NONCE' => Str::random(24),
        ],
        json_encode($body, JSON_THROW_ON_ERROR),
    )->assertUnauthorized();
});

test('fabric ingress rejects wrong installation', function () {
    $body = [
        'operation' => 'voice.incoming.started',
        'installation_id' => InstallationIdentity::uuid(),
        'payload' => [
            'interrupt' => [
                'call_session_id' => -1,
                'display_phone' => '(719) 555-0100',
                'kind' => 'call',
            ],
        ],
    ];

    [$raw, $server] = fabricSignedRequest($body, installationId: (string) Str::uuid());

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertUnauthorized();
});

test('fabric ingress rejects replay nonce', function () {
    $body = [
        'operation' => 'voice.incoming.started',
        'installation_id' => InstallationIdentity::uuid(),
        'occurred_at' => now()->toIso8601String(),
        'payload' => [
            'interrupt' => [
                'call_session_id' => -42,
                'display_phone' => '(719) 555-0100',
                'kind' => 'call',
            ],
        ],
    ];

    $nonce = Str::random(24);
    [$raw, $server] = fabricSignedRequest($body, nonce: $nonce);

    Event::fake([IncomingCallReceived::class, CommsInterruptReceived::class]);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertOk();

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertUnauthorized();
});

test('fabric ingress accepts voice.incoming.started and broadcasts interrupts', function () {
    Event::fake([IncomingCallReceived::class, CommsInterruptReceived::class]);

    $body = [
        'operation' => 'voice.incoming.started',
        'installation_id' => InstallationIdentity::uuid(),
        'occurred_at' => now()->toIso8601String(),
        'payload' => [
            'interrupt' => [
                'call_session_id' => -7,
                'display_phone' => '(719) 555-0142',
                'kind' => 'call',
                'matched' => false,
                'customer_name' => null,
            ],
        ],
    ];

    [$raw, $server] = fabricSignedRequest($body);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertOk()
        ->assertJson(['ok' => true]);

    Event::assertDispatched(IncomingCallReceived::class, function (IncomingCallReceived $event): bool {
        return (int) ($event->context['call_session_id'] ?? 0) === -7
            && ($event->context['display_phone'] ?? null) === '(719) 555-0142'
            && ($event->context['kind'] ?? null) === 'call';
    });

    Event::assertDispatched(CommsInterruptReceived::class, function (CommsInterruptReceived $event): bool {
        return ($event->payload['kind'] ?? null) === 'call'
            && ($event->payload['action'] ?? null) === 'show'
            && (int) ($event->payload['interrupt']['call_session_id'] ?? 0) === -7;
    });
});

test('fabric ingress rejects unknown operation', function () {
    $body = [
        'operation' => 'voice.unknown.event',
        'installation_id' => InstallationIdentity::uuid(),
        'payload' => [],
    ];

    [$raw, $server] = fabricSignedRequest($body);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertStatus(422)
        ->assertJson(['ok' => false, 'error' => 'unknown_operation']);
});

test('fabric ingress rejects when cloud not connected', function () {
    CloudConnection::current()->clear();
    Cache::flush();

    $body = [
        'operation' => 'voice.incoming.started',
        'installation_id' => InstallationIdentity::uuid(),
        'payload' => [
            'interrupt' => [
                'call_session_id' => -1,
                'display_phone' => '(719) 555-0100',
                'kind' => 'call',
            ],
        ],
    ];

    $raw = json_encode($body, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $nonce = Str::random(24);
    $signature = hash_hmac('sha256', implode("\n", [
        $timestamp,
        $nonce,
        'POST',
        VerifyCloudFabricSignature::PATH,
        hash('sha256', $raw),
    ]), 'stale-credential-after-disconnect!!!!');

    $this->call(
        'POST',
        '/webhooks/cloud/fabric/events',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ARK_INSTALLATION_ID' => InstallationIdentity::uuid(),
            'HTTP_X_ARK_TIMESTAMP' => $timestamp,
            'HTTP_X_ARK_NONCE' => $nonce,
            'HTTP_X_ARK_SIGNATURE' => $signature,
        ],
        $raw,
    )->assertUnauthorized();
});
