<?php

use App\Http\Controllers\Restaurant\CustomerOrderDisplayController;
use App\Http\Middleware\EnsureModuleEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
    EnsureModuleEnabled::class . ':kitchen',
    'can:customer_display.view',
])
    ->prefix('restaurant/customer-display')
    ->name('restaurant.customer-display.')
    ->group(function (): void {
        Route::get(
            '/',
            [CustomerOrderDisplayController::class, 'index']
        )->name('index');

        Route::get(
            '/feed',
            [CustomerOrderDisplayController::class, 'feed']
        )->name('feed');
    });
