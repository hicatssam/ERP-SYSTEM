<?php

use App\Http\Controllers\CustomerOrdering\CustomerMenuController;
use App\Http\Controllers\Sales\OrderController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;

use Illuminate\Support\Facades\Route;

Route::prefix('menu')->name('customer-menu.')->group(function (): void {
    Route::get('track/{token}', [CustomerMenuController::class, 'track'])
        ->where('token', '[A-Za-z0-9_-]{20,80}')->name('track');

    Route::get('track/{token}/status', [CustomerMenuController::class, 'status'])
        ->where('token', '[A-Za-z0-9_-]{20,80}')->name('status');

    Route::get('{location:code}/my-orders', [CustomerMenuController::class, 'myOrders'])->name('my-orders');
    Route::get('{location:code}/tables', [CustomerMenuController::class, 'tables'])->name('tables');
    Route::get('{location:code}/payment-options', [CustomerMenuController::class, 'paymentOptions'])->name('payment-options');
    Route::get('{location:code}', [CustomerMenuController::class, 'show'])->name('show');

    Route::post('{location:code}/orders', [CustomerMenuController::class, 'store'])
        ->middleware('throttle:20,1')->name('orders.store');

           Route::get('/products', [CustomerMenuController::class, 'products'])
        ->name('products');

    Route::get('/product/{product}', [CustomerMenuController::class, 'show'])
        ->name('product.show');

    Route::get('/cart', [CartController::class, 'index'])
        ->name('cart');

    Route::get('/checkout', [CheckoutController::class, 'index'])
        ->name('checkout');
});

Route::post('orders/{order}/customer-message', [OrderController::class, 'updateCustomerMessage'])
    ->whereNumber('order')
    ->middleware(['auth', 'location.scope', 'password.changed', 'can:orders.update'])
    ->name('orders.customer-message');
