<?php

namespace App\Support;

use BackedEnum;
use UnitEnum;

final class ArabicUi
{
    private const MODULES = [
        'dashboard' => 'لوحة التحكم',
        'locations' => 'الفروع والمصنع',
        'employees' => 'الموظفون',
        'users' => 'المستخدمون',
        'roles' => 'الأدوار والصلاحيات',
        'categories' => 'الفئات',
        'products' => 'المنتجات',
        'customers' => 'العملاء',
        'orders' => 'الطلبات',
        'cake_orders' => 'طلبات الكيك الخاصة',
        'showroom_cake_requests' => 'طلبات كيك الفروع',
        'showroom_sweets_requests' => 'طلبات حلويات الفروع',
        'inventory' => 'المخزون',
        'stock_counts' => 'جرد المخزون',
        'stock_requests' => 'طلبات المخزون',
        'stock_transfers' => 'تحويلات المخزون',
        'procurement' => 'المشتريات',
        'suppliers' => 'الموردون',
        'purchase_orders' => 'أوامر الشراء',
        'goods_receipts' => 'سندات استلام المشتريات',
        'purchase_returns' => 'مرتجعات الموردين',
        'supplier_invoices' => 'فواتير الموردين',
        'supplier_payments' => 'دفعات الموردين',
        'exchange_rates' => 'أسعار الصرف',
        'payments' => 'المدفوعات',
        'invoices' => 'الفواتير',
        'cash_sessions' => 'جلسات الكاشير',
        'financial' => 'الإدارة المالية',
        'financial_periods' => 'الفترات المالية',
        'reports' => 'التقارير',
        'report_schedules' => 'التقارير المجدولة',
        'notifications' => 'الإشعارات',
        'payment_methods' => 'طرق الدفع',
        'receiving_invoices' => 'فواتير الاستلام',
        'settings' => 'إعدادات النظام',
        'sales_channels' => 'قنوات البيع',
    ];

    private const GENERAL = [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'enabled' => 'مفعّل',
        'disabled' => 'معطّل',
        'draft' => 'مسودة',
        'pending' => 'قيد الانتظار',
        'submitted' => 'تم الإرسال',
        'under_review' => 'قيد المراجعة',
        'approved' => 'معتمد',
        'partially_approved' => 'معتمد جزئيًا',
        'rejected' => 'مرفوض',
        'cancelled' => 'ملغى',
        'completed' => 'مكتمل',
        'closed' => 'مغلق',
        'open' => 'مفتوح',
        'posted' => 'مرحّل',
        'confirmed' => 'مؤكد',
        'failed' => 'فشل',
        'refunded' => 'مسترد',
        'reversed' => 'معكوس',
        'archived' => 'مؤرشف',
        'scheduled' => 'مجدول',
        'received' => 'تم الاستلام',
        'dispatched' => 'تم الإرسال',
        'delivered' => 'تم التسليم',
        'ready' => 'جاهز',
        'processing' => 'قيد المعالجة',
        'in_progress' => 'قيد التنفيذ',
        'branch' => 'فرع',
        'factory' => 'مصنع',
        'cash' => 'نقدًا',
        'bank_transfer' => 'تحويل بنكي',
        'electronic_wallet' => 'محفظة إلكترونية',
        'card_pos' => 'بطاقة / نقطة بيع',
        'piece' => 'قطعة',
        'tray' => 'صينية',
        'kg' => 'كغ',
        'cup' => 'كوب',
        'box' => 'علبة',
    ];

