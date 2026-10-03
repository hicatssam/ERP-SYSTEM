<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\LocationProductController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\StockCountController;
use App\Http\Controllers\Inventory\StockRequestController;
use App\Http\Controllers\Inventory\StockTransferController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\OrderController;
use App\Http\Controllers\Sales\SpecialCakeOrderController;
use App\Http\Controllers\Sales\ShowroomCakeRequestController;
use App\Http\Controllers\Sales\ShowroomSweetsRequestController;
use App\Http\Controllers\Finance\CustomerAccountController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\IncomingBankTransferController;
use App\Http\Controllers\Finance\CashSessionController;
use App\Http\Controllers\Finance\DailyCashReconciliationController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\FinancialPeriodController;
use App\Http\Controllers\Finance\FinancialDashboardController;
use App\Http\Controllers\Finance\AccountingLedgerController;
use App\Http\Controllers\Finance\EmployeePurchaseController;
use App\Http\Controllers\Finance\AccountingBooksController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\ReportScheduleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\SalesChannelController;
use App\Http\Controllers\Inventory\StockReceivingInvoiceController;
use App\Http\Controllers\Procurement\GoodsReceiptController;
use App\Http\Controllers\Procurement\ExchangeRateController;
use App\Http\Controllers\Procurement\ProcurementDashboardController;
use App\Http\Controllers\Procurement\ProcurementReportController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Procurement\PurchaseReturnController;
use App\Http\Controllers\Procurement\SupplierController;
use App\Http\Controllers\Procurement\SupplierInvoiceController;
use App\Http\Controllers\Procurement\SupplierPaymentController;
use App\Http\Controllers\Admin\LocationPaymentAccountController;
use App\Http\Controllers\System\HealthController;
use App\Http\Controllers\System\HomeController;
use App\Http\Controllers\Assistant\AiAssistantController;
// Public customer-menu routes live only in routes/customer-ordering.php.
// Keeping one owner prevents duplicate URIs, duplicate names and controller drift.

// ─── Health ────────────────────────────────────────────────────────────────
Route::get('/up', [HealthController::class, 'show'])
    ->name('health.up');

// ─── Auth ──────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.post');
});


