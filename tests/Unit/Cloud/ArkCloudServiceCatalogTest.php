<?php

use App\Ark\Cloud\ArkCloudServiceCatalog;
use App\Ark\Cloud\CloudStatusClient;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;

test('disconnected box reports requires ARK Cloud for managed services', function () {
    ShopSettings::current()->persistTrusted([
        'cloud_status' => null,
        'cloud_credential' => null,
    ]);

    $services = collect(ArkCloudServiceCatalog::forCurrentShop()->services());

    expect($services->firstWhere('key', 'mail')['status'])->toBe('requires_cloud')
        ->and($services->firstWhere('key', 'sms')['status'])->toBe('requires_cloud')
        ->and($services->firstWhere('key', 'voice')['status'])->toBe('requires_cloud')
        ->and($services->firstWhere('key', 'dragon')['status'])->toBe('requires_cloud');
});

test('connecting box keeps service rows on requires ARK Cloud until connected', function () {
    ShopSettings::current()->persistTrusted([
        'cloud_status' => 'pairing',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_pairing_public_id' => '00000000-0000-4000-8000-000000000001',
        'cloud_pairing_code' => 'ABCD1234',
        'cloud_pairing_expires_at' => now()->addMinutes(10),
    ]);

    $connection = ArkCloudServiceCatalog::forCurrentShop()->connectionSummary();
    $mail = collect(ArkCloudServiceCatalog::forCurrentShop()->services())->firstWhere('key', 'mail');

    expect($connection['connection_label'])->toBe('Connecting')
        ->and($connection['cloud_pairing'])->toBeTrue()
        ->and($mail['status'])->toBe('requires_cloud');
});

test('connected box renders Cloud service projection not local leftovers', function () {
    ShopSettings::current()->persistTrusted([
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
        'ark_mail_from_email' => null,
    ]);

    Http::fake([
        'cloud.example.test/api/v1/status' => Http::response([
            'ok' => true,
            'services' => [
                ['key' => 'connect', 'label' => 'ARK Connect', 'status' => 'active', 'status_label' => 'Active', 'detail' => null],
                ['key' => 'mail', 'label' => 'ARK Mail', 'status' => 'needs_setup', 'status_label' => 'Needs setup', 'detail' => 'Configure Reply-To in ARK Cloud'],
                ['key' => 'sms', 'label' => 'ARK SMS', 'status' => 'not_enabled', 'status_label' => 'Not enabled', 'detail' => null],
                ['key' => 'voice', 'label' => 'ARK Voice', 'status' => 'not_enabled', 'status_label' => 'Not enabled', 'detail' => null],
                ['key' => 'dragon', 'label' => 'Dragon AI', 'status' => 'not_enabled', 'status_label' => 'Not enabled', 'detail' => null],
                ['key' => 'backup', 'label' => 'ARK Backup', 'status' => 'coming_soon', 'status_label' => 'Coming soon', 'detail' => null],
            ],
        ], 200),
    ]);

    $services = collect(ArkCloudServiceCatalog::forCurrentShop()->services());

    expect($services->firstWhere('key', 'connect')['status'])->toBe('active')
        ->and($services->firstWhere('key', 'mail')['status'])->toBe('needs_setup')
        ->and($services->firstWhere('key', 'mail')['status_label'])->toBe('Needs setup')
        ->and($services->firstWhere('key', 'sms')['status'])->toBe('not_enabled')
        ->and($services->firstWhere('key', 'backup')['status'])->toBe('coming_soon');

    Http::assertSentCount(1);
});

test('connected box shows unavailable when Cloud status cannot be loaded', function () {
    ShopSettings::current()->persistTrusted([
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_credential' => 'cloud-install-secret',
    ]);

    Http::fake([
        'cloud.example.test/*' => Http::response(['ok' => false], 503),
    ]);

    $services = ArkCloudServiceCatalog::forCurrentShop()->services();

    expect($services)->toHaveCount(1)
        ->and($services[0]['status'])->toBe('unavailable');
});

test('manage url points at external Cloud portal', function () {
    config([
        'services.ark_cloud.base_url' => 'https://cloud.arksms.com',
    ]);

    ShopSettings::current()->persistTrusted([
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.arksms.com',
        'cloud_credential' => 'secret',
    ]);

    expect(ArkCloudServiceCatalog::forCurrentShop()->manageUrl())
        ->toBe('https://cloud.arksms.com/portal');
});
