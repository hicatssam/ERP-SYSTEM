<?php

use App\Http\Controllers\Admin\ClientOnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'location.scope',
    'password.changed',
    'can:settings.manage',
])->prefix('settings/onboarding')->name('onboarding.')->group(function (): void {
    Route::get('/', [ClientOnboardingController::class, 'index'])
        ->name('index');

    Route::post('/start', [ClientOnboardingController::class, 'start'])
        ->name('start');

    Route::patch('/{run}/step/{step}', [ClientOnboardingController::class, 'saveStep'])
        ->whereNumber('step')
        ->name('step.save');

    Route::post('/{run}/apply', [ClientOnboardingController::class, 'apply'])
        ->name('apply');

    Route::delete('/{run}', [ClientOnboardingController::class, 'destroy'])
        ->name('destroy');
});
