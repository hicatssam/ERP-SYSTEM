<?php

namespace App\Support;

class PermissionUi
{
    public static function roleLabels(): array
    {
        return [
            'Admin' => 'مدير النظام',
            'General Manager' => 'المدير العام',
            'Branch Manager' => 'مدير الفرع',
            'Branch Employee' => 'موظف فرع',
            'Cashier' => 'كاشير',
            'Factory Manager' => 'مدير المصنع',
            'Production Employee' => 'موظف إنتاج',
            'Cake Designer' => 'مصمم كيك',
            'Quality Control' => 'مراقب جودة',
            'Dispatcher' => 'موظف توصيل',
            'Delivery Driver' => 'سائق توصيل',
            'Inventory Manager' => 'مدير المخزون',
            'Accountant' => 'محاسب',
            'Waiter' => 'نادل / مقدم خدمة',
            'Kitchen Staff' => 'موظف مطبخ',
        ];
    }

    public static function groupLabels(): array
    {
        return [
            'dashboard' => 'لوحة التحكم',
            'orders' => 'طلبات البيع',
            'cake_orders' => 'طلبات الكيك الخاصة',
            'showroom_sweets_requests' => 'طلبات حلويات الفروع',
            'customer_display' => 'شاشة طلبات العملاء',
            'customers' => 'العملاء',

            // Sprint 08
            'crm' => 'إدارة علاقات العملاء CRM',
            'loyalty' => 'برنامج الولاء',
            'delivery' => 'التوصيل',

            'employees' => 'الموظفون',
            'users' => 'المستخدمون',
            'roles' => 'الأدوار والصلاحيات',
            'products' => 'المنتجات',
            'inventory' => 'المخزون',
            'stock_requests' => 'طلبات المخزون',
            'stock_transfers' => 'تحويلات المخزون',
            'invoices' => 'الفواتير',
            'payments' => 'المدفوعات',
            'cash_sessions' => 'جلسات الكاش',
            'financial' => 'الإدارة المالية',
            'payment_methods' => 'طرق الدفع',
            'reports' => 'التقارير',
            'locations' => 'الفروع والمصنع',
            'notifications' => 'الإشعارات',
            'settings' => 'إعدادات النظام',
            'system_currencies' => 'عملات النظام',
            'costing' => 'الربحية والتكلفة',
            'receiving_invoices' => 'فواتير الاستلام',
            'suppliers' => 'الموردون',
            'purchase_orders' => 'أوامر الشراء',
            'goods_receipts' => 'استلام المشتريات',
            'supplier_invoices' => 'فواتير الموردين',
            'supplier_payments' => 'دفعات الموردين',
            'purchase_returns' => 'مرتجعات المشتريات',
            'procurement' => 'المشتريات',
            'sales_channels' => 'قنوات البيع',
            'chat' => 'المحادثات الداخلية',
            'restaurant' => 'تشغيل المطعم',
            'restaurant_pos' => 'نقطة بيع المطعم',
            'restaurant_tables' => 'طاولات المطعم',
            'kitchen' => 'المطبخ',
            'kds' => 'شاشة المطبخ KDS',
            'recipes' => 'الوصفات وبنية المنتج',
            'production' => 'الإنتاج',
            'quality_control' => 'مراقبة جودة الإنتاج',
        ];
    }

