<?php

use App\Ark\Install\InstallationState;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    $_ENV['SURFACE_DOMAINS_ENABLED'] = 'true';
    $_ENV['APP_DOMAIN'] = 'app.demo-auto.test';
    $_ENV['PORTAL_DOMAIN'] = 'portal.demo-auto.test';
    $_ENV['PUBLIC_DOMAIN'] = 'demo-auto.test';
    $_ENV['LEARN_DOMAIN'] = 'learn.demo-auto.test';
    $_ENV['APP_URL'] = 'https://app.demo-auto.test';
    $_ENV['BOOKSTACK_CUTOVER'] = 'false';

    putenv('SURFACE_DOMAINS_ENABLED=true');
    putenv('APP_DOMAIN=app.demo-auto.test');
    putenv('PORTAL_DOMAIN=portal.demo-auto.test');
    putenv('PUBLIC_DOMAIN=demo-auto.test');
    putenv('LEARN_DOMAIN=learn.demo-auto.test');
    putenv('APP_URL=https://app.demo-auto.test');
    putenv('BOOKSTACK_CUTOVER=false');

    $this->refreshApplication();
    $this->withoutVite();

    RefreshDatabaseState::$migrated = false;
    RefreshDatabaseState::$lazilyRefreshed = false;
    RefreshDatabaseState::$inMemoryConnections = [];
    $this->refreshDatabase();

    InstallationState::markInstalled();
    ShopSettings::forgetCurrent();

    config([
        'app.url' => 'https://app.demo-auto.test',
        'bookstack.cutover' => false,
    ]);

    $request = Request::create('https://app.demo-auto.test/', 'GET', server: [
        'HTTP_HOST' => 'app.demo-auto.test',
        'SERVER_NAME' => 'app.demo-auto.test',
        'SERVER_PORT' => '443',
        'HTTPS' => 'on',
    ]);
    $this->app->instance('request', $request);
    URL::setRequest($request);
    URL::forceRootUrl('https://app.demo-auto.test');
    URL::forceScheme('https');
});

test('portal estimate links use the customer apex host when public surface is enabled', function () {
    $url = route('portal.estimates.show', ['token' => str_repeat('a', 64)]);

    expect($url)->toStartWith('https://demo-auto.test/');
});

test('staff login uses the app host when surface domains are enabled', function () {
    expect(route('login'))->toBe('https://app.demo-auto.test/app/login');
});

test('portal paths on the app host redirect to the customer apex host', function () {
    $this->get('http://app.demo-auto.test/portal/access')
        ->assertRedirect('https://demo-auto.test/portal/access');
});

test('legacy portal subdomain redirects to the customer apex host', function () {
    $this->get('http://portal.demo-auto.test/portal/access')
        ->assertRedirect('https://demo-auto.test/portal/access');
});

test('portal access is served on the customer apex host', function () {
    $this->get('http://demo-auto.test/portal/access')
        ->assertOk();
});

test('learn host redirects to staff learn on the app host', function () {
    $this->get('http://learn.demo-auto.test/')
        ->assertRedirect('https://app.demo-auto.test/app/learn');
});

test('app host root redirects guests to staff login', function () {
    $this->get('http://app.demo-auto.test/')
        ->assertRedirect(route('login'));
});

test('surface routing helper reports enabled state', function () {
    expect(SurfaceRouting::enabled())->toBeTrue()
        ->and(SurfaceRouting::portalOnPublicHost())->toBeTrue()
        ->and(SurfaceRouting::customerHost())->toBe('demo-auto.test');
});
