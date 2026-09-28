<?php

namespace App\Services\Assistant;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AttendanceFeatureService;
use App\Services\ModuleService;

/**
 * One authorization boundary for the ERP assistant.
 *
 * The assistant never grants a user a system permission. It can only narrow
 * the topics already allowed by the user's normal ERP permissions.
 */
class AssistantAccessService
{
    public const TOPICS = [
        'cake_due' => [
            'label' => 'طلبات الكيك القادمة',
            'description' => 'الطلبات المطلوبة اليوم أو غدًا وأقرب مواعيد التسليم.',
        ],
        'cake_top_branch' => [
            'label' => 'مقارنة طلبات الكيك بين الفروع',
            'description' => 'ترتيب الفروع حسب عدد طلبات الكيك خلال الأسبوع.',
        ],
        'branch_cakes' => [
            'label' => 'كيك الفروع',
            'description' => 'طلبات كيك الفروع وحالاتها ومواعيدها.',
        ],
        'branch_sweets' => [
            'label' => 'حلويات الفروع',
            'description' => 'طلبات حلويات الفروع وحالاتها ومواعيدها.',
        ],
        'low_stock' => [
            'label' => 'المخزون والنواقص',
            'description' => 'المنتجات التي وصلت للحد الأدنى أو أقل.',
        ],
        'orders' => [
            'label' => 'الطلبات العادية',
            'description' => 'ملخص الطلبات وروابط فتح الطلبات المتاحة.',
        ],
        'sales_compare' => [
            'label' => 'المبيعات والتحصيلات',
            'description' => 'مقارنة المبيعات والتحصيلات اليومية حسب الفروع المتاحة.',
        ],
        'payments' => [
            'label' => 'الدفعات والتحصيلات',
            'description' => 'الدفعات المؤكدة والتي تنتظر التحقق.',
        ],
        'invoices' => [
            'label' => 'الفواتير',
            'description' => 'عدد الفواتير النشطة والمبالغ المتبقية.',
        ],
        'transfers' => [
            'label' => 'الحوالات البنكية',
            'description' => 'الحوالات الواردة التي تنتظر التحقق.',
        ],
        'attendance' => [
            'label' => 'الحضور',
            'description' => 'ملخص الحضور والتأخير للموظفين المتاحين.',
        ],
        'payroll' => [
            'label' => 'الرواتب',
            'description' => 'آخر دورة رواتب وصافي مستحقاتها.',
        ],
        'priorities' => [
            'label' => 'الأولويات والتنبيهات',
            'description' => 'ملخص المشاكل التي تحتاج تدخلًا الآن.',
        ],
        'reports' => [
            'label' => 'مركز التقارير',
            'description' => 'فتح التقارير المسموحة للمستخدم.',
        ],
    ];

    private const PAYMENT_PERMISSIONS = [
        'payments.record', 'payments.verify', 'payments.correct',
        'payments.refund', 'financial.branch.view',
        'financial.global.view', 'financial.collections.view',
    ];

    private const TRANSFER_PERMISSIONS = [
        'payments.record', 'payments.verify', 'financial.branch.view',
        'financial.global.view', 'financial.collections.view',
    ];

    public function __construct(
        private readonly ModuleService $modules,
        private readonly AttendanceFeatureService $attendanceFeatures
    ) {}

    /** @return array<string, array{label:string,description:string}> */
    public function topics(): array
    {
        return self::TOPICS;
    }

    public function canUse(User $user): bool
    {
        if (! (bool) SystemSetting::get('assistant_enabled', true)) {
            return false;
        }

        if ($user->assistantSetting && ! $user->assistantSetting->enabled) {
            return false;
        }

        return $this->allowedIntents($user) !== [];
    }

    /** @return list<string> */
    public function allowedIntents(User $user): array
    {
        if (! (bool) SystemSetting::get('assistant_enabled', true)
            || ($user->assistantSetting && ! $user->assistantSetting->enabled)) {
            return [];
        }

        $allowed = collect(array_keys(self::TOPICS))
            ->filter(fn (string $intent): bool => $this->baseAllowed($user, $intent))
            ->values()
            ->all();

        $setting = $user->assistantSetting;
        if (! $setting || $setting->topic_mode !== 'custom') {
            return $allowed;
        }

        return array_values(array_intersect($allowed, $setting->allowed_intents ?? []));
    }

    public function canIntent(User $user, string $intent): bool
    {
        return in_array($intent, $this->allowedIntents($user), true);
    }

    public function maxItems(User $user): int
    {
        $global = max(3, min(20, (int) SystemSetting::get('assistant_max_items', 8)));
        $personal = $user->assistantSetting?->max_items;

        return $personal ? max(3, min($global, (int) $personal)) : $global;
    }

    public function showSuggestions(): bool
    {
        return (bool) SystemSetting::get('assistant_show_suggestions', true);
    }

    public function allowActionSuggestions(User $user): bool
    {
        return (bool) ($user->assistantSetting?->allow_action_suggestions ?? false)
            && (bool) SystemSetting::get('assistant_read_only', true) === false;
    }

    private function baseAllowed(User $user, string $intent): bool
    {
        if ($intent === 'priorities') {
            return collect(['cake_due', 'low_stock', 'transfers', 'branch_cakes', 'branch_sweets'])
                ->contains(fn (string $part): bool => $this->baseAllowed($user, $part));
        }

        $module = match ($intent) {
            'cake_due', 'cake_top_branch', 'branch_cakes' => 'cake_orders',
            'branch_sweets' => 'bakery',
            'low_stock' => 'inventory',
            'orders' => 'sales',
            'sales_compare' => 'finance',
            'payments', 'transfers' => 'payments',
            'invoices' => 'invoices',
            'reports' => 'reports',
            'attendance', 'payroll' => 'employees',
            default => null,
        };

        if ($module === null || ! $this->modules->isEnabled($module)) {
            return false;
        }

        if ($intent === 'attendance' && ! $this->attendanceFeatures->attendanceEnabled()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return match ($intent) {
            'cake_due', 'cake_top_branch' => $user->can('cake_orders.view'),
            'branch_cakes' => $user->can('showroom_cake_requests.view'),
            'branch_sweets' => $user->can('showroom_sweets_requests.view'),
            'low_stock' => $user->can('inventory.view'),
            'orders' => $user->can('orders.view'),
            'sales_compare' => $user->canAny(['financial.dashboard.view', 'financial.sales.view']),
            'payments' => $user->canAny(self::PAYMENT_PERMISSIONS),
            'transfers' => $user->canAny(self::TRANSFER_PERMISSIONS),
            'invoices' => $user->can('invoices.view'),
            'attendance' => $user->can('attendance.view'),
            'payroll' => $user->can('payroll.view'),
            'reports' => $user->can('reports.view'),
            default => false,
        };
    }
}
