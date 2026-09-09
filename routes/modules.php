<?php

use App\Http\Controllers\Admin\BusinessProfileController;
use App\Http\Controllers\Admin\ModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
])->group(function (): void {
    Route::get('/settings/modules', [ModuleController::class, 'index'])
        ->name('modules.index');

    Route::put('/settings/modules/{module:code}', [ModuleController::class, 'update'])
        ->name('modules.update');

    Route::post('/settings/module-bundles/{bundle:code}/apply', [ModuleController::class, 'applyBundle'])
        ->name('modules.bundles.apply');

    Route::get('/settings/business-profile', [BusinessProfileController::class, 'index'])
        ->name('business-profiles.index');

    Route::post('/settings/business-profile/{businessProfile:code}/apply', [BusinessProfileController::class, 'apply'])
        ->name('business-profiles.apply');
});
