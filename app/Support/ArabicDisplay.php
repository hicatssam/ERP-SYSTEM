<?php

namespace App\Support;

use BackedEnum;

final class ArabicDisplay
{
    private const STATUSES = [
        'draft' => 'مسودة', 'pending' => 'قيد الانتظار', 'submitted' => 'مرسلة للمراجعة',
        'under_review' => 'قيد المراجعة', 'approved' => 'معتمدة', 'rejected' => 'مرفوضة',
        'posted' => 'مرحّلة', 'cancelled' => 'ملغاة', 'canceled' => 'ملغاة',
        'active' => 'نشطة', 'inactive' => 'غير نشطة', 'open' => 'مفتوحة', 'closed' => 'مغلقة',
        'paid' => 'مدفوعة', 'unpaid' => 'غير مدفوعة', 'partially_paid' => 'مدفوعة جزئيًا',
        'overdue' => 'متأخرة', 'confirmed' => 'مؤكدة', 'completed' => 'مكتملة',
        'processing' => 'قيد المعالجة', 'in_progress' => 'قيد التنفيذ', 'ready' => 'جاهزة',
        'received' => 'مستلمة', 'partially_received' => 'مستلمة جزئيًا',
        'pending_dispatch' => 'بانتظار الإرسال', 'dispatched' => 'تم الإرسال',
        'delivered' => 'تم التسليم', 'failed' => 'فشلت', 'voided' => 'ملغاة محاسبيًا',
        'present' => 'حاضر', 'absent' => 'غائب', 'leave' => 'إجازة', 'late' => 'متأخر',
        'queued' => 'في قائمة الانتظار', 'preparing' => 'قيد التحضير', 'served' => 'تم التقديم',
        'released' => 'مفرج عنها', 'awaiting_quality' => 'بانتظار فحص الجودة',
        'passed' => 'ناجحة', 'warning' => 'تحذير', 'error' => 'خطأ', 'success' => 'ناجحة',
        'lead' => 'عميل محتمل', 'vip' => 'عميل مميز',
        'archived' => 'مؤرشفة', 'blocked' => 'محظورة', 'terminated' => 'منتهية الخدمة',
        'accepted' => 'مقبولة', 'partially_accepted' => 'مقبولة جزئيًا',
        'pending_factory_review' => 'بانتظار مراجعة المصنع',
        'modification_requested' => 'مطلوب تعديل', 'scheduled' => 'مجدولة',
        'in_preparation' => 'قيد التجهيز', 'decorating' => 'قيد التزيين',
        'quality_check' => 'قيد فحص الجودة', 'sent_to_branch' => 'مرسلة إلى الفرع',
        'received_by_branch' => 'مستلمة من الفرع', 'ready_for_customer' => 'جاهزة للعميل',
        'delayed' => 'متأخرة', 'issue_open' => 'توجد ملاحظة مفتوحة',
        'discrepancy_open' => 'يوجد فرق مفتوح', 'resolved' => 'تمت المعالجة',
        'ready_for_dispatch' => 'جاهزة للإرسال', 'out_for_delivery' => 'قيد التوصيل',
        'received_at_branch' => 'مستلمة في الفرع', 'fulfilled' => 'تم التنفيذ',
        'pending_verification' => 'بانتظار التحقق', 'corrected' => 'مصححة',
        'refunded' => 'مستردة', 'partially_refunded' => 'مستردة جزئيًا',
        'payment_pending' => 'بانتظار الدفع',
        'pending_payment_verification' => 'بانتظار التحقق من الدفع',
        'assigned' => 'تم التعيين', 'picked_up' => 'تم الاستلام للتوصيل',
        'processed' => 'تمت المعالجة', 'partial' => 'مقبولة جزئيًا',
        'pass' => 'مقبولة', 'fail' => 'مرفوضة', 'na' => 'غير منطبق',
        'void' => 'ملغاة محاسبيًا',
        'in' => 'إدخال مخزون', 'out' => 'إخراج مخزون', 'adjustment' => 'تسوية مخزون',
    ];

    private const ACTIONS = [
        'created' => 'إنشاء', 'updated' => 'تعديل', 'submitted' => 'إرسال للمراجعة',
        'reviewed' => 'مراجعة', 'approved' => 'اعتماد', 'rejected' => 'رفض',
        'posted' => 'ترحيل', 'cancelled' => 'إلغاء', 'paid' => 'دفع',
        'refunded' => 'استرداد', 'confirmed' => 'تأكيد', 'completed' => 'إكمال',
        'received' => 'استلام', 'dispatched' => 'إرسال', 'delivered' => 'تسليم',
    ];

    private const CURRENCIES = [
        'ILS' => 'شيكل', 'USD' => 'دولار أمريكي', 'JOD' => 'دينار أردني',
        'EUR' => 'يورو', 'GBP' => 'جنيه إسترليني', 'SAR' => 'ريال سعودي',
        'AED' => 'درهم إماراتي',
    ];

    private const ROLES = [
        'admin' => 'مدير النظام', 'super-admin' => 'مدير النظام',
        'manager' => 'مدير', 'supervisor' => 'مشرف', 'cashier' => 'أمين صندوق',
        'waiter' => 'نادل', 'kitchen' => 'موظف مطبخ', 'inventory' => 'موظف مخزون',
        'accountant' => 'محاسب', 'branch manager' => 'مدير فرع',
        'cake designer' => 'مصمم كيك',
    ];

    public static function status(mixed $status): string
    {
        if (is_object($status) && method_exists($status, 'label')) {
            return (string) $status->label();
        }

        $value = $status instanceof BackedEnum ? $status->value : (string) $status;

        return self::STATUSES[strtolower($value)] ?? 'حالة غير معرّفة';
    }

    public static function action(mixed $action): string
    {
        $value = $action instanceof BackedEnum ? $action->value : (string) $action;

        return self::ACTIONS[strtolower($value)] ?? 'عملية نظام';
    }

    public static function currency(?string $code): string
    {
        return self::CURRENCIES[strtoupper((string) $code)] ?? 'عملة غير معرّفة';
    }

    public static function role(?string $role): string
    {
        return self::ROLES[strtolower(trim((string) $role))] ?? 'مستخدم نظام';
    }
}