    private const CONTEXT = [
        'cake_orders' => [
            'draft' => 'مسودة',
            'pending_deposit' => 'بانتظار دفع العربون',
            'deposit_paid' => 'تم دفع العربون',
            'pending_factory_review' => 'بانتظار مراجعة المصنع',
            'accepted' => 'تم قبول الطلب',
            'rejected' => 'مرفوض',
            'scheduled' => 'تمت الجدولة',
            'in_preparation' => 'قيد التحضير',
            'decorating' => 'قيد التزيين',
            'quality_check' => 'قيد فحص الجودة',
            'ready' => 'جاهز في المصنع',
            'sent_to_branch' => 'تم الإرسال إلى الفرع',
            'dispatched_to_branch' => 'تم الإرسال إلى الفرع',
            'received_by_branch' => 'تم الاستلام في الفرع',
            'ready_for_customer' => 'جاهز لاستلام العميل',
            'delivered' => 'تم التسليم',
            'completed' => 'مكتمل',
            'delayed' => 'متأخر',
            'issue_open' => 'توجد ملاحظة عند الاستلام',
            'cancelled' => 'ملغى',
        ],
        'orders' => [
            'draft' => 'مسودة',
            'confirmed' => 'مؤكد',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغى',
        ],
        'payment_status' => [
            'unpaid' => 'غير مدفوع',
            'payment_pending' => 'بانتظار الدفع',
            'pending' => 'بانتظار الدفع',
            'pending_verification' => 'بانتظار التحقق',
            'partially_paid' => 'مدفوع جزئيًا',
            'paid' => 'مدفوع بالكامل',
            'confirmed' => 'مؤكد',
            'failed' => 'فشل الدفع',
            'rejected' => 'مرفوض',
            'refunded' => 'تم الاسترجاع',
            'cancelled' => 'ملغى',
        ],
        'payment_arrangement' => [
            'pay_now' => 'دفع كامل الآن',
            'deposit' => 'عربون',
            'partial_payment' => 'دفع جزئي',
            'pay_on_pickup' => 'الدفع عند الاستلام',
            'pending_verification' => 'بانتظار التحقق',
        ],
        'purchase_orders' => [
            'draft' => 'مسودة',
            'submitted' => 'تم الإرسال للاعتماد',
            'approved' => 'معتمد',
            'partially_received' => 'مستلم جزئيًا',
            'received' => 'مستلم بالكامل',
            'closed' => 'مغلق',
            'cancelled' => 'ملغى',
        ],
        'goods_receipts' => [
            'draft' => 'مسودة',
            'posted' => 'مرحّل للمخزون',
            'cancelled' => 'ملغى',
        ],
        'purchase_returns' => [
            'draft' => 'مسودة',
            'posted' => 'مرحّل',
            'cancelled' => 'ملغى',
        ],
        'supplier_invoices' => [
            'draft' => 'مسودة',
            'posted' => 'مرحّلة',
            'partially_paid' => 'مدفوعة جزئيًا',
            'paid' => 'مدفوعة بالكامل',
            'cancelled' => 'ملغاة',
        ],
        'supplier_payments' => [
            'pending' => 'قيد الانتظار',
            'confirmed' => 'مؤكدة',
            'posted' => 'مرحّلة',
            'cancelled' => 'ملغاة',
            'reversed' => 'معكوسة',
        ],
        'stock_requests' => [
            'draft' => 'مسودة',
            'pending_factory_review' => 'بانتظار مراجعة المصنع',
            'approved' => 'معتمد',
            'partially_approved' => 'معتمد جزئيًا',
            'rejected' => 'مرفوض',
            'dispatched' => 'تم الإرسال',
            'received' => 'تم الاستلام',
            'cancelled' => 'ملغى',
        ],
        'stock_transfers' => [
            'draft' => 'مسودة',
            'dispatched' => 'تم الإرسال',
            'received' => 'تم الاستلام',
            'cancelled' => 'ملغى',
        ],
        'employment_status' => [
            'active' => 'على رأس العمل',
            'inactive' => 'غير نشط',
            'on_leave' => 'في إجازة',
            'terminated' => 'منتهي الخدمة',
        ],
        'cash_sessions' => [
            'open' => 'جلسة مفتوحة',
            'closed' => 'جلسة مغلقة',
        ],
        'financial_periods' => [
            'open' => 'فترة مفتوحة',
            'closed' => 'فترة مغلقة',
        ],
        'invoice_status' => [
            'active' => 'فعّالة',
            'cancelled' => 'ملغاة',
        ],
        'invoice_type' => [
            'regular_order' => 'فاتورة طلب عادي',
            'special_cake_order' => 'فاتورة طلب كيك خاص',
            'supplier_invoice' => 'فاتورة مورد',
            'purchase' => 'مشتريات',
            'sales' => 'مبيعات',
        ],
        'order_type' => [
            'order' => 'طلب عادي',
            'special_cake_order' => 'طلب كيك خاص',
        ],
        'location_type' => [
            'branch' => 'فرع',
            'factory' => 'مصنع',
        ],
        'payment_method_type' => [
            'cash' => 'نقدًا',
            'bank_transfer' => 'تحويل بنكي',
            'electronic_wallet' => 'محفظة إلكترونية',
            'card_pos' => 'بطاقة / نقطة بيع',
        ],
        'sales_channels' => [
            'active' => 'نشطة',
            'inactive' => 'غير نشطة',
            'archived' => 'مؤرشفة',
        ],
        'boolean' => [
            '1' => 'نعم',
            '0' => 'لا',
            'true' => 'نعم',
            'false' => 'لا',
        ],
    ];

