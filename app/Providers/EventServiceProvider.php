<?php

namespace App\Providers;

use App\Events\LowStockDetected;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\PaymentReceived;
use App\Events\SpecialCakeOrderTransitioned;
use App\Listeners\NotifyStaffOnLowStock;
use App\Listeners\NotifyStaffOnOrderCreated;
use App\Listeners\NotifyStaffOnOrderStatusChanged;
use App\Listeners\NotifyStaffOnPaymentReceived;
use App\Listeners\NotifyStaffOnSpecialCakeOrderTransitioned;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderCreated::class => [
            NotifyStaffOnOrderCreated::class,
        ],
        OrderStatusChanged::class => [
            NotifyStaffOnOrderStatusChanged::class,
        ],
        SpecialCakeOrderTransitioned::class => [
            NotifyStaffOnSpecialCakeOrderTransitioned::class,
        ],
        LowStockDetected::class => [
            NotifyStaffOnLowStock::class,
        ],
        PaymentReceived::class => [
            NotifyStaffOnPaymentReceived::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
