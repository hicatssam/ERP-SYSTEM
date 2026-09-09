<?php

use App\Http\Controllers\Inventory\InventoryExpiryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sprint 14 — Inventory Batch & Expiry
|--------------------------------------------------------------------------
|
| Route file واحد فقط للصلاحية.
| لا تعيد تعريف هذه المسارات داخل routes/web.php.
|
*/

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
    'can:inventory.view',
])
    ->prefix('inventory/expiry')
    ->name('inventory.expiry.')
    ->group(function (): void {

        Route::get(
            '/',
            [InventoryExpiryController::class, 'index']
        )->name('index');

        Route::get(
            '/print',
            [InventoryExpiryController::class, 'print']
        )->name('print');

        Route::post(
            '/scan',
            [InventoryExpiryController::class, 'scan']
        )
            ->name('scan')
            ->middleware('can:inventory.expiry-alerts.run');
    });
