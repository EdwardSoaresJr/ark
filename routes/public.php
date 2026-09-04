<?php

use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Illuminate\Support\Facades\Route;

SurfaceRouting::publicRoutes(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::get('/', function () {
            return redirect()->route('login');
        });
    });
});
