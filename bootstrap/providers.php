<?php

use App\Providers\AppServiceProvider;
use App\Providers\CrmServiceProvider;
use App\Providers\E2eServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\RateLimitServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    RateLimitServiceProvider::class,
    CrmServiceProvider::class,
    E2eServiceProvider::class,
];
