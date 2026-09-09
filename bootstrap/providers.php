<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;




return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    App\Providers\GrowthServiceProvider::class,
    App\Providers\ClientOnboardingServiceProvider::class,
    App\Providers\ReleaseCenterServiceProvider::class,
    App\Providers\PayrollServiceProvider::class,
    App\Providers\AttendanceServiceProvider::class,
    App\Providers\PrintBrandingServiceProvider::class,

];
