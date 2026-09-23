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
use App\Http\Controllers\Finance\CashSessionController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\FinancialPeriodController;
use App\Http\Controllers\Finance\FinancialDashboardController;
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
// Public customer-menu routes live only in routes/customer-ordering.php.
// Keeping one owner prevents duplicate URIs, duplicate names and controller drift.

// ─── Auth ──────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.post');
});


Route::view('/', 'pages.home')->name('home');
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

      require __DIR__ . '/procurement.php';

        Route::get('/', fn() => redirect()->route('dashboard'));
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('can:dashboard.view')
            ->name('dashboard');

        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.show');
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
    Route::put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
    Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
    Route::post('payment-methods/{paymentMethod}/toggle', [PaymentMethodController::class, 'toggle'])->name('payment-methods.toggle');
});

Route::middleware('can:payment_methods.view')->group(function () {
    Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
    Route::get('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'show'])->name('payment-methods.show');
    Route::get('payment-methods/{paymentMethod}/edit', [PaymentMethodController::class, 'edit'])->name('payment-methods.edit');
});


Route::prefix('locations/{location}')
    ->name('locations.payment-accounts.')
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
    
        // ─── Categories ────────────────────────────────────────────────────
        Route::middleware('can:products.view')->group(function () {
            Route::resource('categories', CategoryController::class);
            Route::post('categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');
        });

        // ─── Products ──────────────────────────────────────────────────────
        Route::middleware('can:products.view')->group(function () {
            Route::resource('products', ProductController::class);
            Route::post('products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');
            Route::resource('products.location-products', LocationProductController::class)
                ->only(['index', 'store', 'update', 'destroy'])
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
        // ─── Showroom Cake Requests (طلبات المعرض) ─────────────────────────
        Route::middleware('can:showroom_cake_requests.view')->group(function () {
            Route::resource('showroom-cake-requests', ShowroomCakeRequestController::class)
                ->except(['edit', 'update']);

            Route::patch('showroom-cake-requests/{showroomCakeRequest}/status', [ShowroomCakeRequestController::class, 'updateStatus'])
                ->name('showroom-cake-requests.status')
                ->middleware('can:showroom_cake_requests.update_status');
        });

        // ─── Showroom Sweets Requests (طلبات حلويات الفروع) ───────────────
        Route::get('showroom-sweets-requests', [ShowroomSweetsRequestController::class, 'index'])
            ->name('showroom-sweets-requests.index')
            ->middleware('can:showroom_sweets_requests.view');

        Route::get('showroom-sweets-requests/create', [ShowroomSweetsRequestController::class, 'create'])
            ->name('showroom-sweets-requests.create')
            ->middleware('can:showroom_sweets_requests.create');

        Route::post('showroom-sweets-requests', [ShowroomSweetsRequestController::class, 'store'])
            ->name('showroom-sweets-requests.store')
            ->middleware('can:showroom_sweets_requests.create');

        Route::get('showroom-sweets-requests/{showroomSweetsRequest}', [ShowroomSweetsRequestController::class, 'show'])
            ->name('showroom-sweets-requests.show')
            ->middleware('can:showroom_sweets_requests.view');

        Route::patch('showroom-sweets-requests/{showroomSweetsRequest}/status', [ShowroomSweetsRequestController::class, 'updateStatus'])
            ->name('showroom-sweets-requests.status')
            ->middleware('can:showroom_sweets_requests.update_status');

        Route::patch('showroom-sweets-requests/{showroomSweetsRequest}/cancel', [ShowroomSweetsRequestController::class, 'cancel'])
            ->name('showroom-sweets-requests.cancel')
            ->middleware('can:showroom_sweets_requests.create');

        Route::delete('showroom-sweets-requests/{showroomSweetsRequest}', [ShowroomSweetsRequestController::class, 'destroy'])
            ->name('showroom-sweets-requests.destroy')
            ->middleware('can:showroom_sweets_requests.delete');

        // ─── Payments ──────────────────────────────────────────────────────
        Route::middleware('can:payments.record')->group(function () {
            Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/{payment}/proof', [PaymentController::class, 'proof'])->name('payments.proof');
            Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
            Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify')->middleware('can:payments.verify');
            Route::post('payments/{payment}/correct', [PaymentController::class, 'correct'])->name('payments.correct')->middleware('can:payments.correct');
            Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund')->middleware('can:payments.refund');
        });

        // ─── Cash Sessions ─────────────────────────────────────────────────
        Route::middleware('can:cash_sessions.manage')->group(function () {
            Route::get('cash-sessions', [CashSessionController::class, 'index'])->name('cash-sessions.index');
            Route::post('cash-sessions', [CashSessionController::class, 'open'])->name('cash-sessions.open');
            Route::post('cash-sessions/{cashSession}/close', [CashSessionController::class, 'close'])->name('cash-sessions.close');
        });

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
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{type}', [ReportController::class, 'show'])->name('reports.show');
            Route::get('reports/{type}/count', [ReportController::class, 'count'])->name('reports.count');
            Route::get('reports/{type}/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
            Route::get('reports/{type}/export/xlsx', [ReportController::class, 'exportXlsx'])->name('reports.export.xlsx');

            // طباعة التقرير كامل حسب الفلاتر الحالية
            Route::get('reports/{type}/print', [ReportController::class, 'printReport'])
                ->name('reports.print');
        });

        // ─── Report Schedules ──────────────────────────────────────────────
        Route::middleware('can:reports.view')->group(function () {
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




Route::middleware(['auth', 'location.scope', 'password.changed', 'can:settings.manage'])
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
