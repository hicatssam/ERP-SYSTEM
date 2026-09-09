<?php

use App\Http\Controllers\Production\ProductionDashboardController;
use App\Http\Controllers\Production\ProductionOrderController;
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
    ->name('production.recipes.')
    ->group(function (): void {
        Route::get(
            '/',
            [RecipeController::class, 'index']
        )
            ->name('index')
            ->middleware('can:recipes.view');

        Route::get(
            '/create',
            [RecipeController::class, 'create']
        )
            ->name('create')
            ->middleware('can:recipes.create');

        Route::post(
            '/',
            [RecipeController::class, 'store']
        )
            ->name('store')
            ->middleware('can:recipes.create');

        Route::get(
            '/{recipe}',
            [RecipeController::class, 'show']
        )
            ->name('show')
            ->middleware('can:recipes.view');

        Route::get(
            '/{recipe}/edit',
            [RecipeController::class, 'edit']
        )
            ->name('edit')
            ->middleware('can:recipes.update');

        Route::put(
            '/{recipe}',
            [RecipeController::class, 'update']
        )
            ->name('update')
            ->middleware('can:recipes.update');

        Route::post(
            '/{recipe}/activate',
            [RecipeController::class, 'approve']
        )
            ->name('approve')
            ->middleware('can:recipes.approve');

        Route::post(
            '/{recipe}/revise',
            [RecipeController::class, 'newVersion']
        )
            ->name('new-version')
            ->middleware('can:recipes.manage');

        Route::post(
            '/{recipe}/archive',
            [RecipeController::class, 'archive']
        )
            ->name('archive')
            ->middleware('can:recipes.approve');

        Route::get(
            '/{recipe}/cost',
            [RecipeController::class, 'cost']
        )
            ->name('cost')
            ->middleware('can:recipes.cost.view');
    });

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
    EnsureModuleEnabled::class . ':production',
])
    ->prefix('production')
    ->name('production.')
    ->group(function (): void {
        Route::get(
            '/',
            [ProductionDashboardController::class, 'index']
        )
            ->name('dashboard')
            ->middleware('can:production.view');

        Route::get(
            '/orders',
            [ProductionOrderController::class, 'index']
        )
            ->name('orders.index')
            ->middleware('can:production.view');

        Route::get(
            '/orders/create',
            [ProductionOrderController::class, 'create']
        )
            ->name('orders.create')
            ->middleware('can:production.create');

        Route::post(
            '/orders',
            [ProductionOrderController::class, 'store']
        )
            ->name('orders.store')
            ->middleware('can:production.create');

        Route::get(
            '/orders/{productionOrder}',
            [ProductionOrderController::class, 'show']
        )
            ->name('orders.show')
            ->middleware('can:production.view');

        Route::post(
            '/orders/{productionOrder}/release',
            [ProductionOrderController::class, 'release']
        )
            ->name('orders.release')
            ->middleware('can:production.release');

        Route::post(
            '/orders/{productionOrder}/start',
            [ProductionOrderController::class, 'start']
        )
            ->name('orders.start')
            ->middleware('can:production.start');

        Route::post(
            '/orders/{productionOrder}/complete',
            [ProductionOrderController::class, 'complete']
        )
            ->name('orders.complete')
            ->middleware('can:production.complete');

        Route::post(
            '/orders/{productionOrder}/cancel',
            [ProductionOrderController::class, 'cancel']
        )
            ->name('orders.cancel')
            ->middleware('can:production.cancel');
    });
