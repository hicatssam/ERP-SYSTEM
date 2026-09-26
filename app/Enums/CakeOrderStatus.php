<?php

namespace App\Enums;

enum CakeOrderStatus: string
{
    /*
     * Draft is a technical state used only while a new order is being saved.
     * The visible workflow starts at Pending.
     */
    case Draft = 'draft';

    // Simplified active workflow.
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /*
     * Legacy values are kept temporarily for backwards compatibility with
     * historical rows and status-history records. New transitions never target
     * these values.
     */
    case PendingFactoryReview = 'pending_factory_review';
    case Accepted = 'accepted';
    case ModificationRequested = 'modification_requested';
    case Rejected = 'rejected';
    case Scheduled = 'scheduled';
    case InPreparation = 'in_preparation';
    case Decorating = 'decorating';
    case QualityCheck = 'quality_check';
    case SentToBranch = 'sent_to_branch';
    case ReceivedByBranch = 'received_by_branch';
    case ReadyForCustomer = 'ready_for_customer';
    case Delayed = 'delayed';
    case IssueOpen = 'issue_open';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة داخلية',
            self::Pending => 'قيد المراجعة',
            self::InProgress => 'قيد التنفيذ',
            self::Ready => 'جاهز للاستلام',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغي',

            self::PendingFactoryReview => 'بانتظار مراجعة المصنع',
            self::Accepted => 'مقبول',
            self::ModificationRequested => 'مطلوب تعديل',
            self::Rejected => 'مرفوض',
            self::Scheduled => 'مجدول للإنتاج',
            self::InPreparation => 'قيد التحضير',
            self::Decorating => 'قيد التزيين',
            self::QualityCheck => 'قيد فحص الجودة',
            self::SentToBranch => 'أُرسل إلى الفرع',
            self::ReceivedByBranch => 'استلمه الفرع',
            self::ReadyForCustomer => 'جاهز للتسليم للعميل',
            self::Delayed => 'متأخر',
            self::IssueOpen => 'توجد مشكلة',
        };
    }

    public function color(): string
    {
        return match ($this->workflowValue()) {
            'pending' => 'warning',
            'in_progress' => 'primary',
            'ready' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }

    public function isTerminal(): bool
    {
        return in_array(
            $this->workflowValue(),
            [
                self::Completed->value,
                self::Cancelled->value,
            ],
            true
        );
    }

    /**
     * Canonical workflow value used by the simplified four-stage flow.
     */
    public function workflowValue(): string
    {
        return match ($this) {
            self::Draft => self::Draft->value,

            self::Pending,
            self::PendingFactoryReview,
            self::ModificationRequested =>
                self::Pending->value,

            self::InProgress,
            self::Accepted,
            self::Scheduled,
            self::InPreparation,
            self::Decorating,
            self::QualityCheck,
            self::Delayed,
            self::IssueOpen =>
                self::InProgress->value,

            self::Ready,
            self::SentToBranch,
            self::ReceivedByBranch,
            self::ReadyForCustomer =>
                self::Ready->value,

            self::Completed =>
                self::Completed->value,

            self::Cancelled,
            self::Rejected =>
                self::Cancelled->value,
        };
    }

    /**
     * Only these statuses should appear in filters and the visible workflow.
     *
     * @return array<int, self>
     */
    public static function workflowCases(): array
    {
        return [
            self::Pending,
            self::InProgress,
            self::Ready,
            self::Completed,
            self::Cancelled,
        ];
    }
}