    public static function permissionLabels(): array
    {
        return [
            // لوحة التحكم
            'dashboard.view' => 'الدخول إلى لوحة التحكم',
            'dashboard.orders' => 'إظهار بطاقة طلبات اليوم',
            'dashboard.sales' => 'إظهار المبيعات',
            'dashboard.collections' => 'إظهار التحصيلات',
            'dashboard.cash' => 'إظهار الرصيد النقدي',
            'dashboard.profit' => 'إظهار صافي الربح',
            'dashboard.inventory' => 'إظهار تنبيهات نقص المخزون',
            'dashboard.stock_requests' => 'إظهار طلبات المخزون المعلقة',
            'dashboard.cake_orders' => 'إظهار الكيك قيد الإنتاج',
            'dashboard.cake_pipeline' => 'إظهار خط إنتاج الكيك',
            'dashboard.products_chart' => 'إظهار رسم المنتجات الأكثر مبيعًا',
            'dashboard.revenue_chart' => 'إظهار رسم الإيرادات',
            'dashboard.branch_comparison' => 'إظهار مقارنة مبيعات الفروع',
            'dashboard.procurement' => 'الدخول إلى لوحة المشتريات',

            // الطلبات
            'orders.view' => 'عرض الطلبات',
            'orders.create' => 'إنشاء طلب',
            'orders.edit' => 'تعديل طلب',
            'orders.confirm' => 'تأكيد طلب',
            'orders.cancel' => 'إلغاء طلب',

            // طلبات الكيك
            'cake_orders.view' => 'عرض طلبات الكيك',
            'cake_orders.create' => 'إنشاء طلب كيك',
            'cake_orders.edit' => 'تعديل طلب كيك',
            'cake_orders.delete' => 'حذف طلب كيك',
            'cake_orders.review' => 'مراجعة طلب كيك في المصنع',
            'cake_orders.accept' => 'قبول طلب كيك',
            'cake_orders.reject' => 'رفض طلب كيك',
            'cake_orders.request_modification' => 'طلب تعديل على طلب الكيك',
            'cake_orders.schedule' => 'جدولة طلب الكيك للإنتاج',
            'cake_orders.prepare' => 'تحضير وتجهيز الكيك',
            'cake_orders.decorate' => 'تزيين الكيك',
            'cake_orders.quality_check' => 'فحص جودة الكيك',
            'cake_orders.dispatch' => 'إرسال الكيك إلى الفرع',
            'cake_orders.receive' => 'تأكيد استلام الكيك في الفرع',
            'cake_orders.manage' => 'إدارة شاملة لطلبات الكيك — صلاحية قديمة',

            // طلبات حلويات الفروع
            'showroom_sweets_requests.view' => 'عرض طلبات حلويات الفروع',
            'showroom_sweets_requests.view_all' => 'عرض طلبات حلويات جميع الفروع',
            'showroom_sweets_requests.create' => 'إنشاء طلب حلويات للمصنع',
            'showroom_sweets_requests.start' => 'اعتماد طلب الحلويات وبدء التجهيز',
            'showroom_sweets_requests.ready' => 'اعتماد جاهزية طلب الحلويات',
            'showroom_sweets_requests.dispatch' => 'إرسال طلب الحلويات إلى الفرع',
            'showroom_sweets_requests.receive' => 'تأكيد استلام الحلويات في الفرع',
            'showroom_sweets_requests.reject' => 'رفض طلب حلويات الفرع',
            'showroom_sweets_requests.cancel' => 'إلغاء طلب حلويات الفرع',
            'showroom_sweets_requests.delete' => 'حذف طلب حلويات ملغى',
            'showroom_sweets_requests.update_status' => 'تحديث حالة طلب الحلويات — صلاحية قديمة',

            // العملاء
            'customers.view' => 'عرض عملاء الفرع',
            'customers.view_all' => 'عرض عملاء جميع الفروع',
            'customers.create' => 'إضافة عميل',
            'customers.update' => 'تعديل بيانات عميل',
            'customers.delete' => 'حذف عميل',

            // Sprint 08 — CRM
            'crm.view' => 'عرض ملف العميل CRM ضمن نطاق الفرع',
            'crm.view_all' => 'عرض CRM لجميع الفروع',
            'crm.manage' => 'إدارة بيانات CRM والعناوين والوسوم والمتابعات',
            'crm.interactions.manage' => 'إضافة وإدارة تفاعلات ومتابعات العملاء',

            // Sprint 08 — Loyalty
            'loyalty.view' => 'عرض حسابات ونقاط الولاء',
            'loyalty.manage' => 'إدارة برنامج الولاء وإعداداته',
            'loyalty.adjust' => 'إجراء تعديل يدوي على نقاط الولاء',
            'loyalty.redeem' => 'استبدال نقاط الولاء للعميل',

            // Sprint 08 — Delivery
            'delivery.view' => 'عرض مهام التوصيل ضمن نطاق الفرع',
            'delivery.view_all_locations' => 'عرض مهام التوصيل لجميع الفروع',
            'delivery.zones.manage' => 'إدارة مناطق ورسوم التوصيل',
            'delivery.create' => 'إنشاء مهمة توصيل',
            'delivery.assign' => 'تعيين أو إعادة تعيين سائق التوصيل',
            'delivery.update_status' => 'تحديث حالة مهمة التوصيل',

            // الموظفون
            'employees.view' => 'عرض الموظفين',
            'employees.view_all' => 'عرض موظفي جميع الفروع',
            'employees.create' => 'إضافة موظف',
            'employees.update' => 'تعديل بيانات موظف',
            'employees.delete' => 'حذف موظف',
            'employees.manage' => 'إدارة الموظفين — صلاحية قديمة',

            // المستخدمون
            'users.manage' => 'إدارة المستخدمين',
            'roles.manage' => 'إدارة الأدوار والصلاحيات',

            // المخزون
            'inventory.view' => 'عرض المخزون',
            'inventory.count' => 'إجراء جرد للمخزون',
            'inventory.adjust' => 'تسوية المخزون',

            'stock_requests.view' => 'عرض طلبات المخزون',
            'stock_requests.create' => 'إنشاء طلب مخزون',
            'stock_requests.review' => 'مراجعة طلبات المخزون',

            'stock_transfers.view' => 'عرض تحويلات المخزون',
            'stock_transfers.dispatch' => 'إرسال تحويل مخزون',
            'stock_transfers.receive' => 'استلام تحويل مخزون',

            // المنتجات
            'products.view' => 'عرض المنتجات',
            'products.create' => 'إضافة منتج',
            'products.update' => 'تعديل منتج',

            // الفواتير
            'invoices.view' => 'عرض الفواتير',
            'invoices.create' => 'إنشاء فاتورة',
            'invoices.print' => 'طباعة فاتورة',
            'invoices.cancel' => 'إلغاء فاتورة',

            // المدفوعات
            'payments.record' => 'تسجيل دفعة',
            'payments.verify' => 'التحقق من دفعة',
            'payments.correct' => 'تصحيح دفعة',
            'payments.refund' => 'استرجاع دفعة',

            // جلسات الكاش
            'cash_sessions.manage' => 'إدارة جلسة الكاش',

            // المالية
            'financial.dashboard.view' => 'عرض لوحة المعلومات المالية',
            'financial.branch.view' => 'عرض مالية الفرع',
            'financial.global.view' => 'عرض مالية جميع الفروع',
            'financial.sales.view' => 'عرض المبيعات المالية',
            'financial.collections.view' => 'عرض التحصيلات المالية',
            'financial.outstanding.view' => 'عرض المبالغ المستحقة',
            'financial.refunds.view' => 'عرض المرتجعات المالية',
            'financial.adjustments.create' => 'إنشاء تسوية مالية',
            'financial.adjustments.approve' => 'اعتماد التسويات المالية',
            'financial.periods.view' => 'عرض الفترات المالية',
            'financial.periods.open' => 'فتح فترة مالية',
            'financial.periods.close' => 'إغلاق فترة مالية',
            'financial.periods.override_close' => 'تجاوز إغلاق الفترة المالية',
            'financial.reports.view' => 'عرض التقارير المالية',
            'financial.reports.export' => 'تصدير التقارير المالية',

            // طرق الدفع
            'payment_methods.view' => 'عرض طرق الدفع',
            'payment_methods.manage' => 'إدارة طرق الدفع',

            // التقارير
            'reports.view' => 'عرض التقارير',
            'reports.export' => 'تصدير التقارير',
            'reports.branch_sales' => 'تقرير مبيعات الفروع',
            'reports.payment_methods' => 'تقرير طرق الدفع',

            // النظام
            'locations.manage' => 'إدارة الفروع والمصنع',
            'notifications.manage' => 'إدارة إعدادات الإشعارات',
            'settings.manage' => 'إدارة إعدادات النظام',
            'system_currencies.view' => 'عرض عملات النظام',
            'system_currencies.manage' => 'إدارة عملات النظام',

            // الربحية والتكلفة
            'costing.view' => 'عرض تقارير الربحية والتكلفة',
            'costing.view_all_locations' => 'عرض الربحية والتكلفة لجميع الفروع',
            'costing.manage' => 'إدارة إعدادات الربحية والتكلفة',
            'costing.backfill' => 'إعادة بناء بيانات التكلفة التاريخية',

            // الاستلام
            'receiving_invoices.view' => 'عرض فواتير الاستلام',
            'stock_receiving_invoices.view' => 'عرض فواتير استلام المخزون',

            // الموردون
            'suppliers.view' => 'عرض الموردين',
            'suppliers.create' => 'إضافة مورد',
            'suppliers.update' => 'تعديل مورد',
            'suppliers.delete' => 'حذف مورد',

            // أوامر الشراء
            'purchase_orders.view' => 'عرض أوامر الشراء',
            'purchase_orders.create' => 'إنشاء أمر شراء',
            'purchase_orders.update' => 'تعديل أمر شراء',
            'purchase_orders.approve' => 'اعتماد أمر شراء',
            'purchase_orders.cancel' => 'إلغاء أمر شراء',

            // استلام المشتريات
            'goods_receipts.view' => 'عرض سندات استلام المشتريات',
            'goods_receipts.create' => 'إنشاء استلام مشتريات',
            'goods_receipts.approve' => 'اعتماد استلام مشتريات',

            // فواتير الموردين
            'supplier_invoices.view' => 'عرض فواتير الموردين',
            'supplier_invoices.create' => 'إنشاء فاتورة مورد',
            'supplier_invoices.cancel' => 'إلغاء فاتورة مورد',

            // دفعات الموردين
            'supplier_payments.view' => 'عرض دفعات الموردين',
            'supplier_payments.create' => 'تسجيل دفعة مورد',

            // مرتجعات المشتريات
            'purchase_returns.view' => 'عرض مرتجعات المشتريات',
            'purchase_returns.create' => 'إنشاء مرتجع مشتريات',
            'purchase_returns.approve' => 'اعتماد مرتجع مشتريات',
            'purchase_returns.cancel' => 'إلغاء مرتجع مشتريات',

            // المشتريات
            'procurement.exchange_rates.view' => 'عرض أسعار الصرف',
            'procurement.exchange_rates.manage' => 'إدارة أسعار الصرف',
            'procurement.reports.view' => 'عرض تقارير المشتريات',

            // قنوات البيع
            'sales_channels.view' => 'عرض قنوات البيع',
            'sales_channels.create' => 'إضافة قناة بيع',
            'sales_channels.update' => 'تعديل قناة بيع',
            'sales_channels.delete' => 'حذف قناة بيع',
            'sales_channels.activate' => 'تفعيل أو إيقاف قناة بيع',
            'sales_channels.reports' => 'عرض تقارير قنوات البيع',

            // المحادثات الداخلية
            'chat.view' => 'عرض محادثة الفرع',
            'chat.send' => 'إرسال رسائل في المحادثات',
            'chat.attachments' => 'إرسال صور وملفات في المحادثات',
            'chat.view_all_branches' => 'عرض محادثات جميع الفروع',
            'chat.manage' => 'إدارة نظام المحادثات بالكامل',
            'chat.direct.start_all' => 'بدء محادثة مباشرة مع أي مستخدم',
            'chat.direct.start_location' => 'بدء محادثة مباشرة مع مستخدمي الموقع نفسه',

            // المطعم
            'restaurant.view' => 'عرض لوحة تشغيل المطعم',
            'restaurant.view_all_locations' => 'عرض تشغيل المطعم لجميع الفروع',
            'restaurant_pos.use' => 'استخدام نقطة بيع المطعم',
            'restaurant_tables.view' => 'عرض طاولات المطعم',
            'restaurant_tables.manage' => 'إدارة مناطق وطاولات المطعم',
            'restaurant_tables.open_session' => 'فتح جلسة طاولة',
            'restaurant_tables.close_session' => 'إغلاق جلسة طاولة',

            // المطبخ وKDS
            'kitchen.view' => 'عرض تذاكر المطبخ',
            'kitchen.view_all_locations' => 'عرض مطابخ جميع الفروع',
            'kitchen.stations.manage' => 'إدارة محطات المطبخ وتوجيه المنتجات',
            'kitchen.ticket.start' => 'بدء تحضير تذكرة مطبخ',
            'kitchen.ticket.ready' => 'اعتماد تذكرة مطبخ كجاهزة',
            'kitchen.ticket.serve' => 'تسجيل تسليم تذكرة مطبخ',
            'kitchen.ticket.priority' => 'تغيير أولوية تذكرة المطبخ',
            'kds.view' => 'عرض شاشة المطبخ KDS',

            // الوصفات والإنتاج والجودة
            'recipes.view' => 'عرض الوصفات',
            'recipes.manage' => 'إنشاء وتعديل مسودات الوصفات وإصداراتها',
            'recipes.approve' => 'اعتماد وأرشفة الوصفات',
            'recipes.cost.view' => 'عرض تكلفة مواد الوصفات',

            'production.view' => 'عرض دفعات الإنتاج',
            'production.view_all_locations' => 'عرض إنتاج جميع المواقع',
            'production.create' => 'إنشاء دفعة إنتاج',
            'production.release' => 'حجز المواد والإفراج عن دفعة الإنتاج',
            'production.start' => 'صرف المواد وبدء الإنتاج',
            'production.finish' => 'تسجيل الاستهلاك والناتج وإنهاء التشغيل',
            'production.cancel' => 'إلغاء دفعة قبل صرف المواد',
            'production.cost.view' => 'عرض تكلفة دفعات الإنتاج',

            'quality_control.view' => 'عرض دفعات الإنتاج بانتظار الجودة',
            'quality_control.decide' => 'اعتماد أو رفض ناتج دفعة الإنتاج',
        ];
    }

    public static function roleLabel(string $role): string
    {
        return self::roleLabels()[$role] ?? $role;
    }

    public static function groupLabel(string $group): string
    {
        return self::groupLabels()[$group] ?? 'صلاحيات أخرى';
    }

    public static function permissionLabel(string $permission): string
    {
        return self::permissionLabels()[$permission] ?? 'صلاحية غير معرفة';
    }

    public static function isLegacyPermission(string $permission): bool
    {
        return in_array($permission, [
            'cake_orders.manage',
            'employees.manage',
            'showroom_sweets_requests.update_status',
        ], true);
    }
}