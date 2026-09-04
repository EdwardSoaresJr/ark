<?php

use App\Ark\Operations\Messaging\ReviewRequestCopy;
use App\Ark\Operations\Settings\ShopSettings;

test('review request url reads Core google_reviews_url not public_surface_settings', function (): void {
    ShopSettings::current()->update([
        'google_reviews_url' => 'https://reviews.example.test/core',
        'public_surface_settings' => [
            'google_reviews_url' => 'https://reviews.example.test/legacy-website',
        ],
    ]);

    expect(ReviewRequestCopy::reviewUrl())->toBe('https://reviews.example.test/core');
});

test('review request url is empty when Core setting is unset', function (): void {
    ShopSettings::current()->update([
        'google_reviews_url' => null,
        'public_surface_settings' => [
            'google_reviews_url' => 'https://reviews.example.test/legacy-website',
        ],
    ]);

    expect(ReviewRequestCopy::reviewUrl())->toBe('');
});
