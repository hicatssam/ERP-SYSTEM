<?php

use App\Http\Controllers\Procurement\ExchangeRateController;
use App\Http\Controllers\Procurement\GoodsReceiptController;
use App\Http\Controllers\Procurement\ProcurementDashboardController;
use App\Http\Controllers\Procurement\ProcurementReportController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Procurement\PurchaseReturnController;
use App\Http\Controllers\Procurement\SupplierController;
use App\Http\Controllers\Procurement\SupplierInvoiceController;
use App\Http\Controllers\Procurement\SupplierPaymentController;
use Illuminate\Support\Facades\Route;

Route::get('procurement/dashboard', [ProcurementDashboardController::class, 'index'])
    ->name('procurement.dashboard');

Route::get('procurement/reports', [ProcurementReportController::class, 'index'])
    ->name('procurement.reports.index');

Route::get('procurement/exchange-rates', [ExchangeRateController::class, 'index'])
    ->name('procurement.exchange-rates.index');

Route::post('procurement/exchange-rates', [ExchangeRateController::class, 'store'])
    ->name('procurement.exchange-rates.store');


/*
|--------------------------------------------------------------------------
| Suppliers
|--------------------------------------------------------------------------
*/


Route::get(
    'suppliers/{supplier}/statement',
    [SupplierController::class, 'statement']
)->name('suppliers.statement');

Route::get(
    'suppliers/{supplier}/statement/print',
    [SupplierController::class, 'printStatement']
)->name('suppliers.statement.print');

Route::resource('suppliers', SupplierController::class);

Route::get('suppliers/{supplier}/statement', [SupplierController::class, 'statement'])
    ->name('suppliers.statement');

Route::resource('suppliers', SupplierController::class);


/*
|--------------------------------------------------------------------------
| Purchase Orders
|--------------------------------------------------------------------------
*/

Route::post(
    'purchase-orders/{purchaseOrder}/submit',
    [PurchaseOrderController::class, 'submit']
)->name('purchase-orders.submit');

Route::post(
    'purchase-orders/{purchaseOrder}/approve',
    [PurchaseOrderController::class, 'approve']
)->name('purchase-orders.approve');

Route::post(
    'purchase-orders/{purchaseOrder}/cancel',
    [PurchaseOrderController::class, 'cancel']
)->name('purchase-orders.cancel');

Route::resource('purchase-orders', PurchaseOrderController::class)
    ->except('destroy');


/*
|--------------------------------------------------------------------------
| Goods Receipts
|--------------------------------------------------------------------------
*/

Route::post(
    'goods-receipts/{goodsReceipt}/post',
    [GoodsReceiptController::class, 'post']
)->name('goods-receipts.post');

Route::resource('goods-receipts', GoodsReceiptController::class)
    ->only([
        'index',
        'create',
        'store',
        'show',
    ]);


/*
|--------------------------------------------------------------------------
| Purchase Returns
|--------------------------------------------------------------------------
*/

Route::post(
    'purchase-returns/{purchaseReturn}/post',
    [PurchaseReturnController::class, 'post']
)->name('purchase-returns.post');

Route::post(
    'purchase-returns/{purchaseReturn}/cancel',
    [PurchaseReturnController::class, 'cancel']
)->name('purchase-returns.cancel');

Route::resource('purchase-returns', PurchaseReturnController::class)
    ->only([
        'index',
        'create',
        'store',
        'show',
    ]);


/*
|--------------------------------------------------------------------------
| Supplier Invoices
|--------------------------------------------------------------------------
*/

Route::get(
    'supplier-invoices/{supplierInvoice}/print',
    [SupplierInvoiceController::class, 'printInvoice']
)->name('supplier-invoices.print');

Route::post(
    'supplier-invoices/{supplierInvoice}/cancel',
    [SupplierInvoiceController::class, 'cancel']
)->name('supplier-invoices.cancel');

Route::resource('supplier-invoices', SupplierInvoiceController::class)
    ->only([
        'index',
        'create',
        'store',
        'show',
    ]);


/*
|--------------------------------------------------------------------------
| Supplier Payments
|--------------------------------------------------------------------------
*/

Route::resource('supplier-payments', SupplierPaymentController::class)
    ->only([
        'index',
        'create',
        'store',
    ]);