Route::get('/', HomeController::class)->name('home');
Route::view('/about', 'pages.about')->name('about');
Route::view('/services', 'pages.services')->name('services');
Route::view('/details', 'pages.details')->name('details');
Route::view('/contact', 'pages.contact')->name('contact');

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ─── Authenticated ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'location.scope'])->group(function () {

    // Force password change
    Route::get('/change-password', [ChangePasswordController::class, 'show'])->name('auth.change-password');
    Route::post('/change-password', [ChangePasswordController::class, 'update'])->name('auth.change-password.post');

    // All routes below require password to be changed
    Route::middleware('password.changed')->group(function () {

        Route::get('/assistant', [AiAssistantController::class, 'index'])
            ->name('assistant.index');
        Route::post('/assistant/ask', [AiAssistantController::class, 'ask'])
            ->middleware('throttle:10,1')
            ->name('assistant.ask');

      require __DIR__ . '/procurement.php';

        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('can:dashboard.view')
            ->name('dashboard');

        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.show');
        Route::get('/profile/image', [ProfileController::class, 'image'])->name('profile.image');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/password', [ChangePasswordController::class, 'updateFromProfile'])->name('password.change');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.count');
        Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
        Route::get('/notifications/check-new', [NotificationController::class, 'checkNew'])->name('notifications.check-new');
        Route::delete('/notifications/destroy-read', [NotificationController::class, 'destroyRead'])->name('notifications.destroy-read');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

        // ─── Locations ─────────────────────────────────────────────────────
        Route::middleware('can:locations.manage')->group(function () {
            Route::resource('locations', LocationController::class);
            Route::post('locations/{location}/toggle', [LocationController::class, 'toggleStatus'])->name('locations.toggle');
        });

        // ─── Employees ─────────────────────────────────────────────────────
        Route::middleware('can:employees.manage')->group(function () {
            Route::resource('employees', EmployeeController::class);
            Route::post('employees/{employee}/toggle', [EmployeeController::class, 'toggleStatus'])->name('employees.toggle');
            Route::post('employees/{employee}/create-user', [EmployeeController::class, 'createUser'])->name('employees.create-user');
        });

        // ─── Users ─────────────────────────────────────────────────────────
        Route::middleware('can:users.manage')->group(function () {
            Route::resource('users', UserController::class)->except(['show']);
            Route::post('users/{user}/toggle', [UserController::class, 'toggleStatus'])->name('users.toggle');
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('users/{user}/assign-role', [UserController::class, 'assignRole'])->name('users.assign-role');
        });

        // ─── Roles & Permissions ───────────────────────────────────────────
        Route::middleware('can:roles.manage')->group(function () {
            Route::resource('roles', RoleController::class);
        });

        // ─── Payment Methods ────────────────────────────────────────────────
Route::middleware('can:payment_methods.manage')->group(function () {
    Route::get('payment-methods/create', [PaymentMethodController::class, 'create'])->name('payment-methods.create');
    Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::get('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'show'])->name('payment-methods.show');
    Route::get('payment-methods/{paymentMethod}/edit', [PaymentMethodController::class, 'edit'])->name('payment-methods.edit');
    Route::put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
    Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
    Route::post('payment-methods/{paymentMethod}/toggle', [PaymentMethodController::class, 'toggle'])->name('payment-methods.toggle');
});

Route::middleware('can:payment_methods.view')->group(function () {
    Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
});


Route::prefix('locations/{location}')
    ->name('locations.payment-accounts.')
    ->middleware('can:payment_methods.manage')
    ->group(function () {

        Route::get(
            '/payment-accounts',
            [LocationPaymentAccountController::class, 'index']
        )->name('index');

        Route::post(
            '/payment-accounts',
            [LocationPaymentAccountController::class, 'store']
        )->name('store');

        Route::put(
            '/payment-accounts/{account}',
            [LocationPaymentAccountController::class, 'update']
        )->name('update');

        Route::delete(
            '/payment-accounts/{account}',
            [LocationPaymentAccountController::class, 'destroy']
        )->name('destroy');
    });
    
        // ─── Categories & Products ─────────────────────────────────────────
        // Read-only catalog access.
        Route::middleware('can:products.view')->group(function () {
            Route::resource('categories', CategoryController::class)
                ->only(['index', 'show']);

            Route::resource('products', ProductController::class)
                ->only(['index', 'show']);

            Route::resource('products.location-products', LocationProductController::class)
                ->only(['index'])
                ->shallow();
        });

        // Catalog creation.
        Route::middleware('can:products.create')->group(function () {
            Route::resource('categories', CategoryController::class)
                ->only(['create', 'store']);

            Route::resource('products', ProductController::class)
                ->only(['create', 'store']);
        });

        // Catalog changes, availability and branch-specific settings.
        Route::middleware('can:products.update')->group(function () {
            Route::resource('categories', CategoryController::class)
                ->only(['edit', 'update', 'destroy']);

            Route::post(
                'categories/{category}/toggle-status',
                [CategoryController::class, 'toggleStatus']
            )->name('categories.toggle-status');

            Route::resource('products', ProductController::class)
                ->only(['edit', 'update', 'destroy']);

            Route::post(
                'products/{product}/toggle-status',
                [ProductController::class, 'toggleStatus']
            )->name('products.toggle-status');

            Route::resource('products.location-products', LocationProductController::class)
                ->only(['store', 'update', 'destroy'])
                ->shallow();
        });

        // ─── Inventory ─────────────────────────────────────────────────────
        Route::middleware('can:inventory.view')->group(function () {
            Route::get(
                'inventory',
                [InventoryController::class, 'index']
            )->name('inventory.index');

            Route::get(
                'inventory/movements',
                [StockMovementController::class, 'index']
            )->name('inventory.movements');

            Route::post(
                'inventory/adjust',
                [InventoryController::class, 'adjust']
            )
                ->middleware('can:inventory.adjust')
                ->name('inventory.adjust');

            /*
             * مهم:
             * هذا route لازم يظل بعد inventory/movements
             * ومقيد برقم حتى لا يلتقط expiry أو أي route ثابت.
             */
            Route::get(
                'inventory/{location}',
                [InventoryController::class, 'show']
            )
                ->whereNumber('location')
                ->name('inventory.show');
        });

        Route::middleware('can:inventory.count')->group(function () {
            Route::resource(
                'stock-counts',
                StockCountController::class
            );

            Route::post(
                'stock-counts/{stockCount}/approve',
                [StockCountController::class, 'approve']
            )->name('stock-counts.approve');
        });


        // ─── Stock Requests ────────────────────────────────────────────────
        Route::middleware('can:stock_requests.create')->group(function () {
            Route::resource('stock-requests', StockRequestController::class);
            Route::post('stock-requests/{stockRequest}/submit', [StockRequestController::class, 'submit'])->name('stock-requests.submit');
            Route::post('stock-requests/{stockRequest}/review', [StockRequestController::class, 'review'])->name('stock-requests.review')->middleware('can:stock_requests.review');
        });

        // ─── Stock Transfers ───────────────────────────────────────────────
        Route::middleware('can:stock_transfers.view')->group(function () {
            Route::resource('stock-transfers', StockTransferController::class);
            Route::post('stock-transfers/{stockTransfer}/dispatch', [StockTransferController::class, 'dispatch'])
                ->name('stock-transfers.dispatch')
                ->middleware('can:stock_transfers.dispatch');
            Route::post('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])
                ->name('stock-transfers.receive')
                ->middleware('can:stock_transfers.receive');
        });

        // ─── Branch Receiving Invoices ─────────────────────────────────────
        Route::middleware('can:stock_receiving_invoices.view')->group(function () {
            Route::get('stock-receiving-invoices/{stockReceivingInvoice}', [StockReceivingInvoiceController::class, 'show'])->name('stock-receiving-invoices.show');
            Route::get('stock-receiving-invoices/{stockReceivingInvoice}/print', [StockReceivingInvoiceController::class, 'print'])->name('stock-receiving-invoices.print');
            Route::get('stock-receiving-invoices/{stockReceivingInvoice}/pdf', [StockReceivingInvoiceController::class, 'pdf'])->name('stock-receiving-invoices.pdf');
        });

        // ─── Customers ─────────────────────────────────────────────────────
       // ─── Customers ───────────────────────────────────────────────
Route::middleware('can:customers.view')->group(function () {

    // كشف حساب العميل
    Route::get(
        'customers/{customer}/statement',
        [CustomerAccountController::class, 'statement']
    )->name('customers.statement');

    // تحميل كشف الحساب PDF
    Route::get(
        'customers/{customer}/statement/pdf',
        [CustomerAccountController::class, 'pdf']
    )->name('customers.statement.pdf');

    // تسجيل دفعة على حساب العميل
    Route::post(
        'customers/{customer}/account-payments',
        [CustomerAccountController::class, 'storePayment']
    )->name('customers.account-payments.store')
        ->middleware('can:payments.record');

    // تأكيد / رفض دفعة حساب العميل
    Route::post(
        'customers/{customer}/account-payments/{customerPayment}/verify',
        [CustomerAccountController::class, 'verifyPayment']
    )->name('customers.account-payments.verify')
        ->middleware('can:payments.verify');

    // CRUD العملاء
    Route::resource(
        'customers',
        CustomerController::class
    );
});
        // ─── Orders ────────────────────────────────────────────────────────
        // ─── Orders ────────────────────────────────────────────────────────
        Route::middleware('can:orders.view')->group(function (): void {
            Route::get('/orders/live/list', [OrderController::class, 'liveList'])
                ->name('orders.live-list');
            Route::get('orders', [OrderController::class, 'index'])
                ->name('orders.index');
            Route::get('orders/{order}', [OrderController::class, 'show'])
                ->whereNumber('order')
                ->name('orders.show');
        });

        Route::middleware('can:orders.create')->group(function (): void {
            Route::get('orders/create', [OrderController::class, 'create'])
                ->name('orders.create');
            Route::post('orders', [OrderController::class, 'store'])
                ->name('orders.store');
        });

        Route::middleware('can:orders.update')->group(function (): void {
            Route::get('orders/{order}/edit', [OrderController::class, 'edit'])
                ->whereNumber('order')
                ->name('orders.edit');
            Route::match(['put', 'patch'], 'orders/{order}', [OrderController::class, 'update'])
                ->whereNumber('order')
                ->name('orders.update');
        });

        Route::delete('orders/{order}', [OrderController::class, 'destroy'])
            ->whereNumber('order')
            ->middleware('can:orders.delete')
            ->name('orders.destroy');

        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])
            ->whereNumber('order')
            ->middleware('can:orders.confirm')
            ->name('orders.confirm');

        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
            ->whereNumber('order')
            ->middleware('can:orders.cancel')
            ->name('orders.cancel');

        Route::post('orders/{order}/complete', [OrderController::class, 'complete'])
            ->whereNumber('order')
            ->middleware('can:orders.complete')
            ->name('orders.complete');

// ─── Special Cake Orders ─────────────────────────────────────────
// العرض منفصل عن الإنشاء حتى يستطيع المصنع وموظفو خط الإنتاج فتح الطلبات
// حسب صلاحياتهم بدون الحاجة إلى صلاحية إنشاء طلب كيك.
Route::get('cake-orders', [SpecialCakeOrderController::class, 'index'])
    ->name('cake-orders.index')
    ->middleware('can:cake_orders.view');

Route::get('cake-orders/create', [SpecialCakeOrderController::class, 'create'])
    ->name('cake-orders.create')
    ->middleware('can:cake_orders.create');

Route::post('cake-orders', [SpecialCakeOrderController::class, 'store'])
    ->name('cake-orders.store')
    ->middleware('can:cake_orders.create');

Route::post(
    'cake-orders/customers/quick',
    [SpecialCakeOrderController::class, 'quickStoreCustomer']
)
    ->name('cake-orders.customers.quick-store')
    ->middleware('can:cake_orders.create');

Route::get('cake-orders/{cakeOrder}', [SpecialCakeOrderController::class, 'show'])
    ->name('cake-orders.show')
    ->middleware('can:cake_orders.view');

Route::get('cake-orders/{cakeOrder}/edit', [SpecialCakeOrderController::class, 'edit'])
    ->name('cake-orders.edit')
    ->middleware('can:cake_orders.edit');

Route::match(['put', 'patch'], 'cake-orders/{cakeOrder}', [SpecialCakeOrderController::class, 'update'])
    ->name('cake-orders.update')
    ->middleware('can:cake_orders.edit');

Route::delete('cake-orders/{cakeOrder}', [SpecialCakeOrderController::class, 'destroy'])
    ->name('cake-orders.destroy')
    ->middleware('can:cake_orders.delete');

// الصلاحية الدقيقة لتغيير الحالة يتم فحصها داخل SpecialCakeStatusTransitionService
// حسب الخطوة: مراجعة، قبول، تحضير، تزيين، جودة، إرسال، استلام، إكمال...
Route::post(
    'cake-orders/{cakeOrder}/transition',
    [SpecialCakeOrderController::class, 'transition']
)->name('cake-orders.transition')
    ->middleware('can:cake_orders.view');

Route::post(
    'cake-orders/{cakeOrder}/comment',
    [SpecialCakeOrderController::class, 'addComment']
)->name('cake-orders.comment')
    ->middleware('can:cake_orders.view');

Route::post(
    'cake-orders/{cakeOrder}/attachment',
    [SpecialCakeOrderController::class, 'addAttachment']
)->name('cake-orders.attachment')
    ->middleware('can:cake_orders.view');
        // ─── Showroom Cake Requests (طلبات كيك الفروع) ─────────────────────
        Route::middleware('can:showroom_cake_requests.view')->group(function () {
            Route::resource(
                'showroom-cake-requests',
                ShowroomCakeRequestController::class
            )->only(['index', 'show']);
        });

        Route::middleware('can:showroom_cake_requests.create')->group(function () {
            Route::resource(
                'showroom-cake-requests',
                ShowroomCakeRequestController::class
            )->only(['create', 'store']);

            Route::patch(
                'showroom-cake-requests/{showroomCakeRequest}/items/{item}/reservation',
                [ShowroomCakeRequestController::class, 'updateItemReservation']
            )->name('showroom-cake-requests.items.reservation');
        });

        Route::patch(
            'showroom-cake-requests/{showroomCakeRequest}/status',
            [ShowroomCakeRequestController::class, 'updateStatus']
        )
            ->name('showroom-cake-requests.status')
            ->middleware('can:showroom_cake_requests.update_status');

        Route::delete(
            'showroom-cake-requests/{showroomCakeRequest}',
            [ShowroomCakeRequestController::class, 'destroy']
        )
            ->name('showroom-cake-requests.destroy')
            ->middleware('can:showroom_cake_requests.delete');

        // ─── Showroom Sweets Requests (طلبات حلويات الفروع) ───────────────
        Route::get('showroom-sweets-requests', [ShowroomSweetsRequestController::class, 'index'])
            ->name('showroom-sweets-requests.index')
            ->middleware('can:showroom_sweets_requests.view');

        Route::get('showroom-sweets-requests/create', [ShowroomSweetsRequestController::class, 'create'])
            ->name('showroom-sweets-requests.create')
            ->middleware('can:showroom_sweets_requests.create');

        Route::get('showroom-sweets-requests/products', [ShowroomSweetsRequestController::class, 'products'])
            ->name('showroom-sweets-requests.products')
            ->middleware('can:showroom_sweets_requests.create');

        Route::post('showroom-sweets-requests', [ShowroomSweetsRequestController::class, 'store'])
            ->name('showroom-sweets-requests.store')
            ->middleware('can:showroom_sweets_requests.create');

        Route::get('showroom-sweets-requests/{showroomSweetsRequest}', [ShowroomSweetsRequestController::class, 'show'])
            ->name('showroom-sweets-requests.show')
            ->middleware('can:showroom_sweets_requests.view');

        Route::patch('showroom-sweets-requests/{showroomSweetsRequest}/status', [ShowroomSweetsRequestController::class, 'updateStatus'])
            ->name('showroom-sweets-requests.status')
            ->middleware('can:showroom_sweets_requests.view');

        Route::patch('showroom-sweets-requests/{showroomSweetsRequest}/cancel', [ShowroomSweetsRequestController::class, 'cancel'])
            ->name('showroom-sweets-requests.cancel')
            ->middleware('can:showroom_sweets_requests.view');

        Route::patch(
            'showroom-sweets-requests/{showroomSweetsRequest}/items/{item}/reservation',
            [ShowroomSweetsRequestController::class, 'updateItemReservation']
        )
            ->name('showroom-sweets-requests.items.reservation')
            ->middleware('can:showroom_sweets_requests.view');

        Route::delete('showroom-sweets-requests/{showroomSweetsRequest}', [ShowroomSweetsRequestController::class, 'destroy'])
            ->name('showroom-sweets-requests.destroy')
            ->middleware('can:showroom_sweets_requests.delete');

        // ─── Payments ──────────────────────────────────────────────────────
        Route::get('payments', [PaymentController::class, 'index'])
            ->name('payments.index');

        Route::get('payments/bank-sales', [PaymentController::class, 'bankSales'])
            ->name('payments.bank-sales');

        Route::get('incoming-bank-transfers', [IncomingBankTransferController::class, 'index'])
            ->name('incoming-bank-transfers.index');

        Route::get('incoming-bank-transfers/export/xlsx', [IncomingBankTransferController::class, 'exportXlsx'])
            ->name('incoming-bank-transfers.export.xlsx');

        Route::get('incoming-bank-transfers/export/pdf', [IncomingBankTransferController::class, 'exportPdf'])
            ->name('incoming-bank-transfers.export.pdf');

        Route::post('incoming-bank-transfers', [IncomingBankTransferController::class, 'store'])
            ->middleware('can:payments.record')
            ->name('incoming-bank-transfers.store');

        Route::get('incoming-bank-transfers/{incomingBankTransfer}/proof', [IncomingBankTransferController::class, 'proof'])
            ->name('incoming-bank-transfers.proof');

        Route::post('incoming-bank-transfers/{incomingBankTransfer}/verify', [IncomingBankTransferController::class, 'verify'])
            ->middleware('can:payments.verify')
            ->name('incoming-bank-transfers.verify');

        Route::get('payments/{payment}/proof', [PaymentController::class, 'proof'])
            ->name('payments.proof');

        Route::post('payments', [PaymentController::class, 'store'])
            ->middleware('can:payments.record')
            ->name('payments.store');

        Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])
            ->middleware('can:payments.verify')
            ->name('payments.verify');

        Route::post('payments/{payment}/correct', [PaymentController::class, 'correct'])
            ->middleware('can:payments.correct')
            ->name('payments.correct');

        Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])
            ->middleware('can:payments.refund')
            ->name('payments.refund');

        // ─── Cash Sessions ─────────────────────────────────────────────────
        Route::middleware('can:cash_sessions.manage')->group(function () {
            Route::get('cash-sessions', [CashSessionController::class, 'index'])->name('cash-sessions.index');
            Route::post('cash-sessions', [CashSessionController::class, 'open'])->name('cash-sessions.open');
            Route::post('cash-sessions/{cashSession}/close', [CashSessionController::class, 'close'])->name('cash-sessions.close');
        });

        // Branch treasury reconciliation is separate from an employee's cashier shift.
        Route::get('financial/daily-cash', [DailyCashReconciliationController::class, 'index'])->name('daily-cash.index');
        Route::post('financial/daily-cash/movements', [DailyCashReconciliationController::class, 'storeMovement'])->name('daily-cash.movements.store');
        Route::post('financial/daily-cash/movements/{movement}/void', [DailyCashReconciliationController::class, 'voidMovement'])->name('daily-cash.movements.void');
        Route::post('financial/daily-cash/close', [DailyCashReconciliationController::class, 'close'])->name('daily-cash.close');
        Route::post('financial/daily-cash/expenses/{expense}/classify', [ExpenseController::class, 'classifyPaymentMethod'])->name('daily-cash.expenses.classify');

        // ─── Invoices ──────────────────────────────────────────────────────
        Route::middleware('can:invoices.view')->group(function () {
            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
            Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
            Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
            Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel')->middleware('can:invoices.cancel');
        });

        // ─── Financial ─────────────────────────────────────────────────────
        Route::middleware('can:financial.dashboard.view')->group(function () {
            Route::get('financial/dashboard', [FinancialDashboardController::class, 'index'])->name('financial.dashboard');
            Route::get('financial/dashboard/{location}', [FinancialDashboardController::class, 'branch'])->name('financial.dashboard.branch');
        });

        Route::get('financial/accounting/ledger', [AccountingLedgerController::class, 'index'])
            ->middleware('can:financial.reports.view')
            ->name('accounting.ledger.index');

        Route::prefix('financial/accounting/books')->name('accounting.books.')->group(function () {
            Route::get('/accounts', [AccountingBooksController::class, 'accounts'])->middleware('can:accounting.books.view')->name('accounts');
            Route::post('/accounts', [AccountingBooksController::class, 'storeAccount'])->middleware('can:accounting.accounts.manage')->name('accounts.store');
            Route::get('/vouchers', [AccountingBooksController::class, 'vouchers'])->middleware('can:accounting.books.view')->name('vouchers');
            Route::get('/vouchers/create', [AccountingBooksController::class, 'createVoucher'])->middleware('can:accounting.vouchers.create')->name('vouchers.create');
            Route::post('/vouchers', [AccountingBooksController::class, 'storeVoucher'])->middleware('can:accounting.vouchers.create')->name('vouchers.store');
            Route::get('/vouchers/{voucher}/proof', [AccountingBooksController::class, 'voucherProof'])->middleware('can:accounting.books.view')->name('vouchers.proof');
            Route::post('/vouchers/{voucher}/post', [AccountingBooksController::class, 'postVoucher'])->middleware('can:accounting.vouchers.post')->name('vouchers.post');
            Route::post('/vouchers/{voucher}/cancel', [AccountingBooksController::class, 'cancelVoucher'])->middleware('can:accounting.vouchers.create')->name('vouchers.cancel');
            Route::post('/vouchers/{voucher}/reverse', [AccountingBooksController::class, 'reverseVoucher'])->middleware('can:accounting.vouchers.reverse')->name('vouchers.reverse');
            Route::get('/vouchers/{voucher}', [AccountingBooksController::class, 'showVoucher'])->middleware('can:accounting.books.view')->name('vouchers.show');
            Route::get('/journals', [AccountingBooksController::class, 'journals'])->middleware('can:accounting.books.view')->name('journals');
            Route::get('/journals/create', [AccountingBooksController::class, 'createJournal'])->middleware('can:accounting.journals.post')->name('journals.create');
            Route::post('/journals', [AccountingBooksController::class, 'storeJournal'])->middleware('can:accounting.journals.post')->name('journals.store');
            Route::post('/journals/{journal}/reverse', [AccountingBooksController::class, 'reverseJournal'])->middleware('can:accounting.journals.post')->name('journals.reverse');
            Route::get('/journals/{journal}', [AccountingBooksController::class, 'showJournal'])->middleware('can:accounting.books.view')->name('journals.show');
            Route::get('/statements', [AccountingBooksController::class, 'statements'])->middleware('can:accounting.reports.view')->name('statements');
        });

        Route::prefix('financial/accounting/employee-purchases')->name('accounting.employee-purchases.')->group(function () {
            Route::get('/', [EmployeePurchaseController::class, 'index'])->middleware('can:accounting.employee_accounts.view')->name('index');
            Route::get('/create', [EmployeePurchaseController::class, 'create'])->middleware('can:accounting.employee_accounts.create')->name('create');
            Route::get('/employees', [EmployeePurchaseController::class, 'employees'])->middleware('can:accounting.employee_accounts.create')->name('employees');
            Route::get('/products', [EmployeePurchaseController::class, 'products'])->middleware('can:accounting.employee_accounts.create')->name('products');
            Route::post('/', [EmployeePurchaseController::class, 'store'])->middleware('can:accounting.employee_accounts.create')->name('store');
            Route::get('/receipts/{receipt}/proof', [EmployeePurchaseController::class, 'proof'])->middleware('can:accounting.employee_accounts.view')->name('receipts.proof');
            Route::post('/receipts/{receipt}/verify', [EmployeePurchaseController::class, 'verify'])->middleware('can:accounting.employee_accounts.verify')->name('receipts.verify');
            Route::post('/receipts/{receipt}/reject', [EmployeePurchaseController::class, 'reject'])->middleware('can:accounting.employee_accounts.verify')->name('receipts.reject');
            Route::get('/{purchase}', [EmployeePurchaseController::class, 'show'])->middleware('can:accounting.employee_accounts.view')->name('show');
            Route::post('/{purchase}/receipts', [EmployeePurchaseController::class, 'receive'])->middleware('can:accounting.employee_accounts.receive')->name('receipts.store');
        });

      Route::middleware('can:financial.periods.view')->group(function () {

    Route::resource(
        'financial-periods',
        FinancialPeriodController::class
    );

    Route::post(
        'financial-periods/{financialPeriod}/open',
        [FinancialPeriodController::class, 'open']
    )
        ->name('financial-periods.open')
        ->middleware('can:financial.periods.open');

    Route::post(
        'financial-periods/{financialPeriod}/close',
        [FinancialPeriodController::class, 'close']
    )
        ->name('financial-periods.close')
        ->middleware('can:financial.periods.close');
});
        // ─── Reports ───────────────────────────────────────────────────────
        Route::middleware('can:reports.view')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])
                ->name('reports.index');
            Route::get('reports/{type}', [ReportController::class, 'show'])
                ->name('reports.show');
            Route::get('reports/{type}/count', [ReportController::class, 'count'])
                ->name('reports.count');
        });

        Route::middleware('can:reports.export')->group(function () {
            Route::get(
                'reports/{type}/export/pdf',
                [ReportController::class, 'exportPdf']
            )->name('reports.export.pdf');

            Route::get(
                'reports/{type}/export/xlsx',
                [ReportController::class, 'exportXlsx']
            )->name('reports.export.xlsx');

            Route::get(
                'reports/{type}/print',
                [ReportController::class, 'printReport']
            )->name('reports.print');
        });

        // ─── Report Schedules ──────────────────────────────────────────────
        // Scheduling and emailing reports is an export action, not read-only access.
        Route::middleware('can:reports.export')->group(function () {
            Route::resource('report-schedules', ReportScheduleController::class)
                ->except(['show']);
            Route::post('report-schedules/{reportSchedule}/toggle', [ReportScheduleController::class, 'toggleActive'])
                ->name('report-schedules.toggle');
            Route::post('report-schedules/{reportSchedule}/send-now', [ReportScheduleController::class, 'sendNow'])
                ->name('report-schedules.send-now');
            Route::post('report-schedules/test-send', [ReportScheduleController::class, 'testSend'])
                ->name('report-schedules.test-send');
        });

        // ─── Settings ──────────────────────────────────────────────────────
        Route::middleware('can:settings.manage')->group(function () {
            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
        });
    });
});




