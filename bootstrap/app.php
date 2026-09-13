<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware([
                'web',
                'auth',
                'location.scope',
                'password.changed',
            ])->group(base_path('routes/payment-proof-ai.php'));
        },
    )
    ->withSchedule(function (Schedule $schedule) {
        // Check every hour for scheduled reports that are due.
        // withoutOverlapping() prevents a second invocation starting before the
        // first finishes, guarding against slow exports under a busy cron.
        $schedule->command('reports:send-scheduled')
            ->hourly()
            ->withoutOverlapping();

        // Run the registered expiry command once per day. Keeping the schedule
        // here gives Laravel 12 one canonical scheduling source.
        $schedule->command('inventory:check-expiry')
            ->dailyAt('08:00')
            ->withoutOverlapping(60)
            ->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
            'location.scope'   => \App\Http\Middleware\CheckLocationScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
