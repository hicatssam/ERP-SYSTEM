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

    Route::get('track/{token}/invoice', [CustomerMenuController::class, 'invoice'])
        ->where('token', '[A-Za-z0-9_-]{20,80}')->name('invoice');

    // Every one of these lives under {location:code}/... so none of them
    // can ever collide with the single-segment catch-all "show" route
    // further down, regardless of registration order.
    Route::get('{location:code}/my-orders', [CustomerMenuController::class, 'myOrders'])->name('my-orders');
    Route::get('{location:code}/tables', [CustomerMenuController::class, 'tables'])->name('tables');
    Route::get('{location:code}/queue-status', [CustomerMenuController::class, 'queueStatus'])->name('queue-status');
    Route::get('{location:code}/payment-options', [CustomerMenuController::class, 'paymentOptions'])->name('payment-options');

    Route::get('{location:code}/products', [CustomerMenuController::class, 'products'])->name('products');
    Route::get('{location:code}/product/{product}', [CustomerMenuController::class, 'productShow'])
        ->whereNumber('product')->name('product.show');
    Route::get('{location:code}/favorites', [CustomerMenuController::class, 'favorites'])->name('favorites');

    Route::get('{location:code}/cart', [CartController::class, 'index'])->name('cart');
    Route::get('{location:code}/checkout', [CheckoutController::class, 'index'])->name('checkout');

    Route::post('{location:code}/orders', [CustomerMenuController::class, 'store'])
        ->middleware('throttle:20,1')->name('orders.store');

    // The single-segment catch-all must stay last in the group: it would
    // otherwise swallow every route above it (e.g. GET /menu/cart would
    // have matched here with "cart" bound as the location code).
    Route::get('{location:code}', [CustomerMenuController::class, 'show'])->name('show');
});

Route::post('orders/{order}/customer-message', [OrderController::class, 'updateCustomerMessage'])
    ->whereNumber('order')
    ->middleware(['auth', 'location.scope', 'password.changed', 'can:orders.update'])
    ->name('orders.customer-message');
