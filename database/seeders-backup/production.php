<?php

use App\Http\Controllers\Production\ProductionOrderController;
use App\Http\Controllers\Production\QualityControlController;
use App\Http\Controllers\Production\RecipeController;
use App\Http\Middleware\EnsureModuleEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
    EnsureModuleEnabled::class . ':recipes',
])
    ->prefix('recipes')
    ->name('recipes.')
    ->group(function (): void {
        Route::get('/', [RecipeController::class, 'index'])
            ->name('index')->middleware('can:recipes.view');
        Route::get('/create', [RecipeController::class, 'create'])
            ->name('create')->middleware('can:recipes.manage');
        Route::post('/', [RecipeController::class, 'store'])
            ->name('store')->middleware('can:recipes.manage');
        Route::get('/{recipe}', [RecipeController::class, 'show'])
            ->name('show')->middleware('can:recipes.view');
        Route::get('/{recipe}/edit', [RecipeController::class, 'edit'])
            ->name('edit')->middleware('can:recipes.manage');
        Route::put('/{recipe}', [RecipeController::class, 'update'])
            ->name('update')->middleware('can:recipes.manage');
        Route::post('/{recipe}/duplicate', [RecipeController::class, 'duplicate'])
            ->name('duplicate')->middleware('can:recipes.manage');
        Route::post('/{recipe}/activate', [RecipeController::class, 'activate'])
            ->name('activate')->middleware('can:recipes.activate');
        Route::post('/{recipe}/archive', [RecipeController::class, 'archive'])
            ->name('archive')->middleware('can:recipes.activate');
    });

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
    EnsureModuleEnabled::class . ':production',
])
    ->prefix('production/orders')
    ->name('production.orders.')
    ->group(function (): void {
        Route::get('/', [ProductionOrderController::class, 'index'])
            ->name('index')->middleware('can:production.view');
        Route::get('/create', [ProductionOrderController::class, 'create'])
            ->name('create')->middleware('can:production.create');
        Route::post('/', [ProductionOrderController::class, 'store'])
            ->name('store')->middleware('can:production.create');
        Route::get('/{productionOrder}', [ProductionOrderController::class, 'show'])
            ->name('show')->middleware('can:production.view');
        Route::post('/{productionOrder}/release', [ProductionOrderController::class, 'release'])
            ->name('release')->middleware('can:production.release');
        Route::post('/{productionOrder}/start', [ProductionOrderController::class, 'start'])
            ->name('start')->middleware('can:production.start');
        Route::post('/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])
            ->name('complete')->middleware('can:production.complete');
        Route::post('/{productionOrder}/cancel', [ProductionOrderController::class, 'cancel'])
            ->name('cancel')->middleware('can:production.cancel');
    });

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
    EnsureModuleEnabled::class . ':quality_control',
    'can:quality_control.view',
])
    ->prefix('quality-control')
    ->name('quality-control.')
    ->group(function (): void {
        Route::get('/', [QualityControlController::class, 'index'])->name('index');
        Route::get('/{productionOrder}', [QualityControlController::class, 'show'])->name('show');
        Route::post('/{productionOrder}/approve', [QualityControlController::class, 'approve'])
            ->name('approve')->middleware('can:quality_control.inspect');
        Route::post('/{productionOrder}/reject', [QualityControlController::class, 'reject'])
            ->name('reject')->middleware('can:quality_control.inspect');
    });
