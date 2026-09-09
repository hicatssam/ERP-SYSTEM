<?php

use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\ColorController;
use App\Http\Controllers\Admin\ProductAttributeController;
use App\Http\Controllers\Admin\ProductCatalogController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\SizeController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Middleware\EnsureModuleEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','location.scope','password.changed','can:products.view'])->group(function (): void {
    Route::get('/catalog', ProductCatalogController::class)->name('catalog.index');

    Route::middleware('can:products.update')->prefix('catalog')->name('catalog.')->group(function (): void {
        Route::get('/units', [UnitController::class,'index'])->name('units.index');
        Route::post('/units', [UnitController::class,'store'])->name('units.store');
        Route::put('/units/{unit}', [UnitController::class,'update'])->name('units.update');
        Route::post('/units/{unit}/toggle', [UnitController::class,'toggle'])->name('units.toggle');

        Route::middleware(EnsureModuleEnabled::class . ':brands')->group(function (): void {
            Route::get('/brands', [BrandController::class,'index'])->name('brands.index');
            Route::post('/brands', [BrandController::class,'store'])->name('brands.store');
            Route::put('/brands/{brand}', [BrandController::class,'update'])->name('brands.update');
            Route::post('/brands/{brand}/toggle', [BrandController::class,'toggle'])->name('brands.toggle');
        });

        Route::middleware(EnsureModuleEnabled::class . ':sizes')->group(function (): void {
            Route::get('/sizes', [SizeController::class,'index'])->name('sizes.index');
            Route::post('/sizes', [SizeController::class,'store'])->name('sizes.store');
            Route::put('/sizes/{size}', [SizeController::class,'update'])->name('sizes.update');
            Route::post('/sizes/{size}/toggle', [SizeController::class,'toggle'])->name('sizes.toggle');
        });

        Route::middleware(EnsureModuleEnabled::class . ':colors')->group(function (): void {
            Route::get('/colors', [ColorController::class,'index'])->name('colors.index');
            Route::post('/colors', [ColorController::class,'store'])->name('colors.store');
            Route::put('/colors/{color}', [ColorController::class,'update'])->name('colors.update');
            Route::post('/colors/{color}/toggle', [ColorController::class,'toggle'])->name('colors.toggle');
        });

        Route::middleware(EnsureModuleEnabled::class . ':product_variants')->group(function (): void {
            Route::get('/attributes', [ProductAttributeController::class,'index'])->name('attributes.index');
            Route::post('/attributes', [ProductAttributeController::class,'store'])->name('attributes.store');
            Route::put('/attributes/{attribute}', [ProductAttributeController::class,'update'])->name('attributes.update');
            Route::post('/attributes/{attribute}/toggle', [ProductAttributeController::class,'toggle'])->name('attributes.toggle');
            Route::post('/attributes/{attribute}/values', [ProductAttributeController::class,'storeValue'])->name('attributes.values.store');
            Route::post('/attribute-values/{value}/toggle', [ProductAttributeController::class,'toggleValue'])->name('attributes.values.toggle');
        });
    });

    Route::middleware(['can:products.update', EnsureModuleEnabled::class . ':product_variants'])->group(function (): void {
        Route::get('/products/{product}/variants', [ProductVariantController::class,'index'])->name('products.variants.index');
        Route::get('/products/{product}/variants/create', [ProductVariantController::class,'create'])->name('products.variants.create');
        Route::post('/products/{product}/variants', [ProductVariantController::class,'store'])->name('products.variants.store');
        Route::get('/products/{product}/variants/{variant}/edit', [ProductVariantController::class,'edit'])->name('products.variants.edit');
        Route::put('/products/{product}/variants/{variant}', [ProductVariantController::class,'update'])->name('products.variants.update');
        Route::post('/products/{product}/variants/{variant}/toggle', [ProductVariantController::class,'toggle'])->name('products.variants.toggle');
    });
});
