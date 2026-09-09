<?php

use App\Http\Controllers\Settings\SystemCurrencyController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'location.scope',
    'password.changed',
])->prefix('settings')->name('settings.')->group(function (): void {
    Route::get(
        'currencies',
        [SystemCurrencyController::class, 'index']
    )
        ->middleware('can:system_currencies.view')
        ->name('currencies.index');

    Route::middleware('can:system_currencies.manage')->group(function (): void {
        Route::post(
            'currencies',
            [SystemCurrencyController::class, 'store']
        )->name('currencies.store');

        Route::put(
            'currencies/{currency}',
            [SystemCurrencyController::class, 'update']
        )->name('currencies.update');

        Route::patch(
            'currencies/{currency}/toggle',
            [SystemCurrencyController::class, 'toggle']
        )->name('currencies.toggle');

        Route::delete(
            'currencies/{currency}',
            [SystemCurrencyController::class, 'destroy']
        )->name('currencies.destroy');
    });
});
