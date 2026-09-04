<?php

use Illuminate\Support\Facades\Route;

test('root redirects to staff login without marketing homepage', function (): void {
    $this->get('/')
        ->assertRedirect(route('login'));
});

test('growth and website route names are not registered', function (): void {
    expect(Route::has('growth.opportunities.index'))->toBeFalse()
        ->and(Route::has('website.manage'))->toBeFalse()
        ->and(Route::has('website.performance'))->toBeFalse()
        ->and(Route::has('public.home'))->toBeFalse();
});

test('portal access remains registered', function (): void {
    expect(Route::has('portal.access'))->toBeTrue();
});
