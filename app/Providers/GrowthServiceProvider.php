<?php

namespace App\Providers;

use App\Events\OrderStatusChanged;
use App\Listeners\LoyaltyOrderStatusListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class GrowthServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(OrderStatusChanged::class, LoyaltyOrderStatusListener::class);
    }
}
