<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\ShopMemoryServiceProvider;
use App\Ark\Dragon\Agent\DragonAgentServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    ShopMemoryServiceProvider::class,
    DragonAgentServiceProvider::class,
];
