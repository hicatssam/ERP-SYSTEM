<?php

use App\Http\Controllers\Growth\CrmController;
use App\Http\Controllers\Growth\CrmTagController;
use App\Http\Controllers\Growth\CustomerAddressController;
use App\Http\Controllers\Growth\CustomerInteractionController;
use App\Http\Controllers\Growth\DeliveryTaskController;
use App\Http\Controllers\Growth\DeliveryZoneController;
use App\Http\Controllers\Growth\LoyaltyController;
use App\Http\Middleware\EnsureModuleEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
])->group(function (): void {
    Route::middleware([EnsureModuleEnabled::class.':crm','can:crm.view'])->prefix('crm')->name('crm.')->group(function (): void {
        Route::get('/',[CrmController::class,'index'])->name('index');
        Route::get('/customers/{customer}',[CrmController::class,'show'])->name('customers.show');
        Route::patch('/customers/{customer}/profile',[CrmController::class,'updateProfile'])->name('customers.profile.update')->middleware('can:crm.manage');
        Route::post('/customers/{customer}/addresses',[CustomerAddressController::class,'store'])->name('addresses.store')->middleware('can:crm.manage');
        Route::patch('/customers/{customer}/addresses/{address}',[CustomerAddressController::class,'update'])->name('addresses.update')->middleware('can:crm.manage');
        Route::post('/customers/{customer}/addresses/{address}/default',[CustomerAddressController::class,'setDefault'])->name('addresses.default')->middleware('can:crm.manage');
        Route::delete('/customers/{customer}/addresses/{address}',[CustomerAddressController::class,'destroy'])->name('addresses.destroy')->middleware('can:crm.manage');
        Route::post('/customers/{customer}/interactions',[CustomerInteractionController::class,'store'])->name('interactions.store')->middleware('can:crm.interactions.manage');
        Route::post('/customers/{customer}/interactions/{interaction}/complete',[CustomerInteractionController::class,'complete'])->name('interactions.complete')->middleware('can:crm.interactions.manage');
        Route::get('/tags/manage',[CrmTagController::class,'index'])->name('tags.index')->middleware('can:crm.manage');
        Route::post('/tags',[CrmTagController::class,'store'])->name('tags.store')->middleware('can:crm.manage');
        Route::patch('/tags/{tag}',[CrmTagController::class,'update'])->name('tags.update')->middleware('can:crm.manage');
        Route::post('/customers/{customer}/tags',[CrmTagController::class,'sync'])->name('customers.tags.sync')->middleware('can:crm.manage');
    });

    Route::middleware([EnsureModuleEnabled::class.':loyalty','can:loyalty.view'])->prefix('loyalty')->name('loyalty.')->group(function (): void {
        Route::get('/',[LoyaltyController::class,'index'])->name('index');
        Route::post('/programs',[LoyaltyController::class,'saveProgram'])->name('programs.store')->middleware('can:loyalty.manage');
        Route::post('/customers/{customer}/adjust',[LoyaltyController::class,'adjust'])->name('customers.adjust')->middleware('can:loyalty.adjust');
        Route::post('/customers/{customer}/redeem',[LoyaltyController::class,'redeem'])->name('customers.redeem')->middleware('can:loyalty.redeem');
    });

    Route::middleware([EnsureModuleEnabled::class.':delivery','can:delivery.view'])->prefix('delivery')->name('delivery.')->group(function (): void {
        Route::get('/tasks',[DeliveryTaskController::class,'index'])->name('tasks.index');
        Route::get('/tasks/create',[DeliveryTaskController::class,'create'])->name('tasks.create')->middleware('can:delivery.create');
        Route::post('/tasks',[DeliveryTaskController::class,'store'])->name('tasks.store')->middleware('can:delivery.create');
        Route::get('/tasks/{task}',[DeliveryTaskController::class,'show'])->name('tasks.show');
        Route::post('/tasks/{task}/assign',[DeliveryTaskController::class,'assign'])->name('tasks.assign')->middleware('can:delivery.assign');
        Route::post('/tasks/{task}/status',[DeliveryTaskController::class,'updateStatus'])->name('tasks.status')->middleware('can:delivery.update_status');
        Route::get('/zones',[DeliveryZoneController::class,'index'])->name('zones.index')->middleware('can:delivery.zones.manage');
        Route::post('/zones',[DeliveryZoneController::class,'store'])->name('zones.store')->middleware('can:delivery.zones.manage');
        Route::patch('/zones/{zone}',[DeliveryZoneController::class,'update'])->name('zones.update')->middleware('can:delivery.zones.manage');
    });
});