Route::middleware(['auth', 'location.scope', 'password.changed'])
    ->prefix('settings')
    ->name('settings.')
    ->group(function (): void {
        Route::get('sales-channels/report', [SalesChannelController::class, 'report'])
            ->name('sales-channels.report')
            ->middleware('can:sales_channels.reports');

        Route::post('sales-channels/{salesChannel}/toggle-status', [SalesChannelController::class, 'toggleStatus'])
            ->name('sales-channels.toggle-status')
            ->middleware('can:sales_channels.activate');

        Route::resource('sales-channels', SalesChannelController::class)
            ->parameters(['sales-channels' => 'salesChannel']);
    });



    
    // ─── Branch Chat Module ───────────────────────────────────────────────
require __DIR__ . '/chat.php';
require __DIR__ . '/modules.php';
require __DIR__ . '/product-catalog.php';
require __DIR__ . '/restaurant.php';
require __DIR__ . '/kitchen.php';
require __DIR__ . '/production.php';
require __DIR__ . '/costing.php';
require __DIR__ . '/payroll.php';
require __DIR__ . '/customer-display.php';
require __DIR__ . '/customer-ordering.php';
require __DIR__ . '/growth.php';
require __DIR__ . '/onboarding.php';
require __DIR__ . '/release-center.php';
require __DIR__ . '/attendance.php';
require __DIR__ . '/print-branding.php';
require __DIR__ . '/inventory-expiry.php';
require __DIR__ . '/pwa.php';
