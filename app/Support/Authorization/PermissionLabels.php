<?php

namespace App\Support\Authorization;

/**
 * Single Arabic dictionary for the Spatie permission names stored in the
 * database. The permission codes stay stable for middleware and policies;
 * only their presentation changes for people managing roles.
 */
final class PermissionLabels
{
    /** @return array<string, string> */
    public static function groupLabels(): array
    {
        return [
            'cake_orders' => 'طلبات الكيك الخاصة',
            'cash_sessions' => 'جلسات الكاشير',
            'customers' => 'العملاء',
            'dashboard' => 'لوحة التحكم',
            'employees' => 'الموظفون',
            'financial' => 'الإدارة المالية',
            'goods_receipts' => 'سندات استلام المشتريات',
            'inventory' => 'المخزون',
            'invoices' => 'فواتير العملاء',
            'locations' => 'الفروع والمصنع',
            'notifications' => 'الإشعارات',
            'orders' => 'الطلبات',
            'payment_methods' => 'طرق الدفع',
            'payments' => 'مدفوعات العملاء',
            'procurement' => 'إعدادات المشتريات',
            'products' => 'المنتجات',
            'purchase_orders' => 'أوامر الشراء',
            'purchase_returns' => 'مرتجعات الموردين',
            'receiving_invoices' => 'فواتير الاستلام الداخلية',
            'reports' => 'التقارير',
            'roles' => 'الأدوار والصلاحيات',
            'sales_channels' => 'قنوات البيع',
            'settings' => 'إعدادات النظام',
            'showroom_cake_requests' => 'طلبات كيك الفروع',
            'showroom_sweets_requests' => 'طلبات حلويات الفروع',
            'stock_receiving_invoices' => 'فواتير الاستلام الداخلية',
            'stock_requests' => 'طلبات المخزون',
            'stock_transfers' => 'تحويلات المخزون',
            'supplier_invoices' => 'فواتير الموردين',
            'supplier_payments' => 'دفعات الموردين',
            'suppliers' => 'الموردون',
            'users' => 'المستخدمون',
        ];
    }

