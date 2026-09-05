<?php

use App\Ark\Cloud\Http\FabricIngressController;
use App\Ark\Cloud\Http\VerifyCloudFabricSignature;
use Illuminate\Support\Facades\Route;

/*
| Cloud → Core fabric ingress (signed, no staff session).
| CSRF: bootstrap/app.php excepts webhooks/*.
*/

Route::post('/webhooks/cloud/fabric/events', FabricIngressController::class)
    ->middleware(VerifyCloudFabricSignature::class)
    ->name('webhooks.cloud.fabric.events');
