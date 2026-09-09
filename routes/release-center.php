<?php

use App\Http\Controllers\Admin\ReleaseCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'location.scope',
    'password.changed',
    'can:settings.manage',
])->prefix('settings/release-center')
    ->name('release-center.')
    ->group(function (): void {
        Route::get(
            '/',
            [ReleaseCenterController::class, 'index']
        )->name('index');

        Route::get(
            '/export',
            [ReleaseCenterController::class, 'export']
        )->name('export');
    });
