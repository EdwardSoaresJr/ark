<?php

test('public core has ArkVoiceClient and no Twilio Voice SDK adapter', function () {
    expect(class_exists(\App\Ark\Voice\ArkVoiceClient::class))->toBeTrue()
        ->and(file_exists(base_path('app/Ark/Voice/ArkVoiceClient.php')))->toBeTrue()
        ->and(class_exists('Twilio\\Jwt\\AccessToken'))->toBeFalse()
        ->and(file_exists(base_path('app/Ark/Operations/Telephony/MobileVoice/TwilioMobileVoiceTransport.php')))->toBeFalse();
});

test('ark voice client mirrors texting client shape', function () {
    $client = app(\App\Ark\Voice\ArkVoiceClient::class);
    expect(method_exists($client, 'isConfigured'))->toBeTrue()
        ->and(method_exists($client, 'issueSession'))->toBeTrue();
});