    /** @return array<string, string> */
    public static function permissionLabels(): array
    {
        return [
            'cake_orders.accept' => 'قبول طلب كيك',
            'cake_orders.create' => 'إنشاء طلب كيك',
            'cake_orders.decorate' => 'تزيين الكيك',
            'cake_orders.delete' => 'حذف طلب كيك',
            'cake_orders.dispatch' => 'إرسال الكيك إلى الفرع',
            'cake_orders.edit' => 'تعديل طلب كيك',
            'cake_orders.manage' => 'إدارة طلبات الكيك',
            'cake_orders.prepare' => 'تجهيز الكيك',
            'cake_orders.quality_check' => 'فحص جودة الكيك',
            'cake_orders.receive' => 'استلام الكيك في الفرع',
            'cake_orders.reject' => 'رفض طلب كيك',
            'cake_orders.request_modification' => 'طلب تعديل على الكيك',
            'cake_orders.review' => 'مراجعة طلب كيك',
            'cake_orders.schedule' => 'جدولة طلب الكيك',
            'cake_orders.view' => 'عرض طلبات الكيك',

            'cash_sessions.manage' => 'إدارة جلسات الكاشير',
            'customers.view' => 'عرض العملاء',

            'dashboard.view' => 'الدخول إلى لوحة التحكم',
            'dashboard.orders' => 'إظهار بطاقة طلبات اليوم',
            'dashboard.sales' => 'إظهار بطاقة المبيعات',
            'dashboard.collections' => 'إظهار بطاقة التحصيلات',
            'dashboard.cash' => 'إظهار بطاقة الرصيد النقدي',
            'dashboard.profit' => 'إظهار بطاقة صافي الربح',
            'dashboard.inventory' => 'إظهار تنبيهات نقص المخزون',
            'dashboard.stock_requests' => 'إظهار طلبات المخزون المعلقة',
            'dashboard.cake_orders' => 'إظهار الكيك قيد الإنتاج',
            'dashboard.cake_pipeline' => 'إظهار خط إنتاج الكيك',
            'dashboard.products_chart' => 'إظهار المنتجات الأكثر مبيعاً',
            'dashboard.revenue_chart' => 'إظهار مخطط الإيرادات',
            'dashboard.branch_comparison' => 'إظهار مقارنة مبيعات الفروع',
            'dashboard.procurement' => 'إظهار لوحة المشتريات والموردين',

            'employees.manage' => 'إدارة الموظفين',
            'users.manage' => 'إدارة المستخدمين',
            'roles.manage' => 'إدارة الأدوار والصلاحيات',
            'locations.manage' => 'إدارة الفروع والمصنع',
            'notifications.manage' => 'إدارة الإشعارات',
            'settings.manage' => 'إدارة إعدادات النظام',

            'financial.adjustments.approve' => 'اعتماد التسويات المالية',
            'financial.adjustments.create' => 'إنشاء تسوية مالية',
            'financial.branch.view' => 'عرض مالية الفرع',
            'financial.collections.view' => 'عرض التحصيلات المالية',
            'financial.dashboard.view' => 'عرض لوحة المعلومات المالية',
            'financial.global.view' => 'عرض مالية جميع الفروع',
            'financial.outstanding.view' => 'عرض المبالغ المستحقة',
            'financial.periods.close' => 'إغلاق فترة مالية',
            'financial.periods.open' => 'فتح فترة مالية',
            'financial.periods.override_close' => 'تجاوز إغلاق الفترة المالية',
            'financial.periods.view' => 'عرض الفترات المالية',
            'financial.refunds.view' => 'عرض المرتجعات المالية',
            'financial.reports.export' => 'تصدير التقارير المالية',
            'financial.reports.view' => 'عرض التقارير المالية',
            'financial.sales.view' => 'عرض المبيعات المالية',

            'inventory.adjust' => 'تسوية المخزون',
            'inventory.count' => 'إجراء جرد للمخزون',
            'inventory.view' => 'عرض المخزون',

            'invoices.cancel' => 'إلغاء فاتورة عميل',
            'invoices.create' => 'إنشاء فاتورة عميل',
            'invoices.print' => 'طباعة فاتورة عميل',
            'invoices.view' => 'عرض فواتير العملاء',

            'orders.cancel' => 'إلغاء طلب',
            'orders.confirm' => 'تأكيد طلب',
            'orders.create' => 'إنشاء طلب',
            'orders.edit' => 'تعديل طلب',
            'orders.view' => 'عرض الطلبات',

            'payment_methods.manage' => 'إدارة طرق الدفع',
            'payment_methods.view' => 'عرض طرق الدفع',
            'payments.correct' => 'تصحيح دفعة',
            'payments.record' => 'تسجيل دفعة',
            'payments.refund' => 'استرجاع دفعة',
            'payments.verify' => 'التحقق من دفعة',

            'products.create' => 'إضافة منتج',
            'products.update' => 'تعديل منتج',
            'products.view' => 'عرض المنتجات',

            'receiving_invoices.view' => 'عرض فواتير الاستلام الداخلية',
            'stock_receiving_invoices.view' => 'عرض فواتير الاستلام الداخلية',
            'reports.branch_sales' => 'تقرير مبيعات الفروع',
            'reports.export' => 'تصدير التقارير',
            'reports.payment_methods' => 'تقرير طرق الدفع',
            'reports.view' => 'عرض التقارير',

            'sales_channels.view' => 'عرض قنوات البيع',
            'sales_channels.create' => 'إضافة قناة بيع',
            'sales_channels.update' => 'تعديل قناة بيع',
            'sales_channels.delete' => 'حذف قناة بيع',
            'sales_channels.activate' => 'تفعيل أو إيقاف قناة بيع',
            'sales_channels.reports' => 'عرض تقارير قنوات البيع',

            'showroom_cake_requests.view' => 'عرض طلبات كيك الفروع',
            'showroom_cake_requests.update_status' => 'تحديث حالة طلب كيك الفرع',

            'showroom_sweets_requests.view' => 'عرض طلبات حلويات الفروع',
            'showroom_sweets_requests.view_all' => 'عرض طلبات حلويات جميع الفروع',
            'showroom_sweets_requests.create' => 'إنشاء طلب حلويات للفرع',
            'showroom_sweets_requests.update_status' => 'تحديث حالة طلب حلويات الفرع',
            'showroom_sweets_requests.delete' => 'حذف طلب حلويات ملغى',

            'stock_requests.create' => 'إنشاء طلب مخزون',
            'stock_requests.review' => 'مراجعة طلبات المخزون',
            'stock_requests.view' => 'عرض طلبات المخزون',
            'stock_transfers.dispatch' => 'إرسال تحويل مخزون',
            'stock_transfers.receive' => 'استلام تحويل مخزون',
            'stock_transfers.view' => 'عرض تحويلات المخزون',

            'suppliers.view' => 'عرض الموردين',
            'suppliers.create' => 'إضافة مورد',
            'suppliers.update' => 'تعديل المورد',
            'suppliers.delete' => 'أرشفة المورد',

            'purchase_orders.view' => 'عرض أوامر الشراء',
            'purchase_orders.create' => 'إنشاء أمر شراء',
            'purchase_orders.update' => 'تعديل أمر شراء',
            'purchase_orders.approve' => 'اعتماد أمر شراء',
            'purchase_orders.cancel' => 'إلغاء أمر شراء',

            'goods_receipts.view' => 'عرض سندات الاستلام',
            'goods_receipts.create' => 'إنشاء سند استلام',
            'goods_receipts.approve' => 'ترحيل سند الاستلام إلى المخزون',

            'purchase_returns.view' => 'عرض مرتجعات الموردين',
            'purchase_returns.create' => 'إنشاء مرتجع مورد',
            'purchase_returns.approve' => 'ترحيل مرتجع المورد',
            'purchase_returns.cancel' => 'إلغاء مرتجع مورد',

            'supplier_invoices.view' => 'عرض فواتير الموردين',
            'supplier_invoices.create' => 'تسجيل فاتورة مورد',
            'supplier_invoices.cancel' => 'إلغاء فاتورة مورد',
            'supplier_payments.view' => 'عرض دفعات الموردين',
            'supplier_payments.create' => 'تسجيل دفعة للمورد',

            'procurement.reports.view' => 'عرض تقارير المشتريات',
            'procurement.exchange_rates.view' => 'عرض العملات وأسعار الصرف',
            'procurement.exchange_rates.manage' => 'إدارة أسعار الصرف',
        ];
    }

    /** @return array<string, string> */
    public static function roleLabels(): array
    {
        return [
            'Admin' => 'مدير النظام',
            'Super Admin' => 'مدير أعلى',
            'super-admin' => 'مدير أعلى',
            'Accountant' => 'المحاسب',
            'Branch Employee' => 'موظف فرع',
            'Branch Manager' => 'مدير فرع',
            'Cake Designer' => 'مصمم كيك',
            'Cashier' => 'كاشير',
            'Dispatcher' => 'مسؤول إرسال',
            'Factory Manager' => 'مدير المصنع',
            'General Manager' => 'المدير العام',
            'Inventory Manager' => 'مدير المخزون',
            'Production Employee' => 'موظف إنتاج',
            'Quality Control' => 'مراقب الجودة',
        ];
    }

    public static function group(string $permission): string
    {
        return explode('.', $permission)[0];
    }

    public static function groupLabel(string $group): string
    {
        return self::groupLabels()[$group] ?? 'صلاحيات أخرى';
    }

    public static function label(string $permission): string
    {
        return self::permissionLabels()[$permission] ?? 'صلاحية غير مسماة';
    }

    public static function roleLabel(string $role): string
    {
        return self::roleLabels()[$role] ?? $role;
    }
}
