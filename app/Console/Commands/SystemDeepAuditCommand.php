<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class SystemDeepAuditCommand extends Command
{
    protected $signature = 'system:audit
        {--output=storage/app/audits : Output directory, relative to the project root}
        {--format=both : markdown, json, or both}
        {--sample=20 : Maximum sample rows shown in detailed sections}
        {--no-row-counts : Skip table row counts for very large databases}
        {--include-sensitive : Include user display names in the local report}
        {--fail-on=critical : Return a failing exit code at this severity: critical, high, medium, low, or never}';

    protected $description = 'Read-only deep audit for routes, permissions, workflows, notifications, database, code and tests';

    private const MUTATION_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const SEVERITY_WEIGHT = [
        'critical' => 4,
        'high' => 3,
        'medium' => 2,
        'low' => 1,
        'info' => 0,
    ];

    /**
     * Built-in defaults make this command fully self-contained. An optional
     * config/system_audit.php file may override any complete top-level section.
     */
    private const DEFAULT_AUDIT_CONFIG = [
        'public_mutation_allowlist' => [
            'login', 'login.post', 'password.email', 'password.update',
            'attendance.devices.push.heartbeat', 'attendance.devices.push.punches',
            'attendance.integrations.devices.heartbeat', 'attendance.integrations.devices.punches',
            'customer-menu.orders.store', 'storage.local.upload',
        ],
        'authorization_exempt_mutations' => [
            'logout', 'auth.change-password.post', 'password.change', 'profile.update',
        ],
        'ignored_empty_tables' => [
            'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs',
            'password_reset_tokens', 'sessions',
        ],
        'critical_tables' => [
            'users', 'roles', 'permissions', 'role_has_permissions', 'model_has_roles',
            'locations', 'products', 'location_products', 'inventories',
            'orders', 'order_items', 'payment_methods',
        ],
        'scheduled_commands' => ['reports:send-scheduled', 'inventory:check-expiry'],
        'notification_rules' => [
            'order_created' => [
                'event' => 'App\\Events\\OrderCreated',
                'listener' => 'App\\Listeners\\NotifyStaffOnOrderCreated',
                'permissions_any' => ['orders.view'], 'scope' => 'location',
            ],
            'order_status_changed' => [
                'event' => 'App\\Events\\OrderStatusChanged',
                'listener' => 'App\\Listeners\\NotifyStaffOnOrderStatusChanged',
                'permissions_any' => ['orders.view'], 'scope' => 'location',
            ],
            'payment_received' => [
                'event' => 'App\\Events\\PaymentReceived',
                'listener' => 'App\\Listeners\\NotifyStaffOnPaymentReceived',
                'permissions_any' => ['payments.verify', 'payments.view', 'payments.record'], 'scope' => 'location',
            ],
            'low_stock' => [
                'event' => 'App\\Events\\LowStockDetected',
                'listener' => 'App\\Listeners\\NotifyStaffOnLowStock',
                'permissions_any' => ['inventory.view', 'inventory.adjust'], 'scope' => 'location',
            ],
            'stock_request_created' => [
                'notification' => 'App\\Notifications\\StockRequestCreatedNotification',
                'permissions_any' => ['stock_requests.view', 'stock_requests.review'], 'scope' => 'destination_location',
            ],
            'stock_request_status_changed' => [
                'notification' => 'App\\Notifications\\StockRequestStatusChangedNotification',
                'permissions_any' => ['stock_requests.view', 'stock_requests.create'], 'scope' => 'location',
            ],
            'invoice_cancelled' => [
                'notification' => 'App\\Notifications\\InvoiceCancelledNotification',
                'permissions_any' => ['invoices.view', 'invoices.cancel'], 'scope' => 'location',
            ],
            'product_created' => [
                'notification' => 'App\\Notifications\\ProductCreatedNotification',
                'permissions_any' => ['products.view', 'products.update'], 'scope' => 'location',
            ],
            'customer_created' => [
                'notification' => 'App\\Notifications\\CustomerCreatedNotification',
                'permissions_any' => ['customers.view'], 'scope' => 'location',
            ],
            'employee_created' => [
                'notification' => 'App\\Notifications\\EmployeeCreatedNotification',
                'permissions_any' => ['employees.view', 'employees.manage'], 'scope' => 'location',
            ],
            'user_created' => [
                'notification' => 'App\\Notifications\\UserCreatedNotification',
                'permissions_any' => ['users.manage'], 'scope' => 'global',
            ],
            'payment_method_created' => [
                'notification' => 'App\\Notifications\\PaymentMethodCreatedNotification',
                'permissions_any' => ['payment_methods.view', 'payment_methods.manage'], 'scope' => 'global',
            ],
            'sales_channel_changed' => [
                'notification' => 'App\\Notifications\\SalesChannelChanged',
                'permissions_any' => ['sales_channels.view', 'sales_channels.manage'], 'scope' => 'global',
            ],
        ],
        'workflows' => [
            'access_control' => [
                'label' => 'الدخول والأدوار والصلاحيات', 'priority' => 'critical',
                'routes' => ['login', 'logout', 'users.index', 'roles.index'],
                'permissions' => ['users.manage', 'roles.manage'],
                'tables' => ['users', 'roles', 'permissions', 'model_has_roles', 'role_has_permissions'],
            ],
            'sales_order' => [
                'label' => 'الطلب: إنشاء ← تأكيد ← إكمال/إلغاء', 'priority' => 'critical',
                'routes' => ['orders.index', 'orders.store', 'orders.confirm', 'orders.complete', 'orders.cancel'],
                'permissions' => ['orders.view', 'orders.create'],
                'services' => ['App\\Services\\Orders\\OrderService'],
                'tables' => ['orders', 'order_items', 'customers'],
            ],
            'payment_invoice' => [
                'label' => 'الدفع ← التحقق ← الفاتورة ← الطباعة', 'priority' => 'critical',
                'routes' => ['payments.index', 'payments.store', 'payments.verify', 'invoices.index', 'invoices.show', 'invoices.print', 'invoices.pdf'],
                'permissions' => ['payments.record', 'payments.verify', 'invoices.view'],
                'services' => ['App\\Services\\Payments\\PaymentService', 'App\\Services\\Invoices\\InvoiceService'],
                'tables' => ['payments', 'payment_methods', 'invoices', 'invoice_items'],
            ],
            'customer_account' => [
                'label' => 'كشف العميل ← دفعة ← تخصيص ← تحقق', 'priority' => 'high',
                'routes' => ['customers.statement', 'customers.account-payments.store', 'customers.account-payments.verify'],
                'permissions' => ['customers.view', 'payments.record', 'payments.verify'],
                'services' => ['App\\Services\\Customers\\CustomerAccountService'],
                'tables' => ['customers', 'customer_payments', 'customer_payment_allocations'],
            ],
            'financial_period' => [
                'label' => 'الصندوق والفترة المالية', 'priority' => 'critical',
                'routes' => ['cash-sessions.open', 'cash-sessions.close', 'financial-periods.open', 'financial-periods.close'],
                'permissions' => ['cash_sessions.manage', 'financial.periods.view'],
                'services' => ['App\\Services\\Finance\\FinancialPeriodService', 'App\\Services\\Finance\\FinancialPostingService'],
                'tables' => ['cash_sessions', 'financial_periods', 'payments'],
            ],
            'inventory' => [
                'label' => 'المخزون ← جرد ← طلب ← تحويل', 'priority' => 'critical',
                'routes' => ['inventory.index', 'inventory.adjust', 'stock-counts.index', 'stock-requests.index', 'stock-transfers.index'],
                'permissions' => ['inventory.view', 'inventory.adjust', 'inventory.count', 'stock_requests.create', 'stock_transfers.view'],
                'services' => ['App\\Services\\Inventory\\InventoryService', 'App\\Services\\Inventory\\InternalTransferService'],
                'tables' => ['inventories', 'stock_movements', 'stock_counts', 'stock_requests', 'stock_transfers'],
            ],
            'inventory_expiry' => [
                'label' => 'دفعات المخزون ← الصلاحية ← المسح ← التنبيه', 'priority' => 'high',
                'routes' => ['inventory.expiry.index', 'inventory.expiry.print', 'inventory.expiry.scan'],
                'permissions' => ['inventory.view'],
                'services' => ['App\\Services\\Inventory\\InventoryExpiryService', 'App\\Services\\Inventory\\InventoryExpiryMonitorService'],
                'tables' => ['inventory_batches'],
            ],
            'procurement' => [
                'label' => 'مورد ← أمر شراء ← استلام ← فاتورة ← دفع', 'priority' => 'critical',
                'routes' => ['suppliers.index', 'purchase-orders.submit', 'purchase-orders.approve', 'goods-receipts.post', 'supplier-invoices.index', 'supplier-payments.store'],
                'permissions' => ['suppliers.view', 'purchase_orders.view'],
                'services' => ['App\\Services\\Procurement\\PurchaseOrderService', 'App\\Services\\Procurement\\GoodsReceiptService', 'App\\Services\\Procurement\\SupplierInvoiceService'],
                'tables' => ['suppliers', 'supplier_products', 'purchase_orders', 'purchase_order_items', 'goods_receipts', 'supplier_invoices'],
            ],
            'procurement_return_payment' => [
                'label' => 'مرتجع شراء ← ترحيل ← رصيد مورد ← سداد', 'priority' => 'high',
                'routes' => ['purchase-returns.index', 'purchase-returns.store', 'purchase-returns.post', 'supplier-payments.index', 'supplier-payments.store', 'suppliers.statement'],
                'services' => ['App\\Services\\Procurement\\PurchaseReturnService', 'App\\Services\\Procurement\\SupplierPaymentService', 'App\\Services\\Procurement\\SupplierBalanceService'],
                'tables' => ['purchase_returns', 'purchase_return_items', 'supplier_payments', 'supplier_invoices'],
            ],
            'special_cake_orders' => [
                'label' => 'طلب كيك خاص ← مصنع ← انتقالات ← مرفقات', 'priority' => 'high',
                'routes' => ['cake-orders.index', 'cake-orders.store', 'cake-orders.transition', 'cake-orders.comment', 'cake-orders.attachment'],
                'permissions' => ['cake_orders.view', 'cake_orders.create'],
                'services' => ['App\\Services\\SpecialCakes\\SpecialCakeOrderService', 'App\\Services\\SpecialCakes\\SpecialCakeStatusTransitionService'],
                'tables' => ['special_cake_orders', 'cake_order_status_histories'],
            ],
            'branch_sweets_requests' => [
                'label' => 'طلبات الفروع ← مراجعة المصنع ← التجهيز ← الاستلام', 'priority' => 'high',
                'routes' => ['showroom-sweets-requests.index', 'showroom-sweets-requests.store', 'showroom-sweets-requests.status'],
                'permissions' => ['showroom_sweets_requests.view', 'showroom_sweets_requests.create'],
                'services' => ['App\\Services\\Notifications\\ShowroomSweetsRequestNotifier'],
                'tables' => ['showroom_sweets_requests', 'showroom_sweets_request_items'],
            ],
            'restaurant' => [
                'label' => 'طاولة ← جلسة ← POS ← طلب', 'priority' => 'high',
                'routes' => ['restaurant.dashboard', 'restaurant.pos.index', 'restaurant.pos.orders.store', 'restaurant.tables.open', 'restaurant.tables.close'],
                'permissions' => ['restaurant.view', 'restaurant_pos.use', 'restaurant_tables.view'],
                'services' => ['App\\Services\\Restaurant\\RestaurantOrderService', 'App\\Services\\Restaurant\\RestaurantTableService'],
                'tables' => ['restaurant_areas', 'restaurant_tables', 'restaurant_table_sessions', 'orders'],
            ],
            'kitchen_kds' => [
                'label' => 'توجيه ← تذكرة ← تحضير ← جاهز', 'priority' => 'high',
                'routes' => ['kitchen.stations.index', 'kitchen.tickets.index', 'kds.index'],
                'permissions' => ['kitchen.view', 'kds.view'],
                'services' => ['App\\Services\\Kitchen\\KitchenRoutingService', 'App\\Services\\Kitchen\\KitchenTicketService'],
                'tables' => ['kitchen_stations', 'kitchen_tickets', 'kitchen_ticket_items'],
            ],
            'production' => [
                'label' => 'وصفة ← اعتماد ← إنتاج ← صرف ← إكمال', 'priority' => 'critical',
                'routes' => ['production.recipes.index', 'production.recipes.approve', 'production.orders.release', 'production.orders.start', 'production.orders.complete'],
                'permissions' => ['recipes.view', 'recipes.approve', 'production.view', 'production.complete'],
                'services' => ['App\\Services\\Production\\RecipeService', 'App\\Services\\Production\\ProductionOrderService', 'App\\Services\\Production\\ProductionInventoryService'],
                'tables' => ['recipes', 'recipe_items', 'production_orders', 'production_order_items'],
            ],
            'production_quality' => [
                'label' => 'جودة الإنتاج ← فحص ← قبول/رفض', 'priority' => 'high',
                'routes' => ['production.quality.index', 'production.quality.show', 'production.quality.inspect'],
                'permissions' => ['quality_control.view', 'quality_control.inspect'],
                'services' => ['App\\Services\\Production\\ProductionQualityService'],
                'tables' => ['production_quality_inspections'],
            ],
            'attendance_payroll' => [
                'label' => 'دوام ← اعتماد ← راتب ← اعتماد ← صرف', 'priority' => 'critical',
                'routes' => ['attendance.store', 'attendance.approve', 'payroll.calculate', 'payroll.approve', 'payroll.pay'],
                'permissions' => ['attendance.view', 'attendance.approve', 'payroll.view', 'payroll.approve', 'payroll.pay'],
                'services' => ['App\\Services\\AttendanceService', 'App\\Services\\PayrollService', 'App\\Services\\PayrollSettlementService'],
                'tables' => ['attendance_records', 'payroll_periods', 'payroll_items', 'payroll_payments', 'employee_ledger_entries'],
            ],
            'reports' => [
                'label' => 'عرض ← طباعة ← PDF/XLSX ← جدولة ← بريد', 'priority' => 'high',
                'routes' => ['reports.index', 'reports.show', 'reports.print', 'reports.export.pdf', 'reports.export.xlsx', 'report-schedules.index'],
                'permissions' => ['reports.view'], 'services' => ['App\\Services\\ReportScheduleService'],
                'tables' => ['report_schedules'],
            ],
            'product_catalog' => [
                'label' => 'وحدات ← علامات ← خصائص ← متغيرات المنتجات', 'priority' => 'medium',
                'routes' => ['catalog.index', 'catalog.units.index', 'catalog.brands.index', 'catalog.sizes.index', 'catalog.colors.index', 'catalog.products.variants.index'],
                'permissions' => ['products.update'],
                'tables' => ['units', 'brands', 'sizes', 'colors', 'product_attributes', 'product_attribute_values', 'product_variants'],
            ],
            'configuration' => [
                'label' => 'تهيئة العميل والوحدات والعملات والهوية', 'priority' => 'high',
                'routes' => ['modules.index', 'business-profiles.apply', 'onboarding.apply', 'settings.currencies.index', 'settings.print-branding.edit'],
                'permissions' => ['settings.manage'],
                'services' => ['App\\Services\\BusinessProfileService', 'App\\Services\\ClientOnboardingService', 'App\\Services\\ModuleService', 'App\\Services\\PrintThemeService'],
                'tables' => ['modules', 'business_profiles', 'business_profile_modules', 'system_settings'],
            ],
            'notifications' => [
                'label' => 'Event ← Listener ← مستلم حسب الدور والفرع', 'priority' => 'critical',
                'routes' => ['notifications.index', 'notifications.read-all', 'notifications.recent'],
                'tables' => ['notifications'],
            ],
            'internal_chat' => [
                'label' => 'قناة داخلية ← رسالة ← استلام ← قراءة', 'priority' => 'medium',
                'routes' => ['chat.index', 'chat.direct.start', 'chat.show', 'chat.messages.store', 'chat.read'],
                'services' => ['App\\Services\\Chat\\BranchChatService'],
                'tables' => ['chat_channels', 'chat_channel_members', 'chat_messages', 'chat_message_receipts'],
            ],
            'crm_loyalty_delivery' => [
                'label' => 'CRM ← ولاء ← عنوان ← مهمة توصيل', 'priority' => 'medium',
                'routes' => ['crm.index', 'loyalty.index', 'delivery.tasks.index'],
                'permissions' => ['crm.view', 'loyalty.view', 'delivery.view'],
                'services' => ['App\\Services\\Loyalty\\LoyaltyService', 'App\\Services\\Delivery\\DeliveryService'],
                'tables' => ['customer_addresses', 'customer_interactions', 'loyalty_accounts', 'delivery_tasks'],
            ],
            'costing' => [
                'label' => 'التكلفة ← المصروفات ← الربحية', 'priority' => 'high',
                'routes' => ['costing.profitability', 'costing.expenses.index'],
                'permissions' => ['costing.view', 'expenses.view'],
                'services' => ['App\\Services\\Finance\\CostingService', 'App\\Services\\Finance\\ProfitabilityService'],
                'tables' => ['expense_categories', 'expenses'],
            ],
        ],
    ];

    /** Demo-data minimums consolidated from SeederCoverageAudit. */
    private const DATA_EXPECTATIONS = [
        'locations' => [2, 'core', 'critical'],
        'employees' => [8, 'employees', 'critical'],
        'employee_locations' => [8, 'employees', 'critical'],
        'users' => [8, 'users', 'critical'],
        'roles' => [8, 'permissions', 'critical'],
        'permissions' => [25, 'permissions', 'critical'],
        'model_has_roles' => [8, 'permissions', 'critical'],
        'role_has_permissions' => [25, 'permissions', 'critical'],
        'modules' => [10, 'architecture', 'critical'],
        'business_profiles' => [1, 'architecture', 'critical'],
        'business_profile_modules' => [5, 'architecture', 'high'],
        'system_settings' => [15, 'settings', 'high'],
        'categories' => [5, 'catalog', 'critical'],
        'products' => [15, 'catalog', 'critical'],
        'location_products' => [15, 'catalog', 'critical'],
        'inventories' => [15, 'inventory', 'critical'],
        'stock_movements' => [5, 'inventory', 'high'],
        'inventory_batches' => [3, 'expiry', 'high'],
        'stock_counts' => [1, 'inventory', 'medium'],
        'stock_count_items' => [3, 'inventory', 'medium'],
        'stock_requests' => [2, 'inventory', 'high'],
        'stock_request_items' => [3, 'inventory', 'high'],
        'stock_transfers' => [1, 'inventory', 'medium'],
        'customers' => [8, 'sales', 'critical'],
        'orders' => [12, 'sales', 'critical'],
        'order_items' => [20, 'sales', 'critical'],
        'invoices' => [8, 'finance', 'critical'],
        'invoice_items' => [12, 'finance', 'high'],
        'payments' => [6, 'finance', 'critical'],
        'payment_methods' => [3, 'finance', 'critical'],
        'financial_periods' => [2, 'finance', 'high'],
        'cash_sessions' => [2, 'finance', 'high'],
        'suppliers' => [3, 'procurement', 'high'],
        'supplier_products' => [5, 'procurement', 'high'],
        'purchase_orders' => [2, 'procurement', 'high'],
        'purchase_order_items' => [4, 'procurement', 'high'],
        'goods_receipts' => [1, 'procurement', 'medium'],
        'supplier_invoices' => [1, 'procurement', 'medium'],
        'sales_channels' => [3, 'sales_channels', 'medium'],
        'restaurant_areas' => [1, 'restaurant', 'high'],
        'restaurant_tables' => [6, 'restaurant', 'high'],
        'restaurant_table_sessions' => [2, 'restaurant', 'medium'],
        'kitchen_stations' => [1, 'kitchen', 'critical'],
        'kitchen_tickets' => [3, 'kitchen', 'high'],
        'kitchen_ticket_items' => [5, 'kitchen', 'high'],
        'recipes' => [2, 'production', 'critical'],
        'recipe_items' => [5, 'production', 'critical'],
        'production_orders' => [2, 'production', 'critical'],
        'production_order_items' => [4, 'production', 'high'],
        'work_shifts' => [2, 'attendance', 'critical'],
        'employee_shift_assignments' => [5, 'attendance', 'critical'],
        'attendance_records' => [40, 'attendance', 'critical'],
        'leave_types' => [3, 'attendance', 'medium'],
        'employee_leave_requests' => [2, 'attendance', 'medium'],
        'employee_compensation_profiles' => [5, 'payroll', 'critical'],
        'employee_payroll_adjustments' => [8, 'payroll', 'critical'],
        'employee_advances' => [2, 'payroll', 'medium'],
        'payroll_periods' => [2, 'payroll', 'critical'],
        'payroll_items' => [8, 'payroll', 'critical'],
        'payroll_item_components' => [16, 'payroll', 'critical'],
        'payroll_payments' => [3, 'payroll', 'high'],
        'employee_ledger_entries' => [12, 'payroll', 'critical'],
        'report_schedules' => [2, 'reports', 'medium'],
        'notifications' => [8, 'notifications', 'high'],
        'activity_logs' => [8, 'audit', 'medium'],
    ];

    /** @var array<int, array<string, mixed>> */
    private array $issues = [];

    /** @var array<string, true> */
    private array $routeNames = [];

    /** @var array<string, int> */
    private array $permissionIds = [];

    /** @var array<int, array<string, true>> */
    private array $effectiveUserPermissions = [];

    /** @var array<int, array<int, string>> */
    private array $userRoles = [];

    /** @var array<int, bool> */
    private array $userIsActive = [];

    /** @var array<int, array<int, int>> */
    private array $userLocations = [];

    /** @var array<int, string> */
    private array $userLabelCache = [];

    /** @var array<string, int|null> */
    private array $tableCounts = [];

    public function handle(): int
    {
        $startedAt = microtime(true);
        $this->components->info('Running a read-only full-system audit...');

        $report = [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'application' => (string) config('app.name'),
                'environment' => (string) app()->environment(),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'database_driver' => DB::connection()->getDriverName(),
                'read_only' => true,
            ],
        ];

        $report['configuration'] = $this->auditConfiguration();
        $report['operations'] = $this->auditOperations();
        $report['database'] = $this->auditDatabase();
        $report['data_coverage'] = $this->auditDataCoverage();
        $report['data_integrity'] = $this->auditDataIntegrity();
        $report['expiry_readiness'] = $this->auditExpiryReadiness();
        $report['routes'] = $this->auditRoutes();
        $report['access_control'] = $this->auditAccessControl();
        $report['code'] = $this->auditCode();
        $report['enums'] = $this->auditEnums();
        $report['modules'] = $this->auditModules();
        $report['notifications'] = $this->auditNotifications();
        $report['workflows'] = $this->auditWorkflows();
        $report['release_readiness'] = $this->auditReleaseReadiness();
        $report['tests'] = $this->auditTests();

        $report['issues'] = collect($this->issues)
            ->sortByDesc(fn (array $issue): int => self::SEVERITY_WEIGHT[$issue['severity']] ?? 0)
            ->values()
            ->all();

        $report['summary'] = $this->buildSummary($report);
        $report['meta']['duration_seconds'] = round(microtime(true) - $startedAt, 3);

        $paths = $this->writeReport($report);
        $this->renderConsoleSummary($report, $paths);

        return $this->shouldFail($report['issues']) ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function auditConfiguration(): array
    {
        $environment = (string) app()->environment();
        $debug = (bool) config('app.debug');
        $appKey = (string) config('app.key');
        $appUrl = (string) config('app.url');
        $mailer = (string) config('mail.default');
        $queue = (string) config('queue.default');
        $sessionSecure = (bool) config('session.secure');
        $sameSite = (string) config('session.same_site');
        $auditConfigExists = File::exists(config_path('system_audit.php'));
        $notificationRuleCount = count($this->auditSetting('notification_rules', []));
        $workflowCount = count($this->auditSetting('workflows', []));

        if ($appKey === '') {
            $this->issue('critical', 'configuration', 'app_key_missing', 'APP_KEY is empty. Encryption and sessions are unsafe.');
        }

        if ($debug && in_array($environment, ['production', 'staging'], true)) {
            $this->issue('critical', 'configuration', 'debug_enabled', 'APP_DEBUG is enabled outside local development.');
        }

        if (File::exists(public_path('.env'))) {
            $this->issue('critical', 'configuration', 'public_env_exposed', 'A .env file exists under public/.');
        }

        if (in_array($environment, ['production', 'staging'], true) && str_starts_with($appUrl, 'http://')) {
            $this->issue('high', 'configuration', 'insecure_app_url', 'APP_URL uses HTTP outside local development.', ['url' => $appUrl]);
        }

        if (str_starts_with($appUrl, 'https://') && ! $sessionSecure) {
            $this->issue('high', 'configuration', 'insecure_session_cookie', 'HTTPS is configured but SESSION_SECURE_COOKIE is disabled.');
        }

        if ($sameSite === 'none' && ! $sessionSecure) {
            $this->issue('high', 'configuration', 'invalid_same_site_cookie', 'SameSite=None requires secure session cookies.');
        }

        if ($environment === 'production' && in_array($mailer, ['log', 'array'], true)) {
            $this->issue('high', 'configuration', 'non_delivery_mailer', 'Production mailer does not deliver real mail.', ['mailer' => $mailer]);
        }

        if ($environment === 'production' && $queue === 'sync') {
            $this->issue('medium', 'configuration', 'sync_queue', 'Production queue uses sync; notifications and reports block web requests.');
        }

        return [
            'app_env' => $environment,
            'app_debug' => $debug,
            'app_key_present' => $appKey !== '',
            'app_url' => $appUrl,
            'timezone' => (string) config('app.timezone'),
            'mailer' => $mailer,
            'queue' => $queue,
            'session_secure' => $sessionSecure,
            'session_same_site' => $sameSite,
            'public_env_exists' => File::exists(public_path('.env')),
            'storage_link_exists' => File::exists(public_path('storage')),
            'system_audit_config_exists' => $auditConfigExists,
            'system_audit_config_source' => $auditConfigExists ? 'external_with_built_in_fallback' : 'built_in',
            'notification_rule_count' => $notificationRuleCount,
            'workflow_count' => $workflowCount,
        ];
    }

    /** @return array<string, mixed> */
    private function auditOperations(): array
    {
        $schedulerFiles = [
            base_path('routes/console.php'),
            app_path('Console/Kernel.php'),
            base_path('bootstrap/app.php'),
        ];
        $schedulerSource = collect($schedulerFiles)
            ->filter(fn (string $path): bool => File::exists($path))
            ->map(fn (string $path): string => File::get($path))
            ->implode("\n");

        $scheduledCommands = [];
        foreach ($this->auditSetting('scheduled_commands', []) as $command) {
            $registered = str_contains($schedulerSource, $command);
            $scheduledCommands[] = ['command' => $command, 'registered' => $registered];
            if (! $registered) {
                $this->issue('high', 'operations', 'scheduled_command_missing', "Scheduled command [{$command}] was not found in the scheduler definition.");
            }
        }

        $reportSchedules = ['available' => false, 'active' => 0, 'invalid' => []];
        if (Schema::hasTable('report_schedules')) {
            try {
                $columns = Schema::getColumnListing('report_schedules');
                $query = DB::table('report_schedules');
                if (in_array('is_active', $columns, true)) {
                    $query->where('is_active', true);
                }
                $activeSchedules = $query->get();
                $invalid = [];

                foreach ($activeSchedules as $schedule) {
                    $problems = [];
                    foreach (['report_type', 'frequency', 'hour'] as $column) {
                        if (in_array($column, $columns, true) && blank($schedule->{$column} ?? null)) {
                            $problems[] = "missing_{$column}";
                        }
                    }

                    if (in_array('recipients', $columns, true)) {
                        $emails = $this->recipientEmails($schedule->recipients ?? null);
                        if ($emails === []) {
                            $problems[] = 'missing_recipients';
                        } elseif (collect($emails)->contains(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
                            $problems[] = 'invalid_recipient';
                        }
                    }

                    if ($problems !== []) {
                        $id = (int) ($schedule->id ?? 0);
                        $invalid[] = ['id' => $id, 'problems' => $problems];
                        $this->issue('high', 'operations', 'invalid_report_schedule', "Active report schedule #{$id} is incomplete or invalid.", ['problems' => $problems]);
                    }
                }

                if ($activeSchedules->isEmpty()) {
                    $this->issue('medium', 'operations', 'no_active_report_schedules', 'No active report schedule exists.');
                }

                $reportSchedules = ['available' => true, 'active' => $activeSchedules->count(), 'invalid' => $invalid];
            } catch (Throwable $e) {
                $this->issue('high', 'operations', 'report_schedule_scan_failed', 'Could not inspect scheduled report configuration.', ['error' => $e->getMessage()]);
                $reportSchedules = ['available' => false, 'active' => 0, 'invalid' => [], 'error' => $e->getMessage()];
            }
        }

        $failedJobs = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : null;
        if (($failedJobs ?? 0) > 0) {
            $this->issue('high', 'operations', 'failed_queue_jobs', "The failed_jobs table contains {$failedJobs} failed job(s).");
        }

        $mailer = (string) config('mail.default');
        $mailerConfig = config("mail.mailers.{$mailer}", []);
        if ($mailer === 'smtp' && blank($mailerConfig['host'] ?? null)) {
            $this->issue('critical', 'operations', 'smtp_host_missing', 'SMTP is selected but MAIL_HOST is empty.');
        }

        return [
            'scheduled_commands' => $scheduledCommands,
            'report_schedules' => $reportSchedules,
            'failed_jobs' => $failedJobs,
            'mailer' => $mailer,
            'queue' => (string) config('queue.default'),
            'external_scheduler_heartbeat_verified' => false,
            'note' => 'Application code can verify schedule definitions, but the operating-system cron/Task Scheduler heartbeat must be monitored externally.',
        ];
    }

    /** @return array<string, mixed> */
    private function auditDatabase(): array
    {
        $tables = $this->databaseTables();
        $ignoredEmpty = array_flip($this->auditSetting('ignored_empty_tables', []));
        $criticalTables = array_flip($this->auditSetting('critical_tables', []));
        $withCounts = ! (bool) $this->option('no-row-counts');
        $inventory = [];

        foreach ($tables as $table) {
            try {
                $columns = Schema::getColumnListing($table);
                $count = $withCounts ? (int) DB::table($table)->count() : null;
                $this->tableCounts[$table] = $count;

                $inventory[] = [
                    'table' => $table,
                    'rows' => $count,
                    'columns' => count($columns),
                    'status' => $count === 0 ? 'EMPTY' : ($count === null ? 'NOT_COUNTED' : 'HAS_DATA'),
                ];

                if ($count === 0 && isset($criticalTables[$table])) {
                    $this->issue('critical', 'database', 'critical_table_empty', "Critical table [{$table}] is empty.", ['table' => $table]);
                } elseif ($count === 0 && ! isset($ignoredEmpty[$table])) {
                    $this->issue('low', 'database', 'table_empty', "Table [{$table}] is empty.", ['table' => $table]);
                }

                $this->auditLikelyMissingIndexes($table, $columns);
            } catch (Throwable $e) {
                $this->tableCounts[$table] = null;
                $inventory[] = ['table' => $table, 'rows' => null, 'columns' => null, 'status' => 'ERROR'];
                $this->issue('high', 'database', 'table_scan_failed', "Could not inspect table [{$table}].", ['error' => $e->getMessage()]);
            }
        }

        foreach (array_keys($criticalTables) as $requiredTable) {
            if (! in_array($requiredTable, $tables, true)) {
                $this->issue('critical', 'database', 'critical_table_missing', "Critical table [{$requiredTable}] does not exist.");
            }
        }

        return [
            'tables_total' => count($tables),
            'tables_empty' => count(array_filter($inventory, fn (array $row): bool => $row['status'] === 'EMPTY')),
            'inventory' => $inventory,
        ];
    }

    /** @param array<int, string> $columns */
    private function auditLikelyMissingIndexes(string $table, array $columns): void
    {
        try {
            $indexes = Schema::getIndexes($table);
            $indexed = [];
            foreach ($indexes as $index) {
                foreach (($index['columns'] ?? []) as $column) {
                    $indexed[(string) $column] = true;
                }
            }

            foreach ($columns as $column) {
                if (str_ends_with($column, '_id') && ! isset($indexed[$column])) {
                    $this->issue('low', 'database', 'possible_missing_index', "Foreign-key-like column [{$table}.{$column}] is not indexed.");
                }
            }
        } catch (Throwable) {
            // Index introspection is not supported by every configured driver.
        }
    }

    /** @return array<int, string> */
    private function databaseTables(): array
    {
        $driver = DB::connection()->getDriverName();

        try {
            return match ($driver) {
                'mysql', 'mariadb' => collect(DB::select(
                    'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = ?',
                    ['BASE TABLE']
                ))->map(fn (object $row): string => (string) ($row->TABLE_NAME ?? $row->table_name))->sort()->values()->all(),
                'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
                    ->pluck('name')->map(fn ($name): string => (string) $name)->sort()->values()->all(),
                'pgsql' => collect(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"))
                    ->pluck('tablename')->map(fn ($name): string => (string) $name)->sort()->values()->all(),
                default => [],
            };
        } catch (Throwable $e) {
            $this->issue('critical', 'database', 'database_inventory_failed', 'Could not list database tables.', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /** @param array<int, string> $columns */
    private function hasColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return collect($columns)->every(fn (string $column): bool => Schema::hasColumn($table, $column));
    }

    /** @return array<string, mixed> */
    private function auditDataCoverage(): array
    {
        $rows = [];
        foreach (self::DATA_EXPECTATIONS as $table => [$minimum, $module, $priority]) {
            $exists = Schema::hasTable($table);
            $count = $exists ? ($this->tableCounts[$table] ?? null) : null;
            $status = ! $exists ? 'MISSING' : ($count === null ? 'NOT_COUNTED' : ($count < $minimum ? 'INSUFFICIENT' : 'READY'));

            if ($status === 'MISSING') {
                $this->issue($priority, 'demo_data', 'expected_table_missing', "Expected demo-data table [{$table}] is missing.", [
                    'module' => $module, 'minimum' => $minimum,
                ]);
            } elseif ($status === 'INSUFFICIENT') {
                $this->issue($priority, 'demo_data', 'insufficient_demo_data', "Table [{$table}] has {$count} row(s); the integrated demo expects at least {$minimum}.", [
                    'module' => $module, 'table' => $table, 'actual' => $count, 'minimum' => $minimum,
                ]);
            }

            $rows[] = [
                'table' => $table,
                'module' => $module,
                'priority' => $priority,
                'exists' => $exists,
                'rows' => $count,
                'minimum' => $minimum,
                'status' => $status,
            ];
        }

        return [
            'expected_tables' => count($rows),
            'ready' => collect($rows)->where('status', 'READY')->count(),
            'insufficient' => collect($rows)->where('status', 'INSUFFICIENT')->count(),
            'missing' => collect($rows)->where('status', 'MISSING')->count(),
            'items' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    private function auditDataIntegrity(): array
    {
        $checks = [];

        foreach ($this->databaseTables() as $table) {
            try {
                foreach (Schema::getForeignKeys($table) as $foreignKey) {
                    $columns = array_values($foreignKey['columns'] ?? []);
                    $foreignColumns = array_values($foreignKey['foreign_columns'] ?? []);
                    $foreignTable = $foreignKey['foreign_table'] ?? null;
                    if (count($columns) !== 1 || count($foreignColumns) !== 1 || ! is_string($foreignTable) || ! Schema::hasTable($foreignTable)) {
                        continue;
                    }

                    $childColumn = $columns[0];
                    $parentColumn = $foreignColumns[0];
                    $this->integrityCount(
                        $checks,
                        'foreign_keys',
                        "{$table}.{$childColumn} → {$foreignTable}.{$parentColumn}",
                        fn (): int => (int) DB::table("{$table} as child")
                            ->leftJoin("{$foreignTable} as parent", "parent.{$parentColumn}", '=', "child.{$childColumn}")
                            ->whereNotNull("child.{$childColumn}")
                            ->whereNull("parent.{$parentColumn}")
                            ->count(),
                        'critical'
                    );
                }
            } catch (Throwable $e) {
                $checks[] = [
                    'section' => 'foreign_keys', 'check' => $table, 'status' => 'ERROR',
                    'count' => null, 'error' => $e->getMessage(),
                ];
                $this->issue('medium', 'data_integrity', 'foreign_key_scan_failed', "Could not inspect foreign keys for [{$table}].", ['error' => $e->getMessage()]);
            }
        }

        if ($this->hasColumns('purchase_order_items', ['id', 'purchase_order_id', 'product_id', 'ordered_quantity', 'received_quantity'])
            && $this->hasColumns('goods_receipt_items', ['goods_receipt_id', 'purchase_order_item_id', 'accepted_quantity'])
            && $this->hasColumns('goods_receipts', ['status'])) {
            $this->integrityRows($checks, 'procurement', 'PO received quantity equals posted receipts', function () {
                $posted = DB::table('goods_receipt_items as gri')
                    ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
                    ->where('gr.status', 'posted')
                    ->groupBy('gri.purchase_order_item_id')
                    ->selectRaw('gri.purchase_order_item_id, SUM(gri.accepted_quantity) as accepted_total');

                return DB::table('purchase_order_items as poi')
                    ->leftJoinSub($posted, 'posted', 'posted.purchase_order_item_id', '=', 'poi.id')
                    ->whereRaw('ABS(poi.received_quantity - COALESCE(posted.accepted_total, 0)) > 0.0005')
                    ->selectRaw('poi.id, poi.purchase_order_id, poi.product_id, poi.ordered_quantity, poi.received_quantity, COALESCE(posted.accepted_total, 0) as posted_accepted_quantity')
                    ->orderBy('poi.id')->get();
            });
        }

        if ($this->hasColumns('purchase_order_items', ['ordered_quantity', 'received_quantity'])) {
            $this->integrityCount($checks, 'procurement', 'No over-received purchase-order lines',
                fn (): int => (int) DB::table('purchase_order_items')->whereRaw('received_quantity > ordered_quantity + 0.0005')->count());
        }

        if ($this->hasColumns('supplier_products', ['conversion_factor'])) {
            $this->integrityCount($checks, 'procurement', 'Positive supplier conversion factors',
                fn (): int => (int) DB::table('supplier_products')->where('conversion_factor', '<=', 0)->count());
        }

        if ($this->hasColumns('supplier_products', ['supplier_id', 'product_id'])) {
            $this->integrityCount($checks, 'procurement', 'Unique supplier/product combinations',
                fn (): int => DB::table('supplier_products')->select(['supplier_id', 'product_id'])
                    ->groupBy('supplier_id', 'product_id')->havingRaw('COUNT(*) > 1')->get()->count());
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'supplier_id') && Schema::hasTable('supplier_products')) {
            $this->issue('medium', 'schema', 'legacy_single_supplier_column',
                'products.supplier_id still exists beside supplier_products; verify that legacy code cannot bypass the many-to-many supplier model.');
        }

        if ($this->hasColumns('supplier_products', ['product_id'])) {
            $preferredColumn = collect(['is_preferred', 'is_default', 'preferred'])
                ->first(fn (string $column): bool => Schema::hasColumn('supplier_products', $column));
            if ($preferredColumn) {
                $this->integrityCount($checks, 'procurement', 'At most one preferred supplier per product',
                    fn (): int => DB::table('supplier_products')->select('product_id')->where($preferredColumn, true)
                        ->groupBy('product_id')->havingRaw('COUNT(*) > 1')->get()->count());
            }
        }

        if ($this->hasColumns('inventories', ['quantity', 'reserved_quantity', 'damaged_quantity'])) {
            $this->integrityCount($checks, 'inventory', 'Non-negative inventory and valid reservations', fn (): int => (int) DB::table('inventories')
                ->where(function ($query): void {
                    $query->where('quantity', '<', 0)
                        ->orWhere('reserved_quantity', '<', 0)
                        ->orWhere('damaged_quantity', '<', 0)
                        ->orWhereRaw('reserved_quantity > quantity + 0.0005');
                })->count());
        }

        if ($this->hasColumns('inventory_batches', ['received_quantity', 'available_quantity'])) {
            $this->integrityCount($checks, 'inventory', 'Batch availability stays within received quantity',
                fn (): int => (int) DB::table('inventory_batches')->where('available_quantity', '<', 0)
                    ->orWhereRaw('available_quantity > received_quantity + 0.0005')->count());
        }

        foreach (['stock_movements', 'sales_ledger_entries'] as $table) {
            if ($this->hasColumns($table, ['idempotency_key'])) {
                $this->integrityCount($checks, 'idempotency', "Unique {$table} idempotency keys",
                    fn (): int => DB::table($table)->select('idempotency_key')->whereNotNull('idempotency_key')
                        ->groupBy('idempotency_key')->havingRaw('COUNT(*) > 1')->get()->count());
            }
        }

        if ($this->hasColumns('invoices', ['total_amount', 'paid_amount', 'remaining_amount'])) {
            $this->integrityCount($checks, 'finance', 'Invoice total equals paid plus remaining',
                fn (): int => (int) DB::table('invoices')->whereRaw('ABS(total_amount - paid_amount - remaining_amount) > 0.01')->count());
        }

        if ($this->hasColumns('refunds', ['payment_id', 'amount']) && $this->hasColumns('payments', ['amount'])) {
            $this->integrityCount($checks, 'finance', 'Refund totals do not exceed payment amount', function (): int {
                $refundTotals = DB::table('refunds')->groupBy('payment_id')->selectRaw('payment_id, SUM(amount) as refunded');
                return (int) DB::table('payments as p')->joinSub($refundTotals, 'r', 'r.payment_id', '=', 'p.id')
                    ->whereRaw('r.refunded > p.amount + 0.01')->count();
            });
        }

        if ($this->hasColumns('cash_sessions', ['employee_id', 'status'])) {
            $this->integrityCount($checks, 'cash', 'One open cash session per employee',
                fn (): int => DB::table('cash_sessions')->select('employee_id')->where('status', 'open')
                    ->groupBy('employee_id')->havingRaw('COUNT(*) > 1')->get()->count());
        }

        $this->auditPeriodOverlaps($checks, 'financial_periods', 'finance');
        $this->auditPeriodOverlaps($checks, 'payroll_periods', 'payroll');

        if ($this->hasColumns('payroll_payments', ['status'])
            && $this->hasColumns('employee_ledger_entries', ['source_type', 'source_id'])) {
            $this->integrityCount($checks, 'payroll', 'Posted payroll payments have ledger entries',
                fn (): int => (int) DB::table('payroll_payments as pp')
                    ->leftJoin('employee_ledger_entries as ele', function ($join): void {
                        $join->on('ele.source_id', '=', 'pp.id')->where('ele.source_type', 'payroll_payment');
                    })->where('pp.status', 'posted')->whereNull('ele.id')->count());

            $this->integrityCount($checks, 'payroll', 'Voided payroll payments have reversal entries',
                fn (): int => (int) DB::table('payroll_payments as pp')
                    ->leftJoin('employee_ledger_entries as ele', function ($join): void {
                        $join->on('ele.source_id', '=', 'pp.id')->where('ele.source_type', 'payroll_payment_void');
                    })->where('pp.status', 'voided')->whereNull('ele.id')->count());
        }

        if ($this->hasColumns('restaurant_table_sessions', ['restaurant_table_id', 'location_id', 'status'])
            && $this->hasColumns('restaurant_tables', ['location_id'])) {
            $this->integrityCount($checks, 'restaurant', 'One open session per restaurant table',
                fn (): int => DB::table('restaurant_table_sessions')->select('restaurant_table_id')->where('status', 'open')
                    ->groupBy('restaurant_table_id')->havingRaw('COUNT(*) > 1')->get()->count());
            $this->integrityCount($checks, 'restaurant', 'Table session matches table location',
                fn (): int => (int) DB::table('restaurant_table_sessions as s')
                    ->join('restaurant_tables as t', 't.id', '=', 's.restaurant_table_id')
                    ->whereColumn('s.location_id', '!=', 't.location_id')->count());
        }

        return [
            'checks_total' => count($checks),
            'passed' => collect($checks)->where('status', 'PASS')->count(),
            'failed' => collect($checks)->where('status', 'FAIL')->count(),
            'errors' => collect($checks)->where('status', 'ERROR')->count(),
            'checks' => $checks,
        ];
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function auditPeriodOverlaps(array &$checks, string $table, string $section): void
    {
        if (! $this->hasColumns($table, ['start_date', 'end_date'])) {
            return;
        }

        $hasLocation = Schema::hasColumn($table, 'location_id');
        $this->integrityCount($checks, $section, "{$table} periods do not overlap", function () use ($table, $hasLocation): int {
            return (int) DB::table("{$table} as a")->join("{$table} as b", function ($join) use ($hasLocation): void {
                $join->on('a.id', '<', 'b.id')
                    ->whereColumn('a.start_date', '<=', 'b.end_date')
                    ->whereColumn('a.end_date', '>=', 'b.start_date');
                if ($hasLocation) {
                    $join->whereColumn('a.location_id', '=', 'b.location_id');
                }
            })->count();
        }, 'high');
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function integrityCount(array &$checks, string $section, string $check, callable $resolver, string $severity = 'critical'): void
    {
        try {
            $count = (int) $resolver();
            $status = $count > 0 ? 'FAIL' : 'PASS';
            $checks[] = compact('section', 'check', 'status', 'count');
            if ($count > 0) {
                $this->issue($severity, 'data_integrity', 'business_rule_violation', "{$check}: {$count} violating row(s).", [
                    'section' => $section, 'count' => $count,
                ]);
            }
        } catch (Throwable $e) {
            $checks[] = ['section' => $section, 'check' => $check, 'status' => 'ERROR', 'count' => null, 'error' => $e->getMessage()];
            $this->issue('high', 'data_integrity', 'integrity_check_failed', "Integrity check [{$check}] could not run.", ['error' => $e->getMessage()]);
        }
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function integrityRows(array &$checks, string $section, string $check, callable $resolver, string $severity = 'critical'): void
    {
        try {
            $rows = collect($resolver());
            $count = $rows->count();
            $status = $count > 0 ? 'FAIL' : 'PASS';
            $sample = $rows->take((int) $this->option('sample'))->map(fn ($row): array => (array) $row)->values()->all();
            $checks[] = compact('section', 'check', 'status', 'count', 'sample');
            if ($count > 0) {
                $this->issue($severity, 'data_integrity', 'business_rule_violation', "{$check}: {$count} violating row(s).", [
                    'section' => $section, 'count' => $count, 'sample' => $sample,
                ]);
            }
        } catch (Throwable $e) {
            $checks[] = ['section' => $section, 'check' => $check, 'status' => 'ERROR', 'count' => null, 'error' => $e->getMessage()];
            $this->issue('high', 'data_integrity', 'integrity_check_failed', "Integrity check [{$check}] could not run.", ['error' => $e->getMessage()]);
        }
    }

    /** @return array<string, mixed> */
    private function auditExpiryReadiness(): array
    {
        $report = [
            'products' => ['total' => null, 'tracks_batch' => null, 'tracks_expiry' => null, 'tracks_both' => null],
            'batches' => ['total' => null, 'with_expiry' => null, 'available_with_expiry' => null, 'expired' => null, 'within_7_days' => null, 'within_30_days' => null, 'within_60_days' => null],
            'required_permissions' => [],
            'missing_permissions' => [],
            'settings' => [],
            'missing_settings' => [],
            'mail' => [
                'default' => (string) config('mail.default'),
                'from_configured' => filled(config('mail.from.address')),
            ],
            'whatsapp' => [
                'service_present' => filled(config('services.whatsapp')) || filled(config('services.meta_whatsapp')),
                'token_configured' => filled(config('services.whatsapp.token')) || filled(config('services.meta_whatsapp.token')),
                'phone_number_id_configured' => filled(config('services.whatsapp.phone_number_id')) || filled(config('services.meta_whatsapp.phone_number_id')),
            ],
        ];

        if (Schema::hasTable('products')) {
            $report['products']['total'] = (int) DB::table('products')->count();
            if ($this->hasColumns('products', ['tracks_batch', 'tracks_expiry'])) {
                $report['products']['tracks_batch'] = (int) DB::table('products')->where('tracks_batch', true)->count();
                $report['products']['tracks_expiry'] = (int) DB::table('products')->where('tracks_expiry', true)->count();
                $report['products']['tracks_both'] = (int) DB::table('products')->where('tracks_batch', true)->where('tracks_expiry', true)->count();
            }
        }

        if ($this->hasColumns('inventory_batches', ['expiry_date'])) {
            $today = now()->startOfDay();
            $available = DB::table('inventory_batches')->whereNotNull('expiry_date');
            if (Schema::hasColumn('inventory_batches', 'available_quantity')) {
                $available->where('available_quantity', '>', 0);
            }

            $report['batches'] = [
                'total' => (int) DB::table('inventory_batches')->count(),
                'with_expiry' => (int) DB::table('inventory_batches')->whereNotNull('expiry_date')->count(),
                'available_with_expiry' => (clone $available)->count(),
                'expired' => (clone $available)->whereDate('expiry_date', '<', $today->toDateString())->count(),
                'within_7_days' => (clone $available)->whereBetween('expiry_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])->count(),
                'within_30_days' => (clone $available)->whereBetween('expiry_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])->count(),
                'within_60_days' => (clone $available)->whereBetween('expiry_date', [$today->toDateString(), $today->copy()->addDays(60)->toDateString()])->count(),
            ];
        }

        $requiredPermissions = [
            'inventory.expiry-alerts.view', 'inventory.expiry-alerts.receive',
            'inventory.expiry-alerts.receive_all', 'inventory.expiry-alerts.settings',
        ];
        $existingPermissions = Schema::hasTable('permissions')
            ? DB::table('permissions')->whereIn('name', $requiredPermissions)->pluck('name')->all()
            : [];
        $report['required_permissions'] = $requiredPermissions;
        $report['missing_permissions'] = array_values(array_diff($requiredPermissions, $existingPermissions));
        foreach ($report['missing_permissions'] as $permission) {
            $this->issue('high', 'expiry', 'expiry_permission_missing', "Expiry permission [{$permission}] is missing.");
        }

        $requiredSettings = [
            'inventory_expiry_monitoring_enabled', 'inventory_expiry_alert_days',
            'inventory_expiry_notify_database', 'inventory_expiry_notify_email',
            'inventory_expiry_notify_whatsapp', 'inventory_expiry_block_expired_stock',
        ];
        if ($this->hasColumns('system_settings', ['key', 'value'])) {
            $report['settings'] = DB::table('system_settings')->whereIn('key', $requiredSettings)->pluck('value', 'key')->all();
        }
        $report['missing_settings'] = array_values(array_diff($requiredSettings, array_keys($report['settings'])));
        foreach ($report['missing_settings'] as $setting) {
            $this->issue('medium', 'expiry', 'expiry_setting_missing', "Expiry setting [{$setting}] is missing.");
        }

        return $report;
    }

    /** @return array<string, mixed> */
    private function auditRoutes(): array
    {
        $rows = [];
        $groups = [];
        $seenNames = [];
        $seenSignatures = [];
        $routePermissions = [];
        $allowlist = array_flip($this->auditSetting('public_mutation_allowlist', []));
        $authorizationExempt = array_flip($this->auditSetting('authorization_exempt_mutations', []));

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            $uri = $route->uri();
            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $middleware = $route->gatherMiddleware();
            $action = $route->getActionName();
            $isMutation = count(array_intersect($methods, self::MUTATION_METHODS)) > 0;
            $permissions = $this->permissionsFromMiddleware($middleware);
            $hasAuth = $this->middlewareContains($middleware, ['auth', 'sanctum', 'passport']);
            $hasSignedRequest = $this->middlewareContains($middleware, ['signed']);
            $isPublicAllowlisted = isset($allowlist[$name ?? '']);
            $sourceSecurity = $this->actionSecurity($action);
            $hasAuthorization = $permissions !== [] || $sourceSecurity['authorizes'];
            $group = $name ? explode('.', $name)[0] : '_unnamed';

            if ($name) {
                $this->routeNames[$name] = true;
                $seenNames[$name][] = $uri;
            } else {
                $this->issue('medium', 'routes', 'unnamed_route', "Route [{$uri}] has no name.", ['methods' => $methods]);
            }

            $signature = implode('|', $methods).' '.$uri;
            $seenSignatures[$signature][] = $name ?: $action;

            if ($isMutation && ! $hasAuth && ! $hasSignedRequest && ! $isPublicAllowlisted) {
                $this->issue('critical', 'route_security', 'mutation_without_auth', "Mutation route [{$name}] is not protected by authentication.", ['uri' => $uri, 'methods' => $methods]);
            }

            if ($isMutation && ! $hasAuth && $isPublicAllowlisted && ! $hasSignedRequest) {
                $rateLimited = $this->middlewareContains($middleware, ['throttle'])
                    || $this->actionSourceMatches($action, '/(?:RateLimiter|tooManyAttempts|hit\s*\(|incrementLoginAttempts|ensureIsNotRateLimited)/i');
                if (! $rateLimited) {
                    $this->issue('high', 'route_security', 'public_mutation_without_rate_limit', "Public mutation route [{$name}] is allowlisted but has no detected rate limiting.", ['uri' => $uri, 'action' => $action]);
                }
            }

            if ($isMutation && $hasAuth && ! $hasAuthorization && ! isset($authorizationExempt[$name ?? ''])) {
                $this->issue('high', 'route_security', 'mutation_without_authorization', "Mutation route [{$name}] has auth but no detected permission/policy check.", ['uri' => $uri, 'action' => $action]);
            }

            if (in_array('GET', $methods, true) && $this->looksLikeStateChangingGet($action, $name)) {
                $this->issue('high', 'route_security', 'state_change_over_get', "GET route [{$name}] looks like a state-changing action.", ['uri' => $uri, 'action' => $action]);
            }

            if (preg_match('/(?:telescope|horizon|phpinfo|debugbar|_ignition)/i', $uri) && app()->environment('production')) {
                $this->issue('high', 'route_security', 'debug_route_exposed', "Debug/operations route [{$uri}] is registered in production.");
            }

            foreach ($permissions as $permission) {
                $routePermissions[$permission] = true;
            }

            $groups[$group] ??= ['group' => $group, 'routes' => 0, 'read' => 0, 'mutations' => 0, 'permissions' => []];
            $groups[$group]['routes']++;
            $groups[$group][$isMutation ? 'mutations' : 'read']++;
            $groups[$group]['permissions'] = array_values(array_unique(array_merge($groups[$group]['permissions'], $permissions)));

            $rows[] = [
                'name' => $name,
                'uri' => $uri,
                'methods' => $methods,
                'action' => $action,
                'middleware' => $middleware,
                'permissions' => $permissions,
                'authenticated' => $hasAuth,
                'signed' => $hasSignedRequest,
                'public_mutation_allowlisted' => $isPublicAllowlisted,
                'authorized' => $hasAuthorization,
                'mutation' => $isMutation,
                'controller_exists' => $sourceSecurity['controller_exists'],
                'method_exists' => $sourceSecurity['method_exists'],
            ];
        }

        foreach ($seenNames as $name => $uris) {
            if (count($uris) > 1) {
                $this->issue('high', 'routes', 'duplicate_route_name', "Route name [{$name}] is registered more than once.", ['uris' => $uris]);
            }
        }

        foreach ($seenSignatures as $signature => $names) {
            if (count($names) > 1) {
                $this->issue('medium', 'routes', 'duplicate_route_signature', "Route signature [{$signature}] is registered more than once.", ['routes' => $names]);
            }
        }

        return [
            'total' => count($rows),
            'mutations' => count(array_filter($rows, fn (array $row): bool => $row['mutation'])),
            'unnamed' => count(array_filter($rows, fn (array $row): bool => $row['name'] === null)),
            'permissions_referenced' => array_keys($routePermissions),
            'groups' => array_values($groups),
            'items' => $rows,
        ];
    }

    /** @param array<int, string> $middleware */
    private function permissionsFromMiddleware(array $middleware): array
    {
        $permissions = [];
        foreach ($middleware as $entry) {
            $entry = (string) $entry;
            if (preg_match('/^can:([^,]+)/', $entry, $match)) {
                $permissions[] = trim($match[1]);
            } elseif (preg_match('/^permission:(.+)$/', $entry, $match)) {
                foreach (preg_split('/[|,]/', $match[1]) ?: [] as $permission) {
                    if (trim($permission) !== '') {
                        $permissions[] = trim($permission);
                    }
                }
            }
        }
        return array_values(array_unique($permissions));
    }

    /** @param array<int, string> $middleware @param array<int, string> $needles */
    private function middlewareContains(array $middleware, array $needles): bool
    {
        foreach ($middleware as $entry) {
            foreach ($needles as $needle) {
                if ($entry === $needle || str_starts_with((string) $entry, $needle.':') || str_contains((string) $entry, '\\'.$needle)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function looksLikeStateChangingGet(string $action, ?string $routeName): bool
    {
        $actionMethod = str_contains($action, '@')
            ? substr($action, strrpos($action, '@') + 1)
            : class_basename($action);
        $routeSeparator = $routeName ? strrpos($routeName, '.') : false;
        $routeLeaf = ! $routeName
            ? ''
            : ($routeSeparator === false ? $routeName : substr($routeName, $routeSeparator + 1));
        $candidate = $actionMethod.' '.$routeLeaf;
        $candidate = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $candidate) ?? $candidate;
        $tokens = preg_split('/[^a-z0-9]+/i', strtolower($candidate), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $mutationVerbs = [
            'store', 'save', 'update', 'destroy', 'delete', 'remove', 'approve',
            'reject', 'cancel', 'pay', 'post', 'close', 'open', 'toggle',
            'dispatch', 'receive', 'complete', 'submit', 'verify', 'refund',
            'adjust', 'assign', 'start', 'release', 'sync', 'apply', 'void',
            'correct', 'transition', 'regenerate', 'upload',
        ];

        return array_intersect($tokens, $mutationVerbs) !== [];
    }

    private function actionSourceMatches(string $action, string $pattern): bool
    {
        if ($action === 'Closure' || ! str_contains($action, '@')) {
            return false;
        }

        [$class, $method] = explode('@', $action, 2);
        if (! class_exists($class) || ! method_exists($class, $method)) {
            return false;
        }

        try {
            return preg_match($pattern, $this->methodSource(new ReflectionMethod($class, $method))) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Detect authorization performed directly by an action or by a local
     * controller helper such as ensureAdmin() or authorizeView().
     *
     * @param array<string, true> $visited
     */
    private function methodAuthorizes(ReflectionMethod $method, int $depth = 0, array $visited = []): bool
    {
        $key = $method->getDeclaringClass()->getName().'@'.$method->getName();

        if (isset($visited[$key]) || $depth > 3) {
            return false;
        }

        $visited[$key] = true;
        $source = $this->methodSource($method);

        if (preg_match(
            '/(?:->authorize\s*\(|authorizeForUser\s*\(|Gate::|Policy|abort_if\s*\(|abort_unless\s*\(|throw_if\s*\(|throw_unless\s*\(|->notifications\s*\(|->unreadNotifications\s*\()/i',
            $source
        ) === 1) {
            return true;
        }

        if ($depth === 3) {
            return false;
        }

        preg_match_all('/\$this\s*->\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $source, $matches);
        $class = $method->getDeclaringClass()->getName();

        foreach (array_unique($matches[1] ?? []) as $helper) {
            if ($helper === $method->getName() || ! method_exists($class, $helper)) {
                continue;
            }

            try {
                $helperMethod = new ReflectionMethod($class, $helper);

                if (
                    $helperMethod->getDeclaringClass()->getName() === $class
                    && $this->methodAuthorizes($helperMethod, $depth + 1, $visited)
                ) {
                    return true;
                }
            } catch (Throwable) {
                // Ignore dynamic/proxy helpers and continue inspecting safely.
            }
        }

        return false;
    }

    /**
     * Detect validation performed directly by an action or by a local
     * controller helper such as validated() or validateStation().
     *
     * @param array<string, true> $visited
     */
    private function methodValidates(ReflectionMethod $method, int $depth = 0, array $visited = []): bool
    {
        $key = $method->getDeclaringClass()->getName().'@'.$method->getName();

        if (isset($visited[$key]) || $depth > 3) {
            return false;
        }

        $visited[$key] = true;
        $source = $this->methodSource($method);

        if (preg_match(
            '/(?:->validate\s*\(|Validator::make\s*\(|->validated\s*\()/i',
            $source
        ) === 1) {
            return true;
        }

        if ($depth === 3) {
            return false;
        }

        preg_match_all('/\$this\s*->\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $source, $matches);
        $class = $method->getDeclaringClass()->getName();

        foreach (array_unique($matches[1] ?? []) as $helper) {
            if ($helper === $method->getName() || ! method_exists($class, $helper)) {
                continue;
            }

            try {
                $helperMethod = new ReflectionMethod($class, $helper);

                if (
                    $helperMethod->getDeclaringClass()->getName() === $class
                    && $this->methodValidates($helperMethod, $depth + 1, $visited)
                ) {
                    return true;
                }
            } catch (Throwable) {
                // Ignore dynamic/proxy helpers and continue inspecting safely.
            }
        }

        return false;
    }

    /**
     * Validation is required when an action consumes client-controlled input.
     * Route model bindings and mutation method names alone are not request
     * payloads, so they must not generate validation false positives.
     */
    private function usesRequestInput(ReflectionMethod $method, string $source): bool
    {
        $inputMethods = '(?:all|collect|input|only|except|get|string|integer|float|boolean|date|enum|enums|file|hasFile|filled|has|missing|query|post|json|cookie|merge|mergeIfMissing|replace|validate|validateWithBag)';

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type ? ltrim((string) $type, '?') : '';

            if ($typeName === '' || ! is_a($typeName, \Illuminate\Http\Request::class, true)) {
                continue;
            }

            $variable = preg_quote($parameter->getName(), '/');

            if (preg_match('/\$'.$variable.'\s*->\s*'.$inputMethods.'\s*\(/i', $source) === 1) {
                return true;
            }
        }

        return preg_match(
            '/request\s*\(\s*[\'\"]|request\s*\(\s*\)\s*->\s*'.$inputMethods.'\s*\(/i',
            $source
        ) === 1;
    }

    /** @return array{controller_exists: bool, method_exists: bool, authorizes: bool} */
    private function actionSecurity(string $action): array
    {
        if ($action === 'Closure') {
            return ['controller_exists' => true, 'method_exists' => true, 'authorizes' => false];
        }

        [$class, $method] = str_contains($action, '@') ? explode('@', $action, 2) : [$action, '__invoke'];
        $controllerExists = class_exists($class);
        $methodExists = $controllerExists && method_exists($class, $method);
        $authorizes = false;

        if (! $controllerExists) {
            $this->issue('critical', 'routes', 'controller_missing', "Controller [{$class}] does not autoload.", ['action' => $action]);
        } elseif (! $methodExists) {
            $this->issue('critical', 'routes', 'controller_method_missing', "Controller method [{$action}] does not exist.");
        } else {
            try {
                $reflectionMethod = new ReflectionMethod($class, $method);
                $authorizes = $this->methodAuthorizes($reflectionMethod);

                if (! $authorizes) {
                    $reflection = new ReflectionClass($class);
                    $controllerFile = $reflection->getFileName();
                    $controllerSource = $controllerFile ? File::get($controllerFile) : '';
                    $authorizes = preg_match('/(?:authorizeResource\s*\(|middleware\s*\(\s*[\'\"](?:can|permission):|new\s+Middleware\s*\(\s*[\'\"](?:can|permission):)/i', $controllerSource) === 1;
                }
            } catch (Throwable) {
                // Reflection can fail for internal/proxy actions.
            }
        }

        return [
            'controller_exists' => $controllerExists,
            'method_exists' => $methodExists,
            'authorizes' => $authorizes,
        ];
    }

    /** @return array<string, mixed> */
    private function auditAccessControl(): array
    {
        $required = ['users', 'roles', 'permissions', 'model_has_roles', 'role_has_permissions'];
        foreach ($required as $table) {
            if (! Schema::hasTable($table)) {
                $this->issue('critical', 'access_control', 'rbac_table_missing', "RBAC table [{$table}] is missing.");
                return ['available' => false];
            }
        }

        $permissions = DB::table('permissions')->select(['id', 'name', 'guard_name'])->get();
        $roles = DB::table('roles')->select(['id', 'name', 'guard_name'])->get();
        $userColumns = Schema::getColumnListing('users');
        $userSelect = ['id'];
        foreach (['is_active', 'status'] as $column) {
            if (in_array($column, $userColumns, true)) {
                $userSelect[] = $column;
            }
        }
        $users = DB::table('users')->select($userSelect)->get();
        

        foreach ($permissions as $permission) {
            $this->permissionIds[(string) $permission->name] = (int) $permission->id;
        }

        $rolePermissionRows = DB::table('role_has_permissions')->get();
        $rolePermissions = [];
        foreach ($rolePermissionRows as $row) {
            $rolePermissions[(int) $row->role_id][(int) $row->permission_id] = true;
        }

        $roleNames = [];
        foreach ($roles as $role) {
            $roleNames[(int) $role->id] = (string) $role->name;
            if (empty($rolePermissions[(int) $role->id])) {
                $this->issue('high', 'access_control', 'role_without_permissions', "Role [{$role->name}] has no permissions.");
            }
        }

        $userModel = ltrim((string) config('auth.providers.users.model', 'App\\Models\\User'), '\\');
        $modelRoleQuery = DB::table('model_has_roles');
        if (Schema::hasColumn('model_has_roles', 'model_type')) {
            $modelRoleQuery->where('model_type', $userModel);
        }
        $modelRoleRows = $modelRoleQuery->get();
        $userRoleIds = [];
        foreach ($modelRoleRows as $row) {
            $userId = (int) $row->model_id;
            $roleId = (int) $row->role_id;
            $userRoleIds[$userId][$roleId] = true;
            $this->userRoles[$userId][] = $roleNames[$roleId] ?? "role#{$roleId}";
        }

        $directPermissions = [];
        if (Schema::hasTable('model_has_permissions')) {
            $directPermissionQuery = DB::table('model_has_permissions');
            if (Schema::hasColumn('model_has_permissions', 'model_type')) {
                $directPermissionQuery->where('model_type', $userModel);
            }
            foreach ($directPermissionQuery->get() as $row) {
                $directPermissions[(int) $row->model_id][(int) $row->permission_id] = true;
            }
        }

        $permissionNamesById = $permissions->mapWithKeys(fn (object $permission): array => [(int) $permission->id => (string) $permission->name])->all();

        foreach ($users as $user) {
            $userId = (int) $user->id;
            $active = true;
            if (property_exists($user, 'is_active')) {
                $active = (bool) $user->is_active;
            } elseif (property_exists($user, 'status')) {
                $active = ! in_array(strtolower((string) $user->status), ['inactive', 'disabled', 'blocked', 'terminated', 'deleted'], true);
            }
            $this->userIsActive[$userId] = $active;
            $this->effectiveUserPermissions[$userId] ??= [];
            $effectiveIds = $directPermissions[$userId] ?? [];
            foreach (array_keys($userRoleIds[$userId] ?? []) as $roleId) {
                foreach (array_keys($rolePermissions[$roleId] ?? []) as $permissionId) {
                    $effectiveIds[$permissionId] = true;
                }
            }

            foreach (array_keys($effectiveIds) as $permissionId) {
                if (isset($permissionNamesById[$permissionId])) {
                    $this->effectiveUserPermissions[$userId][$permissionNamesById[$permissionId]] = true;
                }
            }

            $this->userLocations[$userId] = $this->resolveUserLocationIds($userId);

            if ($active && empty($userRoleIds[$userId])) {
                $this->issue('critical', 'access_control', 'user_without_role', "User #{$userId} has no role.", ['user_id' => $userId]);
            }
        }

        $routePermissions = collect(Route::getRoutes())
            ->flatMap(fn ($route): array => $this->permissionsFromMiddleware($route->gatherMiddleware()))
            ->unique()->values();

        foreach ($routePermissions as $permission) {
            if (! isset($this->permissionIds[$permission])) {
                $this->issue('critical', 'access_control', 'route_permission_missing', "Route permission [{$permission}] does not exist in the database.");
            }
        }

        foreach ($this->permissionIds as $permission => $permissionId) {
            $hasRecipient = collect($this->effectiveUserPermissions)->contains(
                fn (array $set, int $userId): bool => ($this->userIsActive[$userId] ?? true) && isset($set[$permission])
            );
            if (! $hasRecipient) {
                $this->issue('medium', 'access_control', 'permission_without_active_users', "Permission [{$permission}] is not effectively assigned to any active user.");
            }
        }

        $adminRoles = $roles->filter(fn (object $role): bool => preg_match('/^(?:admin|super[ _-]?admin|مدير النظام)$/i', (string) $role->name) === 1);
        foreach ($adminRoles as $adminRole) {
            $assigned = count($rolePermissions[(int) $adminRole->id] ?? []);
            if ($assigned !== $permissions->count()) {
                $this->issue('high', 'access_control', 'admin_permission_drift', "Admin role [{$adminRole->name}] does not own every permission.", ['assigned' => $assigned, 'total' => $permissions->count()]);
            }
        }

        $permissionMatrix = $permissions->map(function (object $permission) use ($roles, $rolePermissions): array {
            $permissionId = (int) $permission->id;
            $eligibleUserIds = collect($this->effectiveUserPermissions)
                ->filter(fn (array $set, int $userId): bool => ($this->userIsActive[$userId] ?? true) && isset($set[(string) $permission->name]))
                ->keys()->map(fn ($id): int => (int) $id)->values()->all();

            return [
                'name' => (string) $permission->name,
                'guard' => (string) $permission->guard_name,
                'roles' => $roles->filter(fn (object $role): bool => isset($rolePermissions[(int) $role->id][$permissionId]))
                    ->pluck('name')->values()->all(),
                'eligible_user_count' => count($eligibleUserIds),
                'eligible_users' => array_map(fn (int $id): string => $this->userLabel($id), $eligibleUserIds),
            ];
        })->values()->all();

        return [
            'available' => true,
            'permissions_total' => $permissions->count(),
            'roles_total' => $roles->count(),
            'users_total' => $users->count(),
            'users_without_roles' => $users->filter(fn (object $user): bool => ($this->userIsActive[(int) $user->id] ?? true) && empty($userRoleIds[(int) $user->id]))->pluck('id')->values()->all(),
            'permissions' => $permissionMatrix,
            'roles' => $roles->map(fn (object $role): array => [
                'id' => (int) $role->id,
                'name' => (string) $role->name,
                'permission_count' => count($rolePermissions[(int) $role->id] ?? []),
                'user_count' => collect($userRoleIds)->filter(fn (array $ids): bool => isset($ids[(int) $role->id]))->count(),
            ])->values()->all(),
            'users' => $users->map(fn (object $user): array => [
                'id' => (int) $user->id,
                'label' => $this->userLabel((int) $user->id),
                'roles' => $this->userRoles[(int) $user->id] ?? [],
                'active' => $this->userIsActive[(int) $user->id] ?? true,
                'effective_permission_count' => count($this->effectiveUserPermissions[(int) $user->id] ?? []),
                'location_ids' => $this->userLocations[(int) $user->id] ?? [],
            ])->values()->all(),
        ];
    }

    /** @return array<int, int> */
    private function resolveUserLocationIds(int $userId): array
    {
        $locations = [];

        try {
            $userColumns = Schema::getColumnListing('users');
            foreach (['primary_location_id', 'location_id'] as $column) {
                if (in_array($column, $userColumns, true)) {
                    $value = DB::table('users')->where('id', $userId)->value($column);
                    if ($value) {
                        $locations[] = (int) $value;
                    }
                }
            }

            if (Schema::hasTable('employee_locations') && Schema::hasTable('employees')) {
                $employeeColumns = Schema::getColumnListing('employees');
                $pivotColumns = Schema::getColumnListing('employee_locations');
                if (in_array('user_id', $employeeColumns, true) && in_array('employee_id', $pivotColumns, true) && in_array('location_id', $pivotColumns, true)) {
                    $employeeIds = DB::table('employees')->where('user_id', $userId)->pluck('id');
                    $locations = array_merge($locations, DB::table('employee_locations')->whereIn('employee_id', $employeeIds)->pluck('location_id')->map(fn ($id): int => (int) $id)->all());
                }
            }
        } catch (Throwable) {
            return array_values(array_unique($locations));
        }

        return array_values(array_unique($locations));
    }

    private function userLabel(int $userId): string
    {
        if (! (bool) $this->option('include-sensitive')) {
            return "user#{$userId}";
        }

        if (isset($this->userLabelCache[$userId])) {
            return $this->userLabelCache[$userId];
        }

        try {
            $columns = Schema::getColumnListing('users');
            $displayColumn = collect(['name', 'username', 'email'])->first(fn (string $column): bool => in_array($column, $columns, true));
            return $this->userLabelCache[$userId] = $displayColumn
                ? (string) DB::table('users')->where('id', $userId)->value($displayColumn)
                : "user#{$userId}";
        } catch (Throwable) {
            return "user#{$userId}";
        }
    }

    /** @return array<string, mixed> */
    private function auditCode(): array
    {
        $componentDirectories = [
            'controllers' => app_path('Http/Controllers'),
            'middleware' => app_path('Http/Middleware'),
            'requests' => app_path('Http/Requests'),
            'models' => app_path('Models'),
            'services' => app_path('Services'),
            'policies' => app_path('Policies'),
            'events' => app_path('Events'),
            'listeners' => app_path('Listeners'),
            'notifications' => app_path('Notifications'),
            'commands' => app_path('Console/Commands'),
        ];

        $inventory = [];
        $autoloadFailures = [];

        foreach ($componentDirectories as $type => $directory) {
            $files = $this->phpFiles($directory);
            $failures = 0;

            foreach ($files as $path) {
                $source = File::get($path);
                $class = $this->extractFqcn($source);
                $expectedClass = $this->expectedAppClassForPath($path);

                if ($class && $expectedClass && ltrim($class, '\\') !== ltrim($expectedClass, '\\')) {
                    $failures++;
                    $autoloadFailures[] = [
                        'type' => $type,
                        'file' => $this->relativePath($path),
                        'declared_class' => $class,
                        'expected_class' => $expectedClass,
                    ];
                    $this->issue('high', 'autoload', 'psr4_path_mismatch', "File [{$this->relativePath($path)}] should declare [{$expectedClass}], but declares [{$class}].");
                } elseif (! $this->symbolExists($class)) {
                    $failures++;
                    $autoloadFailures[] = ['type' => $type, 'file' => $this->relativePath($path), 'declared_class' => $class];
                    $this->issue('high', 'autoload', 'class_does_not_autoload', "Class [{$class}] does not autoload from [{$this->relativePath($path)}].");
                }

                if (realpath($path) !== realpath(__FILE__)) {
                    $this->scanDangerousSource($path, $source);
                }
            }

            $inventory[$type] = ['count' => count($files), 'autoload_failures' => $failures];
        }

        $controllerDetails = $this->auditControllers();
        $bladeIssues = $this->auditBladeViews();

        return [
            'components' => $inventory,
            'autoload_failures' => $autoloadFailures,
            'controllers' => $controllerDetails,
            'blade_issues' => $bladeIssues,
        ];
    }

    /** @return array<string, mixed> */
    private function auditEnums(): array
    {
        $items = [];
        $failures = 0;

        foreach ($this->phpFiles(app_path('Enums')) as $path) {
            $source = File::get($path);
            $class = $this->extractFqcn($source);
            $autoloads = $class && function_exists('enum_exists') && enum_exists($class);
            $cases = [];

            if ($autoloads) {
                try {
                    foreach ($class::cases() as $case) {
                        $cases[] = [
                            'name' => $case->name,
                            'value' => property_exists($case, 'value') ? $case->value : $case->name,
                        ];
                    }
                } catch (Throwable $e) {
                    $autoloads = false;
                    $this->issue('high', 'autoload', 'enum_inspection_failed', "Enum [{$class}] could not be inspected.", ['error' => $e->getMessage()]);
                }
            }

            if (! $autoloads) {
                $failures++;
                $this->issue('high', 'autoload', 'enum_does_not_autoload',
                    'Enum from ['.$this->relativePath($path).'] does not autoload.', ['class' => $class]);
            }

            $items[] = [
                'class' => $class,
                'file' => $this->relativePath($path),
                'autoloads' => (bool) $autoloads,
                'cases' => $cases,
            ];
        }

        return ['total' => count($items), 'autoload_failures' => $failures, 'items' => $items];
    }

    /** @return array<string, mixed> */
    private function auditModules(): array
    {
        $registryClass = 'App\\Support\\ModuleRegistry';
        $registryRows = [];
        $problems = [];

        if (! class_exists($registryClass) || ! method_exists($registryClass, 'modules')) {
            $this->issue('high', 'modules', 'module_registry_missing',
                'App\\Support\\ModuleRegistry::modules() is missing or does not autoload.');
            return ['registry_total' => 0, 'database_total' => $this->tableCounts['modules'] ?? null, 'dependency_problems' => 1, 'items' => [], 'problems' => ['registry_missing']];
        }

        try {
            $raw = $registryClass::modules();
            $modules = is_iterable($raw) ? $raw : (array) $raw;
            foreach ($modules as $key => $module) {
                $module = is_array($module) ? $module : (array) $module;
                $code = (string) ($module['code'] ?? (is_string($key) ? $key : ''));
                if ($code === '') {
                    $problems[] = 'A registry entry has no code.';
                    continue;
                }

                $dependencies = collect($module['dependencies'] ?? $module['depends_on'] ?? [])
                    ->filter(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '')
                    ->map(fn ($value): string => trim((string) $value))->values()->all();
                $implemented = (bool) ($module['implemented'] ?? $module['is_implemented'] ?? true);
                $registryRows[$code] = [
                    'code' => $code,
                    'name' => (string) ($module['name'] ?? $code),
                    'type' => (string) ($module['type'] ?? ''),
                    'implemented' => $implemented,
                    'dependencies' => $dependencies,
                    'database_record' => false,
                ];
            }
        } catch (Throwable $e) {
            $this->issue('high', 'modules', 'module_registry_failed', 'Module registry could not be inspected.', ['error' => $e->getMessage()]);
            return ['registry_total' => 0, 'database_total' => $this->tableCounts['modules'] ?? null, 'dependency_problems' => 1, 'items' => [], 'problems' => [$e->getMessage()]];
        }

        $databaseCodes = [];
        if ($this->hasColumns('modules', ['code'])) {
            $databaseCodes = DB::table('modules')->pluck('code')->map(fn ($code): string => (string) $code)->all();
        }

        foreach ($registryRows as $code => &$row) {
            $row['database_record'] = in_array($code, $databaseCodes, true);
            if (! $row['database_record']) {
                $problems[] = "Registry module [{$code}] has no modules-table record.";
            }
            foreach ($row['dependencies'] as $dependency) {
                if (! isset($registryRows[$dependency])) {
                    $problems[] = "Module [{$code}] depends on missing registry module [{$dependency}].";
                } elseif ($row['implemented'] && ! $registryRows[$dependency]['implemented']) {
                    $problems[] = "Implemented module [{$code}] depends on unimplemented module [{$dependency}].";
                }
            }
        }
        unset($row);

        foreach (array_diff($databaseCodes, array_keys($registryRows)) as $code) {
            $problems[] = "Database module [{$code}] has no registry definition.";
        }

        foreach (array_values(array_unique($problems)) as $problem) {
            $this->issue('high', 'modules', 'module_registry_inconsistent', $problem);
        }

        return [
            'registry_total' => count($registryRows),
            'database_total' => count($databaseCodes),
            'dependency_problems' => count(array_unique($problems)),
            'items' => array_values($registryRows),
            'problems' => array_values(array_unique($problems)),
        ];
    }

    /** @return array<string, mixed> */
    private function auditReleaseReadiness(): array
    {
        $serviceClass = 'App\\Services\\ReleaseReadinessService';
        if (! class_exists($serviceClass) || ! method_exists($serviceClass, 'report')) {
            $this->issue('medium', 'release', 'release_readiness_service_missing',
                'ReleaseReadinessService::report() is unavailable; the rest of the audit still completed.');
            return ['available' => false, 'report' => null, 'error' => 'service_missing'];
        }

        try {
            return ['available' => true, 'report' => app($serviceClass)->report(), 'error' => null];
        } catch (Throwable $e) {
            $this->issue('high', 'release', 'release_readiness_failed', 'Release readiness service failed.', ['error' => $e->getMessage()]);
            return ['available' => true, 'report' => null, 'error' => $e->getMessage()];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function auditControllers(): array
    {
        $routedActions = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if ($action !== 'Closure' && str_contains($action, '@')) {
                $routedActions[$action][] = $route->getName();
            }
        }

        $testSource = $this->combinedSource(base_path('tests'));
        $details = [];

        foreach ($this->phpFiles(app_path('Http/Controllers')) as $path) {
            $source = File::get($path);
            $class = $this->extractFqcn($source);
            if (! $class || ! class_exists($class)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($class);
            } catch (Throwable) {
                continue;
            }

            $actions = [];
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class || str_starts_with($method->getName(), '__')) {
                    continue;
                }

                $key = $class.'@'.$method->getName();
                if (! isset($routedActions[$key])) {
                    continue;
                }

                $methodSource = $this->methodSource($method);
                $mutation = preg_match('/(?:store|save|update|delete|destroy|approve|reject|cancel|post|pay|close|open|toggle|dispatch|receive|complete|submit|verify|refund|adjust|assign|start|release|sync|apply|void|correct|transition|regenerate)/i', $method->getName()) === 1;
                $formRequest = collect($method->getParameters())->contains(function ($parameter): bool {
                    $type = $parameter->getType();
                    return $type && str_contains((string) $type, 'Request') && (string) $type !== 'Illuminate\\Http\\Request';
                });
                $inlineValidation = $this->methodValidates($method);
                $usesRequestInput = $this->usesRequestInput($method, $methodSource);
                $authorizes = $this->methodAuthorizes($method);
                $tested = str_contains($testSource, class_basename($class)) || str_contains($testSource, (string) ($routedActions[$key][0] ?? ''));

                if ($mutation && $usesRequestInput && ! $formRequest && ! $inlineValidation) {
                    $this->issue('high', 'validation', 'mutation_without_validation', "[{$key}] has no detected FormRequest or inline validation.");
                }

                if ($mutation && ! $tested) {
                    $this->issue('medium', 'test_coverage', 'untested_mutation', "[{$key}] has no directly detected test reference.");
                }

                $actions[] = [
                    'method' => $method->getName(),
                    'routes' => $routedActions[$key],
                    'mutation' => $mutation,
                    'form_request' => $formRequest,
                    'inline_validation' => $inlineValidation,
                    'request_input_detected' => $usesRequestInput,
                    'inline_authorization' => $authorizes,
                    'related_test_detected' => $tested,
                ];
            }

            if ($actions !== []) {
                preg_match_all('/\bview\s*\(\s*["\']([^"\']+)["\']/', $source, $viewMatches);
                preg_match_all('/DB::table\s*\(\s*["\']([^"\']+)["\']|->(?:from|join|leftJoin|rightJoin)\s*\(\s*["\']([^"\']+)["\']/', $source, $tableMatches);
                $directTables = collect(array_merge($tableMatches[1] ?? [], $tableMatches[2] ?? []))
                    ->filter()->map(fn (string $table): string => preg_replace('/\s+as\s+.*/i', '', trim($table)) ?: trim($table))
                    ->unique()->values()->all();

                $details[] = [
                    'class' => $class,
                    'file' => $this->relativePath($path),
                    'services' => $this->sourceImports($source, 'App\\Services\\'),
                    'models' => $this->sourceImports($source, 'App\\Models\\'),
                    'events' => $this->sourceImports($source, 'App\\Events\\'),
                    'notifications' => $this->sourceImports($source, 'App\\Notifications\\'),
                    'views' => array_values(array_unique($viewMatches[1] ?? [])),
                    'direct_tables' => $directTables,
                    'actions' => $actions,
                ];
            }
        }

        return $details;
    }

    /** @return array<int, string> */
    private function sourceImports(string $source, string $prefix): array
    {
        preg_match_all('/^use\s+('.preg_quote($prefix, '/').'[A-Za-z0-9_\\\\]+)\s*;/m', $source, $matches);
        return array_values(array_unique($matches[1] ?? []));
    }

    private function scanDangerousSource(string $path, string $source): void
    {
        $relative = $this->relativePath($path);
        $checks = [
            ['critical', 'mass_assignment_all', '/(?:create|update|fill|forceFill)\s*\(\s*\$request->all\s*\(\s*\)/', 'Mass assignment uses $request->all().'],
            ['critical', 'command_execution', '/\b(?:shell_exec|passthru|proc_open|popen)\s*\(/', 'Operating-system command execution detected.'],
            ['high', 'unsafe_unserialize', '/\bunserialize\s*\(/', 'PHP unserialize() detected; verify trusted input and allowed_classes.'],
            ['high', 'request_in_raw_sql', '/DB::raw\s*\([^)]*\$request/s', 'Request data may be interpolated into raw SQL.'],
            ['medium', 'debug_statement', '/\b(?:dd|dump|var_dump)\s*\(/', 'Debug output statement remains in application code.'],
            ['medium', 'env_outside_config', '/\benv\s*\(/', 'env() is used outside config; cached configuration can change behavior.'],
        ];

        foreach ($checks as [$severity, $code, $pattern, $message]) {
            if (preg_match($pattern, $source) === 1 && ! ($code === 'env_outside_config' && str_starts_with($relative, 'config/'))) {
                $this->issue($severity, 'code_security', $code, $message, ['file' => $relative]);
            }
        }
    }

    /** @return array<int, array<string, string>> */
    private function auditBladeViews(): array
    {
        $results = [];
        foreach ($this->phpFiles(resource_path('views')) as $path) {
            $source = File::get($path);
            $relative = $this->relativePath($path);

            if (preg_match('/\{!!\s*\$(?!errors)/', $source) === 1) {
                $results[] = ['severity' => 'high', 'file' => $relative, 'issue' => 'Unescaped Blade output'];
                $this->issue('high', 'xss', 'unescaped_blade_output', 'Unescaped Blade output requires manual XSS review.', ['file' => $relative]);
            }

            if (preg_match('/<form[^>]*method=["\']?(?:post|put|patch|delete)["\']?[^>]*>/i', $source) === 1 && ! str_contains($source, '@csrf')) {
                $results[] = ['severity' => 'high', 'file' => $relative, 'issue' => 'Mutation form without @csrf'];
                $this->issue('high', 'csrf', 'blade_form_without_csrf', 'Mutation form has no detected @csrf directive.', ['file' => $relative]);
            }
        }
        return $results;
    }

    /** @return array<string, mixed> */
    private function auditNotifications(): array
    {
        $notificationClasses = [];
        foreach ($this->phpFiles(app_path('Notifications')) as $path) {
            $source = File::get($path);
            $class = $this->extractFqcn($source);
            if (! $class) {
                continue;
            }

            preg_match('/function\s+via\s*\([^)]*\)\s*(?::\s*array)?\s*\{(.*?)\}/s', $source, $viaMatch);
            preg_match_all('/["\'](mail|database|broadcast|vonage|slack)["\']/', $viaMatch[1] ?? '', $channelMatches);

            $notificationClasses[$class] = [
                'class' => $class,
                'file' => $this->relativePath($path),
                'channels_detected' => array_values(array_unique($channelMatches[1] ?? [])),
                'references' => $this->sourceReferenceCount(class_basename($class), [app_path('Notifications')]),
            ];
        }

        $events = [];
        foreach ($this->phpFiles(app_path('Events')) as $path) {
            $class = $this->extractFqcn(File::get($path));
            if (! $class) {
                continue;
            }

            $listenerCount = 0;
            try {
                $listenerCount = count(app('events')->getListeners($class));
            } catch (Throwable) {
                // Keep zero and report through configured notification rules.
            }

            $events[$class] = ['class' => $class, 'listener_count' => $listenerCount];
        }

        $delivery = $this->notificationDeliveryEvidence();
        $deliveryByType = collect($delivery['by_type'] ?? [])->keyBy('type');
        $allLocationIds = [];
        if (Schema::hasTable('locations')) {
            $locationColumns = Schema::getColumnListing('locations');
            $locationQuery = DB::table('locations');
            if (in_array('deleted_at', $locationColumns, true)) {
                $locationQuery->whereNull('deleted_at');
            }
            if (in_array('is_active', $locationColumns, true)) {
                $locationQuery->where('is_active', true);
            }
            $allLocationIds = $locationQuery->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }
        $rules = [];

        foreach ($this->auditSetting('notification_rules', []) as $ruleKey => $rule) {
            $event = $rule['event'] ?? null;
            $listener = $rule['listener'] ?? null;
            $notification = $rule['notification'] ?? null;
            $permissions = array_values(array_unique($rule['permissions_any'] ?? []));
            $existingPermissions = array_values(array_filter(
                $permissions,
                fn (string $permission): bool => isset($this->permissionIds[$permission])
            ));
            $missingPermissions = array_values(array_diff($permissions, $existingPermissions));
            $eligibleUsers = $this->eligibleUsersForAnyPermission($existingPermissions);

            if ($event && ! class_exists($event)) {
                $this->issue('critical', 'notifications', 'event_missing', "Configured event [{$event}] does not autoload.", ['rule' => $ruleKey]);
            }

            if ($listener && ! class_exists($listener)) {
                $this->issue('critical', 'notifications', 'listener_missing', "Configured listener [{$listener}] does not autoload.", ['rule' => $ruleKey]);
            }

            if ($notification && ! class_exists($notification)) {
                $this->issue('high', 'notifications', 'notification_missing', "Configured notification [{$notification}] does not autoload.", ['rule' => $ruleKey]);
            }

            $registeredListeners = $event ? ($events[$event]['listener_count'] ?? 0) : null;
            if ($event && class_exists($event) && $registeredListeners === 0) {
                $this->issue('critical', 'notifications', 'event_without_listener', "Event [{$event}] has no registered listener.", ['rule' => $ruleKey]);
            }

            if ($permissions !== [] && $existingPermissions === []) {
                $this->issue(
                    'high',
                    'notifications',
                    'notification_permission_set_missing',
                    "Notification rule [{$ruleKey}] has no existing permission from its permissions_any set.",
                    ['configured_permissions' => $permissions]
                );
            } elseif ($missingPermissions !== []) {
                $this->issue(
                    'low',
                    'notifications',
                    'notification_permission_alternative_missing',
                    "Notification rule [{$ruleKey}] contains optional permission alternatives that are not registered.",
                    [
                        'missing_permissions' => $missingPermissions,
                        'existing_permissions' => $existingPermissions,
                    ]
                );
            }

            if ($eligibleUsers === []) {
                $this->issue('critical', 'notifications', 'notification_without_recipients', "Notification rule [{$ruleKey}] has no eligible user.", ['permissions' => $permissions]);
            }

            $listenerPermissionEvidence = [];
            $detectedNotificationClasses = $notification ? [$notification] : [];
            if ($listener && class_exists($listener)) {
                try {
                    $file = (new ReflectionClass($listener))->getFileName();
                    $source = $file ? File::get($file) : '';
                    foreach ($permissions as $permission) {
                        if (str_contains($source, $permission)) {
                            $listenerPermissionEvidence[] = $permission;
                        }
                    }

                    if ($permissions !== [] && $listenerPermissionEvidence === [] && ! preg_match('/NotificationDispatcher|permission\s*\(|hasPermissionTo|can\s*\(/', $source)) {
                        $this->issue('high', 'notifications', 'listener_scope_not_detected', "Listener [{$listener}] has no detected permission-aware recipient selection.", ['rule' => $ruleKey]);
                    }

                    foreach (array_keys($notificationClasses) as $notificationClass) {
                        if (str_contains($source, class_basename($notificationClass))) {
                            $detectedNotificationClasses[] = $notificationClass;
                        }
                    }
                } catch (Throwable) {
                    // Autoload issue is already reported elsewhere.
                }
            }
            $detectedNotificationClasses = array_values(array_unique($detectedNotificationClasses));

            $byLocation = [];
            foreach ($eligibleUsers as $userId) {
                foreach ($this->userLocations[$userId] ?? [] as $locationId) {
                    $byLocation[$locationId][] = $userId;
                }
            }

            $globalEligibleUsers = collect($eligibleUsers)->filter(function (int $userId): bool {
                if (($this->userLocations[$userId] ?? []) !== []) {
                    return false;
                }
                return collect($this->userRoles[$userId] ?? [])->contains(
                    fn (string $role): bool => preg_match('/^(?:admin|super[ _-]?admin|مدير النظام)$/i', $role) === 1
                );
            })->values()->all();

            $directUncoveredLocations = [];
            $uncoveredLocations = [];
            if (in_array($rule['scope'] ?? null, ['location', 'destination_location'], true)) {
                $directUncoveredLocations = collect($allLocationIds)->reject(
                    fn (int $locationId): bool => ! empty($byLocation[$locationId])
                )->values()->all();
                $uncoveredLocations = $globalEligibleUsers === [] ? $directUncoveredLocations : [];

                if ($uncoveredLocations !== []) {
                    $this->issue('critical', 'notifications', 'location_without_notification_recipient', "Notification rule [{$ruleKey}] has locations without an eligible recipient.", [
                        'location_ids' => $uncoveredLocations,
                        'permissions' => $permissions,
                        'global_admin_candidates_without_location' => $globalEligibleUsers,
                    ]);
                } elseif ($directUncoveredLocations !== []) {
                    $this->issue('medium', 'notifications', 'location_covered_only_by_global_recipient',
                        "Notification rule [{$ruleKey}] has locations covered only by a global administrator; assign a local recipient for operational resilience.", [
                            'location_ids' => $directUncoveredLocations,
                            'global_admin_candidates_without_location' => $globalEligibleUsers,
                        ]);
                }
            }

            $historicalDeliveries = collect($detectedNotificationClasses)->sum(
                fn (string $class): int => (int) (($deliveryByType->get($class)['deliveries'] ?? 0))
            );
            $historicalRecipients = collect($detectedNotificationClasses)->sum(
                fn (string $class): int => (int) (($deliveryByType->get($class)['recipients'] ?? 0))
            );
            $historicalRecipientIds = collect($detectedNotificationClasses)
                ->flatMap(fn (string $class): array => $delivery['recipient_ids_by_type'][$class] ?? [])
                ->map(fn ($id): int => (int) $id)->unique()->values()->all();
            $eligibleWithoutHistoricalDelivery = array_values(array_diff($eligibleUsers, $historicalRecipientIds));

            if ($historicalDeliveries > 0 && $eligibleWithoutHistoricalDelivery !== []) {
                $this->issue('medium', 'notifications', 'historical_notification_coverage_gap', "Notification rule [{$ruleKey}] has historical deliveries but not to every currently eligible user.", [
                    'eligible_without_historical_delivery' => $eligibleWithoutHistoricalDelivery,
                    'note' => 'This is evidence for review, not proof of a current dispatch bug; users and retention may have changed.',
                ]);
            }

            $rules[] = [
                'rule' => $ruleKey,
                'event' => $event,
                'listener' => $listener,
                'notification' => $notification,
                'scope' => $rule['scope'] ?? 'unknown',
                'permissions_any' => $permissions,
                'existing_permissions_any' => $existingPermissions,
                'missing_permission_alternatives' => $missingPermissions,
                'registered_listener_count' => $registeredListeners,
                'eligible_user_count' => count($eligibleUsers),
                'eligible_users' => array_map(fn (int $id): string => $this->userLabel($id), $eligibleUsers),
                'global_eligible_users' => array_map(fn (int $id): string => $this->userLabel($id), $globalEligibleUsers),
                'eligible_by_location' => collect($byLocation)->map(fn (array $ids): array => [
                    'count' => count(array_unique($ids)),
                    'users' => array_map(fn (int $id): string => $this->userLabel($id), array_values(array_unique($ids))),
                ])->all(),
                'direct_uncovered_location_ids' => $directUncoveredLocations,
                'uncovered_location_ids' => $uncoveredLocations,
                'listener_permission_evidence' => $listenerPermissionEvidence,
                'notification_classes_detected' => $detectedNotificationClasses,
                'historical_deliveries' => $historicalDeliveries,
                'historical_recipients' => $historicalRecipients,
                'eligible_without_historical_delivery' => array_map(fn (int $id): string => $this->userLabel($id), $eligibleWithoutHistoricalDelivery),
            ];
        }

        return [
            'classes_total' => count($notificationClasses),
            'classes' => array_values($notificationClasses),
            'events' => array_values($events),
            'rules' => $rules,
            'delivery_evidence' => $delivery,
        ];
    }

    /** @param array<int, string> $permissions @return array<int, int> */
    private function eligibleUsersForAnyPermission(array $permissions): array
    {
        $eligible = [];
        foreach ($this->effectiveUserPermissions as $userId => $permissionSet) {
            if (! ($this->userIsActive[$userId] ?? true)) {
                continue;
            }
            $isSuperAdmin = collect($this->userRoles[$userId] ?? [])->contains(fn (string $role): bool => preg_match('/^(?:admin|super[ _-]?admin|مدير النظام)$/i', $role) === 1);
            if ($isSuperAdmin || collect($permissions)->contains(fn (string $permission): bool => isset($permissionSet[$permission]))) {
                $eligible[] = (int) $userId;
            }
        }
        return $eligible;
    }

    /** @return array<string, mixed> */
    private function notificationDeliveryEvidence(): array
    {
        if (! Schema::hasTable('notifications')) {
            $this->issue('critical', 'notifications', 'notifications_table_missing', 'The notifications table does not exist.');
            return ['available' => false];
        }

        try {
            $total = (int) DB::table('notifications')->count();
            $unread = Schema::hasColumn('notifications', 'read_at')
                ? (int) DB::table('notifications')->whereNull('read_at')->count()
                : null;
            $byType = DB::table('notifications')
                ->select('type', DB::raw('COUNT(*) AS deliveries'), DB::raw('COUNT(DISTINCT notifiable_id) AS recipients'))
                ->groupBy('type')
                ->orderByDesc('deliveries')
                ->get()
                ->map(fn (object $row): array => [
                    'type' => (string) $row->type,
                    'deliveries' => (int) $row->deliveries,
                    'recipients' => (int) $row->recipients,
                ])->all();
            $recipientIdsByType = DB::table('notifications')
                ->select(['type', 'notifiable_id'])
                ->where('notifiable_type', 'like', '%User%')
                ->distinct()
                ->get()
                ->groupBy('type')
                ->map(fn (Collection $rows): array => $rows->pluck('notifiable_id')->map(fn ($id): int => (int) $id)->unique()->values()->all())
                ->all();

            $orphaned = 0;
            if (Schema::hasTable('users')) {
                $orphaned = (int) DB::table('notifications as n')
                    ->leftJoin('users as u', 'u.id', '=', 'n.notifiable_id')
                    ->where('n.notifiable_type', 'like', '%User%')
                    ->whereNull('u.id')
                    ->count();
            }

            if ($orphaned > 0) {
                $this->issue('high', 'notifications', 'orphaned_notifications', "There are {$orphaned} notification rows pointing to missing users.");
            }

            return [
                'available' => true,
                'total' => $total,
                'unread' => $unread,
                'orphaned' => $orphaned,
                'by_type' => $byType,
                'recipient_ids_by_type' => $recipientIdsByType,
            ];
        } catch (Throwable $e) {
            $this->issue('high', 'notifications', 'notification_delivery_scan_failed', 'Could not inspect notification delivery rows.', ['error' => $e->getMessage()]);
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function auditWorkflows(): array
    {
        $rows = [];
        foreach ($this->auditSetting('workflows', []) as $key => $workflow) {
            $missingRoutes = collect($workflow['routes'] ?? [])->reject(fn (string $route): bool => isset($this->routeNames[$route]))->values()->all();
            $missingPermissions = collect($workflow['permissions'] ?? [])->reject(fn (string $permission): bool => isset($this->permissionIds[$permission]))->values()->all();
            $missingServices = collect($workflow['services'] ?? [])->reject(fn (string $service): bool => $this->symbolExists($service))->values()->all();
            $missingTables = collect($workflow['tables'] ?? [])->reject(fn (string $table): bool => Schema::hasTable($table))->values()->all();
            $emptyTables = collect($workflow['tables'] ?? [])->filter(fn (string $table): bool => ($this->tableCounts[$table] ?? null) === 0)->values()->all();

            $technicalMissing = count($missingRoutes) + count($missingPermissions) + count($missingServices) + count($missingTables);
            $status = $technicalMissing > 0 ? 'MISSING' : ($emptyTables !== [] ? 'PARTIAL' : 'READY');
            $priority = $workflow['priority'] ?? 'medium';

            if ($status !== 'READY') {
                $severity = $priority === 'critical' ? 'critical' : ($priority === 'high' ? 'high' : 'medium');
                $this->issue($severity, 'workflow', 'workflow_'.$status, "Workflow [{$key}] is {$status}.", [
                    'missing_routes' => $missingRoutes,
                    'missing_permissions' => $missingPermissions,
                    'missing_services' => $missingServices,
                    'missing_tables' => $missingTables,
                    'empty_tables' => $emptyTables,
                ]);
            }

            $rows[] = [
                'key' => $key,
                'label' => $workflow['label'] ?? $key,
                'priority' => $priority,
                'status' => $status,
                'missing_routes' => $missingRoutes,
                'missing_permissions' => $missingPermissions,
                'missing_services' => $missingServices,
                'missing_tables' => $missingTables,
                'empty_tables' => $emptyTables,
            ];
        }
        return $rows;
    }

    /** @return array<string, mixed> */
    private function auditTests(): array
    {
        $files = $this->phpFiles(base_path('tests'));
        $methods = 0;
        foreach ($files as $file) {
            preg_match_all('/function\s+(?:test_[a-zA-Z0-9_]+|it_[a-zA-Z0-9_]+)\s*\(|#\[Test\]/', File::get($file), $matches);
            $methods += count($matches[0]);
        }

        $importantTests = [
            'NotificationRoutingTest.php',
            'ReportExportTest.php',
            'CriticalRouteIntegrityTest.php',
            'AutoloadHygieneTest.php',
            'OrderUpdateInventoryGuardTest.php',
        ];
        $names = array_map('basename', $files);
        $missingImportant = array_values(array_diff($importantTests, $names));
        foreach ($missingImportant as $test) {
            $this->issue('medium', 'test_coverage', 'important_test_missing', "Recommended test [{$test}] is missing.");
        }

        return [
            'files_total' => count($files),
            'test_methods_detected' => $methods,
            'missing_recommended_tests' => $missingImportant,
            'files' => collect($files)->map(fn (string $path): string => $this->relativePath($path))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function buildSummary(array $report): array
    {
        $severityCounts = collect($this->issues)->countBy('severity')->all();
        $workflowCounts = collect($report['workflows'])->countBy('status')->all();
        $componentTotal = collect($report['code']['components'])->sum('count');
        $controllerActions = collect($report['code']['controllers'])->flatMap(fn (array $controller): array => $controller['actions']);

        return [
            'routes_total' => $report['routes']['total'],
            'mutation_routes' => $report['routes']['mutations'],
            'database_tables_total' => $report['database']['tables_total'],
            'database_tables_empty' => $report['database']['tables_empty'],
            'data_expectations_ready' => $report['data_coverage']['ready'],
            'data_expectations_insufficient' => $report['data_coverage']['insufficient'],
            'data_expectations_missing' => $report['data_coverage']['missing'],
            'data_integrity_checks' => $report['data_integrity']['checks_total'],
            'data_integrity_failed' => $report['data_integrity']['failed'],
            'data_integrity_errors' => $report['data_integrity']['errors'],
            'code_components_total' => $componentTotal,
            'controller_actions_total' => $controllerActions->count(),
            'controller_actions_without_tests' => $controllerActions->where('related_test_detected', false)->count(),
            'enums_total' => $report['enums']['total'],
            'enum_autoload_failures' => $report['enums']['autoload_failures'],
            'modules_registered' => $report['modules']['registry_total'],
            'module_dependency_problems' => $report['modules']['dependency_problems'],
            'permissions_total' => $report['access_control']['permissions_total'] ?? 0,
            'roles_total' => $report['access_control']['roles_total'] ?? 0,
            'users_total' => $report['access_control']['users_total'] ?? 0,
            'notification_classes_total' => $report['notifications']['classes_total'],
            'notification_rules_total' => count($report['notifications']['rules']),
            'scheduled_commands_missing' => collect($report['operations']['scheduled_commands'])->where('registered', false)->count(),
            'active_report_schedules' => $report['operations']['report_schedules']['active'] ?? 0,
            'invalid_report_schedules' => count($report['operations']['report_schedules']['invalid'] ?? []),
            'failed_queue_jobs' => $report['operations']['failed_jobs'] ?? 0,
            'release_readiness_available' => $report['release_readiness']['available'] ? 'yes' : 'no',
            'workflows_ready' => $workflowCounts['READY'] ?? 0,
            'workflows_partial' => $workflowCounts['PARTIAL'] ?? 0,
            'workflows_missing' => $workflowCounts['MISSING'] ?? 0,
            'critical_issues' => $severityCounts['critical'] ?? 0,
            'high_issues' => $severityCounts['high'] ?? 0,
            'medium_issues' => $severityCounts['medium'] ?? 0,
            'low_issues' => $severityCounts['low'] ?? 0,
            'total_issues' => count($this->issues),
        ];
    }

    /** @return array<string, string> */
    private function writeReport(array $report): array
    {
        $configured = (string) $this->option('output');
        $directory = $this->absoluteOutputDirectory($configured);
        File::ensureDirectoryExists($directory);
        $stamp = now()->format('Ymd_His');
        $format = strtolower((string) $this->option('format'));
        $paths = [];

        if (in_array($format, ['json', 'both'], true)) {
            $paths['json'] = $directory.DIRECTORY_SEPARATOR."system-audit-{$stamp}.json";
            File::put($paths['json'], json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }

        if (in_array($format, ['markdown', 'md', 'both'], true)) {
            $paths['markdown'] = $directory.DIRECTORY_SEPARATOR."system-audit-{$stamp}.md";
            File::put($paths['markdown'], $this->toMarkdown($report));
        }

        if ($paths === []) {
            throw new \InvalidArgumentException('The --format option must be markdown, json, or both.');
        }

        return $paths;
    }

    private function absoluteOutputDirectory(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) === 1) {
            return rtrim($path, '\\/');
        }
        return base_path(trim($path, '\\/'));
    }

    private function toMarkdown(array $report): string
    {
        $lines = [
            '# Dahab ERP — Deep System Audit',
            '',
            '- Generated: `'.$report['meta']['generated_at'].'`',
            '- Environment: `'.$report['meta']['environment'].'`',
            '- Database: `'.$report['meta']['database_driver'].'`',
            '- Read only: `yes`',
            '',
            '## Summary',
            '',
            '| Metric | Value |',
            '|---|---:|',
        ];

        foreach ($report['summary'] as $metric => $value) {
            $lines[] = '| '.$this->md($metric).' | '.$this->md((string) $value).' |';
        }

        $lines = array_merge($lines, [
            '', '## Operational Readiness', '',
            '| Check | Status |',
            '|---|---|',
        ]);
        foreach ($report['operations']['scheduled_commands'] as $row) {
            $lines[] = '| Scheduled `'.$this->md($row['command']).'` | '.($row['registered'] ? 'REGISTERED' : 'MISSING').' |';
        }
        $lines[] = '| Active report schedules | '.($report['operations']['report_schedules']['active'] ?? 0).' |';
        $lines[] = '| Invalid report schedules | '.count($report['operations']['report_schedules']['invalid'] ?? []).' |';
        $lines[] = '| Failed queue jobs | '.($report['operations']['failed_jobs'] ?? '—').' |';

        $lines = array_merge($lines, [
            '', '## Integrated Data Coverage', '',
            '| Module | Table | Rows | Minimum | Status |',
            '|---|---|---:|---:|---|',
        ]);
        foreach ($report['data_coverage']['items'] as $row) {
            $lines[] = '| '.$this->md($row['module']).' | `'.$this->md($row['table']).'` | '.$this->md((string) ($row['rows'] ?? '—')).' | '.$row['minimum'].' | '.$row['status'].' |';
        }

        $lines = array_merge($lines, [
            '', '## Data Integrity', '',
            '| Section | Check | Status | Violations |',
            '|---|---|---|---:|',
        ]);
        foreach ($report['data_integrity']['checks'] as $row) {
            $lines[] = '| '.$this->md($row['section']).' | '.$this->md($row['check']).' | '.$row['status'].' | '.$this->md((string) ($row['count'] ?? '—')).' |';
        }

        $lines = array_merge($lines, [
            '', '## Module Registry', '',
            '| Code | Name | Type | Implemented | Dependencies | DB record |',
            '|---|---|---|---:|---|---:|',
        ]);
        foreach ($report['modules']['items'] as $row) {
            $lines[] = '| `'.$this->md($row['code']).'` | '.$this->md($row['name']).' | '.$this->md($row['type']).' | '.($row['implemented'] ? 'yes' : 'no').' | '.$this->md(implode(', ', $row['dependencies'])).' | '.($row['database_record'] ? 'yes' : 'no').' |';
        }

        $lines = array_merge($lines, [
            '', '## Enums and Allowed States', '',
            '| Enum | Autoload | Values |',
            '|---|---:|---|',
        ]);
        foreach ($report['enums']['items'] as $row) {
            $values = collect($row['cases'])->map(fn (array $case): string => (string) $case['value'])->implode(', ');
            $lines[] = '| `'.$this->md((string) $row['class']).'` | '.($row['autoloads'] ? 'yes' : 'no').' | '.$this->md($values).' |';
        }

        $lines = array_merge($lines, ['', '## Prioritized Issues', '']);
        foreach (array_slice($report['issues'], 0, 500) as $issue) {
            $lines[] = '- **'.strtoupper($issue['severity']).' / '.$this->md($issue['category']).' / '.$this->md($issue['code']).'** — '.$this->md($issue['message']);
        }

        $lines = array_merge($lines, [
            '', '## Notification Recipient Matrix', '',
            '| Rule | Event / Notification | Permission(s) | Scope | Listener registered | Eligible users | Uncovered locations | Historical deliveries |',
            '|---|---|---|---|---:|---:|---:|---:|',
        ]);
        foreach ($report['notifications']['rules'] as $row) {
            $subject = $row['event'] ?: ($row['notification'] ?: '—');
            $lines[] = '| '.$this->md($row['rule']).' | `'.$this->md($subject).'` | '.$this->md(implode(', ', $row['permissions_any'])).' | '.$this->md($row['scope']).' | '.$this->md((string) ($row['registered_listener_count'] ?? '—')).' | '.$row['eligible_user_count'].' | '.count($row['uncovered_location_ids']).' | '.$row['historical_deliveries'].' |';
        }

        $lines = array_merge($lines, [
            '', '## Workflows', '',
            '| Workflow | Status | Priority | Missing routes | Missing permissions | Missing services | Empty tables |',
            '|---|---|---|---:|---:|---:|---:|',
        ]);
        foreach ($report['workflows'] as $row) {
            $lines[] = '| '.$this->md($row['label']).' | **'.$row['status'].'** | '.$row['priority'].' | '.count($row['missing_routes']).' | '.count($row['missing_permissions']).' | '.count($row['missing_services']).' | '.count($row['empty_tables']).' |';
        }

        $lines = array_merge($lines, [
            '', '## Route Groups', '',
            '| Group | Routes | Read | Mutations | Permissions |',
            '|---|---:|---:|---:|---|',
        ]);
        foreach ($report['routes']['groups'] as $row) {
            $lines[] = '| '.$this->md($row['group']).' | '.$row['routes'].' | '.$row['read'].' | '.$row['mutations'].' | '.$this->md(implode(', ', $row['permissions'])).' |';
        }

        $lines = array_merge($lines, [
            '', '## Roles', '',
            '| Role | Permissions | Users |',
            '|---|---:|---:|',
        ]);
        foreach (($report['access_control']['roles'] ?? []) as $row) {
            $lines[] = '| '.$this->md($row['name']).' | '.$row['permission_count'].' | '.$row['user_count'].' |';
        }

        $lines = array_merge($lines, [
            '', '## Permission Recipient Matrix', '',
            '| Permission | Guard | Roles | Eligible users |',
            '|---|---|---|---:|',
        ]);
        foreach (($report['access_control']['permissions'] ?? []) as $row) {
            $lines[] = '| `'.$this->md($row['name']).'` | '.$this->md($row['guard']).' | '.$this->md(implode(', ', $row['roles'])).' | '.$row['eligible_user_count'].' |';
        }

        $lines = array_merge($lines, [
            '', '## Complete Database Inventory', '',
            '| Table | Rows | Columns | Status |',
            '|---|---:|---:|---|',
        ]);
        foreach ($report['database']['inventory'] as $row) {
            $lines[] = '| '.$this->md($row['table']).' | '.$this->md((string) ($row['rows'] ?? '—')).' | '.$this->md((string) ($row['columns'] ?? '—')).' | '.$row['status'].' |';
        }

        $lines = array_merge($lines, [
            '', '## Code Component Inventory', '',
            '| Component | Count | Autoload failures |',
            '|---|---:|---:|',
        ]);
        foreach ($report['code']['components'] as $component => $row) {
            $lines[] = '| '.$this->md($component).' | '.$row['count'].' | '.$row['autoload_failures'].' |';
        }

        $lines[] = '';
        $lines[] = '> The JSON report contains route-level, controller-action, user-role-location and notification delivery details.';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    /** @param array<string, string> $paths */
    private function renderConsoleSummary(array $report, array $paths): void
    {
        $summary = $report['summary'];
        $this->newLine();
        $this->table(['Metric', 'Value'], collect($summary)->map(fn ($value, $key): array => [$key, $value])->values()->all());
        $this->newLine();
        foreach ($paths as $format => $path) {
            $this->line(strtoupper($format).': '.$path);
        }

        $top = array_slice($report['issues'], 0, (int) $this->option('sample'));
        if ($top !== []) {
            $this->newLine();
            $this->warn('Top issues:');
            $this->table(['Severity', 'Category', 'Code', 'Message'], array_map(fn (array $issue): array => [
                strtoupper($issue['severity']), $issue['category'], $issue['code'], $issue['message'],
            ], $top));
        }
    }

    /** @param array<int, array<string, mixed>> $issues */
    private function shouldFail(array $issues): bool
    {
        $threshold = strtolower((string) $this->option('fail-on'));
        if ($threshold === 'never') {
            return false;
        }
        $weight = self::SEVERITY_WEIGHT[$threshold] ?? self::SEVERITY_WEIGHT['critical'];
        return collect($issues)->contains(fn (array $issue): bool => (self::SEVERITY_WEIGHT[$issue['severity']] ?? 0) >= $weight);
    }

    /** @param array<string, mixed> $context */
    private function issue(string $severity, string $category, string $code, string $message, array $context = []): void
    {
        $fingerprint = sha1($severity.'|'.$category.'|'.$code.'|'.$message.'|'.json_encode($context));
        foreach ($this->issues as $issue) {
            if (($issue['fingerprint'] ?? null) === $fingerprint) {
                return;
            }
        }

        $this->issues[] = compact('severity', 'category', 'code', 'message', 'context', 'fingerprint');
    }

    /** @return array<int, string> */
    private function phpFiles(string $directory): array
    {
        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::allFiles($directory))
            ->filter(fn ($file): bool => strtolower($file->getExtension()) === 'php')
            ->map(fn ($file): string => $file->getPathname())
            ->sort()->values()->all();
    }

    private function combinedSource(string $directory): string
    {
        return collect($this->phpFiles($directory))->map(fn (string $path): string => File::get($path))->implode("\n");
    }

    /** @param array<int, string> $excludedDirectories */
    private function sourceReferenceCount(string $needle, array $excludedDirectories = []): int
    {
        $count = 0;
        foreach ($this->phpFiles(app_path()) as $path) {
            if (collect($excludedDirectories)->contains(fn (string $directory): bool => str_starts_with($path, $directory))) {
                continue;
            }
            $count += substr_count(File::get($path), $needle);
        }
        return $count;
    }

    private function extractFqcn(string $source): ?string
    {
        if (! preg_match('/namespace\s+([^;]+);/', $source, $namespace) || ! preg_match('/(?:final\s+|abstract\s+|readonly\s+)?(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/', $source, $class)) {
            return null;
        }
        return trim($namespace[1]).'\\'.$class[1];
    }

    private function expectedAppClassForPath(string $path): ?string
    {
        $appPath = rtrim(str_replace('\\', '/', app_path()), '/').'/';
        $normalized = str_replace('\\', '/', $path);

        if (! str_starts_with($normalized, $appPath) || ! str_ends_with($normalized, '.php')) {
            return null;
        }

        $relative = substr($normalized, strlen($appPath), -4);

        return 'App\\'.str_replace('/', '\\', $relative);
    }

    private function symbolExists(?string $symbol): bool
    {
        if (! $symbol) {
            return false;
        }

        return class_exists($symbol)
            || interface_exists($symbol)
            || trait_exists($symbol)
            || (function_exists('enum_exists') && enum_exists($symbol));
    }

    private function auditSetting(string $key, mixed $fallback = null): mixed
    {
        $builtIn = self::DEFAULT_AUDIT_CONFIG[$key] ?? $fallback;
        $external = config("system_audit.{$key}");

        if ($external === null) {
            return $builtIn;
        }

        if (is_array($builtIn) && is_array($external)) {
            if (array_is_list($builtIn)) {
                return array_values(array_unique(array_merge($builtIn, $external), SORT_REGULAR));
            }

            return array_replace_recursive($builtIn, $external);
        }

        return $external;
    }

    /** @return array<int, string> */
    private function recipientEmails(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)->flatten()->filter(fn ($item): bool => is_scalar($item) && trim((string) $item) !== '')
                ->map(fn ($item): string => trim((string) $item))->unique()->values()->all();
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $this->recipientEmails($decoded);
        }

        return collect(preg_split('/[,;\s]+/', $raw) ?: [])
            ->map(fn (string $email): string => trim($email))
            ->filter()->unique()->values()->all();
    }

    private function methodSource(ReflectionMethod $method): string
    {
        $file = $method->getFileName();
        if (! $file || ! File::exists($file)) {
            return '';
        }
        $lines = File::lines($file)->all();
        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', ltrim(str_replace(base_path(), '', $path), '\\/'));
    }

    private function md(string $value): string
    {
        return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], $value);
    }
}