    private const SPECIAL_PERMISSIONS = [
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
        'dashboard.procurement' => 'إظهار لوحة المشتريات',
        'customers.view' => 'عرض عملاء الفرع',
        'customers.view_all' => 'عرض عملاء جميع الفروع',
        'customers.create' => 'إضافة عميل',
        'customers.update' => 'تعديل عميل',
        'customers.delete' => 'حذف عميل',
        'employees.view' => 'عرض موظفي الموقع / الفرع',
        'employees.view_all' => 'عرض موظفي جميع الفروع والمصنع',
        'employees.create' => 'إضافة موظف',
        'employees.update' => 'تعديل موظف',
        'employees.delete' => 'حذف موظف',
        'employees.manage' => 'إدارة الموظفين بالكامل',
    ];

    private const ACTIONS = [
        'view' => 'عرض',
        'create' => 'إنشاء',
        'update' => 'تعديل',
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'manage' => 'إدارة',
        'approve' => 'اعتماد',
        'cancel' => 'إلغاء',
        'print' => 'طباعة',
        'export' => 'تصدير',
        'review' => 'مراجعة',
        'record' => 'تسجيل',
        'verify' => 'التحقق من',
        'correct' => 'تصحيح',
        'refund' => 'استرجاع',
        'dispatch' => 'إرسال',
        'receive' => 'استلام',
        'activate' => 'تفعيل',
        'submit' => 'إرسال للاعتماد',
        'reports' => 'تقارير',
        'adjust' => 'تسوية',
        'count' => 'جرد',
        'open' => 'فتح',
        'close' => 'إغلاق',
        'accept' => 'قبول',
        'reject' => 'رفض',
        'schedule' => 'جدولة',
        'prepare' => 'تجهيز',
        'decorate' => 'تزيين',
        'quality_check' => 'فحص جودة',
        'request_modification' => 'طلب تعديل',
    ];

    public static function module(?string $key): string
    {
        return $key && isset(self::MODULES[$key])
            ? self::MODULES[$key]
            : 'غير محدد';
    }

    public static function label(mixed $value, ?string $context = null): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof UnitEnum) {
            $value = $value->name;
        } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        if ($value === null || $value === '') {
            return '—';
        }

        $key = (string) $value;

        if ($context && isset(self::CONTEXT[$context][$key])) {
            return self::CONTEXT[$context][$key];
        }

        if (isset(self::GENERAL[$key])) {
            return self::GENERAL[$key];
        }

        if (preg_match('/\p{Arabic}/u', $key) === 1 || is_numeric($key)) {
            return $key;
        }

        // لا نُظهر قيمة إنجليزية غير مترجمة للمستخدم النهائي.
        return 'غير محدد';
    }

    public static function permission(?string $permission): string
    {
        if (! $permission) {
            return '—';
        }

        if (isset(self::SPECIAL_PERMISSIONS[$permission])) {
            return self::SPECIAL_PERMISSIONS[$permission];
        }

        $parts = explode('.', $permission);
        $moduleKey = $parts[0] ?? '';
        $actionKey = $parts[count($parts) - 1] ?? '';

        if ($moduleKey === 'procurement' && ($parts[1] ?? null) === 'exchange_rates') {
            $moduleLabel = 'أسعار الصرف';
        } elseif ($moduleKey === 'procurement' && ($parts[1] ?? null) === 'reports') {
            $moduleLabel = 'تقارير المشتريات';
        } else {
            $moduleLabel = self::MODULES[$moduleKey] ?? 'غير محدد';
        }

        $actionLabel = self::ACTIONS[$actionKey] ?? null;

        return $actionLabel
            ? trim($actionLabel . ' ' . $moduleLabel)
            : $moduleLabel;
    }
}