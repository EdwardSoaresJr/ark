<?php

use App\Ark\Platform\Http\FabricIngressController;
use App\Ark\Platform\Http\VerifyPlatformFabricSignature;
use Illuminate\Support\Facades\Route;

/*
| Cloud → Core fabric ingress (signed, no staff session).
| CSRF: bootstrap/app.php excepts webhooks/*.
*/

Route::post('/webhooks/cloud/fabric/events', FabricIngressController::class)
    ->middleware(VerifyPlatformFabricSignature::class)
    ->name('webhooks.cloud.fabric.events');
