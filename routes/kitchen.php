<?php

use App\Http\Controllers\Kitchen\KdsController;
use App\Http\Controllers\Kitchen\KitchenStationController;
use App\Http\Controllers\Kitchen\KitchenTicketController;
use App\Http\Middleware\EnsureModuleEnabled;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kitchen
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
    EnsureModuleEnabled::class . ':kitchen',
])
    ->prefix('kitchen')
    ->name('kitchen.')
    ->group(function (): void {

        /*
        |--------------------------------------------------------------------------
        | Tickets
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/tickets',
            [KitchenTicketController::class, 'index']
        )
            ->name('tickets.index')
            ->middleware('can:kitchen.view');

        Route::get(
            '/tickets/{ticket}',
            [KitchenTicketController::class, 'show']
        )
            ->name('tickets.show')
            ->middleware('can:kitchen.view');

        Route::post(
            '/tickets/{ticket}/start',
            [KitchenTicketController::class, 'start']
        )
            ->name('tickets.start')
            ->middleware('can:kitchen.ticket.start');

        Route::post(
            '/tickets/{ticket}/ready',
            [KitchenTicketController::class, 'ready']
        )
            ->name('tickets.ready')
            ->middleware('can:kitchen.ticket.ready');

        Route::post(
            '/tickets/{ticket}/serve',
            [KitchenTicketController::class, 'serve']
        )
            ->name('tickets.serve')
            ->middleware('can:kitchen.ticket.serve');

        Route::post(
            '/tickets/{ticket}/priority',
            [KitchenTicketController::class, 'priority']
        )
            ->name('tickets.priority')
            ->middleware('can:kitchen.ticket.priority');

        /*
        |--------------------------------------------------------------------------
        | Stations
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/stations',
            [KitchenStationController::class, 'index']
        )
            ->name('stations.index')
            ->middleware('can:kitchen.stations.manage');

        Route::post(
            '/stations',
            [KitchenStationController::class, 'store']
        )
            ->name('stations.store')
            ->middleware('can:kitchen.stations.manage');

        Route::put(
            '/stations/{station}',
            [KitchenStationController::class, 'update']
        )
            ->name('stations.update')
            ->middleware('can:kitchen.stations.manage');
    });




Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
    EnsureModuleEnabled::class . ':kitchen',
    EnsureModuleEnabled::class . ':kds',
    'can:kds.view',
])
    ->prefix('kds')
    ->name('kds.')
    ->group(function (): void {

        Route::get(
            '/',
            [KdsController::class, 'index']
        )->name('index');

        Route::get(
            '/feed',
            [KdsController::class, 'feed']
        )->name('feed');
    });