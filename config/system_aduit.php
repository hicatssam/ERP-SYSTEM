<?php

return [
    /* Routes that intentionally mutate data without a logged-in web user. */
    'public_mutation_allowlist' => [
        'login',
        'login.post',
        'password.email',
        'password.update',
        'attendance.devices.push.heartbeat',
        'attendance.devices.push.punches',
        'attendance.integrations.devices.heartbeat',
        'attendance.integrations.devices.punches',
        'customer-menu.orders.store',
        'storage.local.upload',
    ],

    /* Authenticated operations that only affect the current user's own session/profile. */
    'authorization_exempt_mutations' => [
        'logout',
        'auth.change-password.post',
        'profile.update',
    ],

    /* Empty framework/queue tables are normal and should not raise a warning. */
    'ignored_empty_tables' => [
        'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs',
        'password_reset_tokens', 'sessions',
    ],

    'critical_tables' => [
        'users', 'roles', 'permissions', 'role_has_permissions', 'model_has_roles',
        'locations', 'products', 'location_products', 'inventories',
        'orders', 'order_items', 'payment_methods',
    ],

    'scheduled_commands' => [
        'reports:send-scheduled',
        'inventory:check-expiry',
    ],

    /*
     * Each notification rule is audited without dispatching a real notification.
     * Add or rename classes here when the application introduces new events.
     */
    'notification_rules' => [
        'order_created' => [
            'event' => 'App\\Events\\OrderCreated',
            'listener' => 'App\\Listeners\\NotifyStaffOnOrderCreated',
            'permissions_any' => ['orders.view'],
            'scope' => 'location',
        ],
        'order_status_changed' => [
            'event' => 'App\\Events\\OrderStatusChanged',
            'listener' => 'App\\Listeners\\NotifyStaffOnOrderStatusChanged',
            'permissions_any' => ['orders.view'],
            'scope' => 'location',
        ],
        'payment_received' => [
            'event' => 'App\\Events\\PaymentReceived',
            'listener' => 'App\\Listeners\\NotifyStaffOnPaymentReceived',
            'permissions_any' => ['payments.verify', 'payments.view', 'payments.record'],
            'scope' => 'location',
        ],
        'low_stock' => [
            'event' => 'App\\Events\\LowStockDetected',
            'listener' => 'App\\Listeners\\NotifyStaffOnLowStock',
            'permissions_any' => ['inventory.view', 'inventory.adjust'],
            'scope' => 'location',
        ],
        'stock_request_created' => [
            'notification' => 'App\\Notifications\\StockRequestCreatedNotification',
            'permissions_any' => ['stock_requests.view', 'stock_requests.review'],
            'scope' => 'destination_location',
        ],
        'stock_request_status_changed' => [
            'notification' => 'App\\Notifications\\StockRequestStatusChangedNotification',
            'permissions_any' => ['stock_requests.view', 'stock_requests.create'],
            'scope' => 'location',
        ],
        'invoice_cancelled' => [
            'notification' => 'App\\Notifications\\InvoiceCancelledNotification',
            'permissions_any' => ['invoices.view', 'invoices.cancel'],
            'scope' => 'location',
        ],
        'product_created' => [
            'notification' => 'App\\Notifications\\ProductCreatedNotification',
            'permissions_any' => ['products.view', 'products.update'],
            'scope' => 'location',
        ],
        'customer_created' => [
            'notification' => 'App\\Notifications\\CustomerCreatedNotification',
            'permissions_any' => ['customers.view'],
            'scope' => 'location',
        ],
        'employee_created' => [
            'notification' => 'App\\Notifications\\EmployeeCreatedNotification',
            'permissions_any' => ['employees.view', 'employees.manage'],
            'scope' => 'location',
        ],
        'user_created' => [
            'notification' => 'App\\Notifications\\UserCreatedNotification',
            'permissions_any' => ['users.manage'],
            'scope' => 'global',
        ],
        'payment_method_created' => [
            'notification' => 'App\\Notifications\\PaymentMethodCreatedNotification',
            'permissions_any' => ['payment_methods.view', 'payment_methods.manage'],
            'scope' => 'global',
        ],
        'sales_channel_changed' => [
            'notification' => 'App\\Notifications\\SalesChannelChanged',
            'permissions_any' => ['sales_channels.view', 'sales_channels.manage'],
            'scope' => 'global',
        ],
    ],

    /* End-to-end flows: route + permission + service + data. */
    'workflows' => [
        'access_control' => [
            'label' => 'الدخول والأدوار والصلاحيات',
            'priority' => 'critical',
            'routes' => ['login', 'logout', 'users.index', 'roles.index'],
            'permissions' => ['users.manage', 'roles.manage'],
            'tables' => ['users', 'roles', 'permissions', 'model_has_roles', 'role_has_permissions'],
        ],
        'sales_order' => [
            'label' => 'الطلب: إنشاء ← تأكيد ← إكمال/إلغاء',
            'priority' => 'critical',
            'routes' => ['orders.index', 'orders.store', 'orders.confirm', 'orders.complete', 'orders.cancel'],
            'permissions' => ['orders.view', 'orders.create'],
            'services' => ['App\\Services\\Orders\\OrderService'],
            'tables' => ['orders', 'order_items', 'customers'],
        ],
        'payment_invoice' => [
            'label' => 'الدفع ← التحقق ← الفاتورة ← الطباعة',
            'priority' => 'critical',
            'routes' => ['payments.index', 'payments.store', 'payments.verify', 'invoices.index', 'invoices.show', 'invoices.print', 'invoices.pdf'],
            'permissions' => ['payments.record', 'payments.verify', 'invoices.view'],
            'services' => ['App\\Services\\Payments\\PaymentService', 'App\\Services\\Invoices\\InvoiceService'],
            'tables' => ['payments', 'payment_methods', 'invoices', 'invoice_items'],
        ],
        'customer_account' => [
            'label' => 'كشف العميل ← دفعة ← تخصيص ← تحقق',
            'priority' => 'high',
            'routes' => ['customers.statement', 'customers.account-payments.store', 'customers.account-payments.verify'],
            'permissions' => ['customers.view', 'payments.record', 'payments.verify'],
            'services' => ['App\\Services\\Customers\\CustomerAccountService'],
            'tables' => ['customers', 'customer_payments', 'customer_payment_allocations'],
        ],
        'financial_period' => [
            'label' => 'الصندوق والفترة المالية',
            'priority' => 'critical',
            'routes' => ['cash-sessions.open', 'cash-sessions.close', 'financial-periods.open', 'financial-periods.close'],
            'permissions' => ['cash_sessions.manage', 'financial.periods.view'],
            'services' => ['App\\Services\\Finance\\FinancialPeriodService', 'App\\Services\\Finance\\FinancialPostingService'],
            'tables' => ['cash_sessions', 'financial_periods', 'payments'],
        ],
        'inventory' => [
            'label' => 'المخزون ← جرد ← طلب ← تحويل',
            'priority' => 'critical',
            'routes' => ['inventory.index', 'inventory.adjust', 'stock-counts.index', 'stock-requests.index', 'stock-transfers.index'],
            'permissions' => ['inventory.view', 'inventory.adjust', 'inventory.count', 'stock_requests.create', 'stock_transfers.view'],
            'services' => ['App\\Services\\Inventory\\InventoryService', 'App\\Services\\Inventory\\InternalTransferService'],
            'tables' => ['inventories', 'stock_movements', 'stock_counts', 'stock_requests', 'stock_transfers'],
        ],
        'inventory_expiry' => [
            'label' => 'دفعات المخزون ← الصلاحية ← المسح ← التنبيه',
            'priority' => 'high',
            'routes' => ['inventory.expiry.index', 'inventory.expiry.print', 'inventory.expiry.scan'],
            'permissions' => ['inventory.view'],
            'services' => ['App\\Services\\Inventory\\InventoryExpiryService', 'App\\Services\\Inventory\\InventoryExpiryMonitorService'],
            'tables' => ['inventory_batches'],
        ],
        'procurement' => [
            'label' => 'مورد ← أمر شراء ← استلام ← فاتورة ← دفع',
            'priority' => 'critical',
            'routes' => ['suppliers.index', 'purchase-orders.submit', 'purchase-orders.approve', 'goods-receipts.post', 'supplier-invoices.index', 'supplier-payments.store'],
            'permissions' => ['suppliers.view', 'purchase_orders.view'],
            'services' => ['App\\Services\\Procurement\\PurchaseOrderService', 'App\\Services\\Procurement\\GoodsReceiptService', 'App\\Services\\Procurement\\SupplierInvoiceService'],
            'tables' => ['suppliers', 'supplier_products', 'purchase_orders', 'purchase_order_items', 'goods_receipts', 'supplier_invoices'],
        ],
        'procurement_return_payment' => [
            'label' => 'مرتجع شراء ← ترحيل ← رصيد مورد ← سداد',
            'priority' => 'high',
            'routes' => ['purchase-returns.index', 'purchase-returns.store', 'purchase-returns.post', 'supplier-payments.index', 'supplier-payments.store', 'suppliers.statement'],
            'services' => ['App\\Services\\Procurement\\PurchaseReturnService', 'App\\Services\\Procurement\\SupplierPaymentService', 'App\\Services\\Procurement\\SupplierBalanceService'],
            'tables' => ['purchase_returns', 'purchase_return_items', 'supplier_payments', 'supplier_invoices'],
        ],
        'special_cake_orders' => [
            'label' => 'طلب كيك خاص ← مصنع ← انتقالات ← مرفقات',
            'priority' => 'high',
            'routes' => ['cake-orders.index', 'cake-orders.store', 'cake-orders.transition', 'cake-orders.comment', 'cake-orders.attachment'],
            'permissions' => ['cake_orders.view', 'cake_orders.create'],
            'services' => ['App\\Services\\SpecialCakes\\SpecialCakeOrderService', 'App\\Services\\SpecialCakes\\SpecialCakeStatusTransitionService'],
            'tables' => ['special_cake_orders', 'cake_order_status_histories'],
        ],
        'branch_sweets_requests' => [
            'label' => 'طلبات الفروع ← مراجعة المصنع ← التجهيز ← الاستلام',
            'priority' => 'high',
            'routes' => ['showroom-sweets-requests.index', 'showroom-sweets-requests.store', 'showroom-sweets-requests.status'],
            'permissions' => ['showroom_sweets_requests.view', 'showroom_sweets_requests.create'],
            'services' => ['App\\Services\\Notifications\\ShowroomSweetsRequestNotifier'],
            'tables' => ['showroom_sweets_requests', 'showroom_sweets_request_items'],
        ],
        'restaurant' => [
            'label' => 'طاولة ← جلسة ← POS ← طلب',
            'priority' => 'high',
            'routes' => ['restaurant.dashboard', 'restaurant.pos.index', 'restaurant.pos.orders.store', 'restaurant.tables.open', 'restaurant.tables.close'],
            'permissions' => ['restaurant.view', 'restaurant_pos.use', 'restaurant_tables.view'],
            'services' => ['App\\Services\\Restaurant\\RestaurantOrderService', 'App\\Services\\Restaurant\\RestaurantTableService'],
            'tables' => ['restaurant_areas', 'restaurant_tables', 'restaurant_table_sessions', 'orders'],
        ],
        'kitchen_kds' => [
            'label' => 'توجيه ← تذكرة ← تحضير ← جاهز',
            'priority' => 'high',
            'routes' => ['kitchen.stations.index', 'kitchen.tickets.index', 'kds.index'],
            'permissions' => ['kitchen.view', 'kds.view'],
            'services' => ['App\\Services\\Kitchen\\KitchenRoutingService', 'App\\Services\\Kitchen\\KitchenTicketService'],
            'tables' => ['kitchen_stations', 'kitchen_tickets', 'kitchen_ticket_items'],
        ],
        'production' => [
            'label' => 'وصفة ← اعتماد ← إنتاج ← صرف ← إكمال',
            'priority' => 'critical',
            'routes' => ['production.recipes.index', 'production.recipes.approve', 'production.orders.release', 'production.orders.start', 'production.orders.complete'],
            'permissions' => ['recipes.view', 'recipes.approve', 'production.view', 'production.complete'],
            'services' => ['App\\Services\\Production\\RecipeService', 'App\\Services\\Production\\ProductionOrderService', 'App\\Services\\Production\\ProductionInventoryService'],
            'tables' => ['recipes', 'recipe_items', 'production_orders', 'production_order_items'],
        ],
        'production_quality' => [
            'label' => 'جودة الإنتاج ← فحص ← قبول/رفض',
            'priority' => 'high',
            'routes' => ['production.quality.index', 'production.quality.show', 'production.quality.inspect'],
            'permissions' => ['quality_control.view', 'quality_control.inspect'],
            'services' => ['App\\Services\\Production\\ProductionQualityService'],
            'tables' => ['production_quality_inspections'],
        ],
        'attendance_payroll' => [
            'label' => 'دوام ← اعتماد ← راتب ← اعتماد ← صرف',
            'priority' => 'critical',
            'routes' => ['attendance.store', 'attendance.approve', 'payroll.calculate', 'payroll.approve', 'payroll.pay'],
            'permissions' => ['attendance.view', 'attendance.approve', 'payroll.view', 'payroll.approve', 'payroll.pay'],
            'services' => ['App\\Services\\AttendanceService', 'App\\Services\\PayrollService', 'App\\Services\\PayrollSettlementService'],
            'tables' => ['attendance_records', 'payroll_periods', 'payroll_items', 'payroll_payments', 'employee_ledger_entries'],
        ],
        'reports' => [
            'label' => 'عرض ← طباعة ← PDF/XLSX ← جدولة ← بريد',
            'priority' => 'high',
            'routes' => ['reports.index', 'reports.show', 'reports.print', 'reports.export.pdf', 'reports.export.xlsx', 'report-schedules.index'],
            'permissions' => ['reports.view'],
            'services' => ['App\\Services\\ReportScheduleService'],
            'tables' => ['report_schedules'],
        ],
        'product_catalog' => [
            'label' => 'وحدات ← علامات ← خصائص ← متغيرات المنتجات',
            'priority' => 'medium',
            'routes' => ['catalog.index', 'catalog.units.index', 'catalog.brands.index', 'catalog.sizes.index', 'catalog.colors.index', 'catalog.products.variants.index'],
            'permissions' => ['products.update'],
            'tables' => ['units', 'brands', 'sizes', 'colors', 'product_attributes', 'product_attribute_values', 'product_variants'],
        ],
        'configuration' => [
            'label' => 'تهيئة العميل والوحدات والعملات والهوية',
            'priority' => 'high',
            'routes' => ['modules.index', 'business-profiles.apply', 'onboarding.apply', 'settings.currencies.index', 'settings.print-branding.edit'],
            'permissions' => ['settings.manage'],
            'services' => ['App\\Services\\BusinessProfileService', 'App\\Services\\ClientOnboardingService', 'App\\Services\\ModuleService', 'App\\Services\\PrintThemeService'],
            'tables' => ['modules', 'business_profiles', 'business_profile_modules', 'system_settings'],
        ],
        'notifications' => [
            'label' => 'Event ← Listener ← مستلم حسب الدور والفرع',
            'priority' => 'critical',
            'routes' => ['notifications.index', 'notifications.read-all', 'notifications.recent'],
            'tables' => ['notifications'],
        ],
        'internal_chat' => [
            'label' => 'قناة داخلية ← رسالة ← استلام ← قراءة',
            'priority' => 'medium',
            'routes' => ['chat.index', 'chat.direct.start', 'chat.show', 'chat.messages.store', 'chat.read'],
            'services' => ['App\\Services\\Chat\\BranchChatService'],
            'tables' => ['chat_channels', 'chat_channel_members', 'chat_messages', 'chat_message_receipts'],
        ],
        'crm_loyalty_delivery' => [
            'label' => 'CRM ← ولاء ← عنوان ← مهمة توصيل',
            'priority' => 'medium',
            'routes' => ['crm.index', 'loyalty.index', 'delivery.tasks.index'],
            'permissions' => ['crm.view', 'loyalty.view', 'delivery.view'],
            'services' => ['App\\Services\\Loyalty\\LoyaltyService', 'App\\Services\\Delivery\\DeliveryService'],
            'tables' => ['customer_addresses', 'customer_interactions', 'loyalty_accounts', 'delivery_tasks'],
        ],
        'costing' => [
            'label' => 'التكلفة ← المصروفات ← الربحية',
            'priority' => 'high',
            'routes' => ['costing.profitability', 'costing.expenses.index'],
            'permissions' => ['costing.view', 'expenses.view'],
            'services' => ['App\\Services\\Finance\\CostingService', 'App\\Services\\Finance\\ProfitabilityService'],
            'tables' => ['expense_categories', 'expenses'],
        ],
    ],
];
