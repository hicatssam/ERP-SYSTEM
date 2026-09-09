<?php

namespace App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InventoryExpiryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware([
            'web',
            'auth',
            'location.scope',
            'password.changed',
        ])->group(
            base_path('routes/inventory-expiry.php')
        );

        if ($this->app->runningInConsole()) {
            $this->app->booted(function (): void {
                $schedule = $this->app->make(Schedule::class);

                $schedule
                    ->command('inventory:check-expiry')
                    ->dailyAt('08:00')
                    ->withoutOverlapping(60);
            });
        }
    }
}
