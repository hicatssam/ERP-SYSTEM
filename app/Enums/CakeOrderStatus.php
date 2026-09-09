<?php

namespace App\Enums;

enum CakeOrderStatus: string
{
    case Draft = 'draft';
    case PendingFactoryReview = 'pending_factory_review';
    case Accepted = 'accepted';
    case ModificationRequested = 'modification_requested';
    case Rejected = 'rejected';
    case Scheduled = 'scheduled';
    case InPreparation = 'in_preparation';
    case Decorating = 'decorating';
    case QualityCheck = 'quality_check';
    case Ready = 'ready';
    case SentToBranch = 'sent_to_branch';
    case ReceivedByBranch = 'received_by_branch';
    case ReadyForCustomer = 'ready_for_customer';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Delayed = 'delayed';
    case IssueOpen = 'issue_open';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::PendingFactoryReview => 'بانتظار مراجعة المصنع',
            self::Accepted => 'مقبول',
            self::ModificationRequested => 'مطلوب تعديل',
            self::Rejected => 'مرفوض',
            self::Scheduled => 'مجدول للإنتاج',
            self::InPreparation => 'قيد التحضير',
            self::Decorating => 'قيد التزيين',
            self::QualityCheck => 'قيد فحص الجودة',
            self::Ready => 'جاهز',
            self::SentToBranch => 'أُرسل إلى الفرع',
            self::ReceivedByBranch => 'استلمه الفرع',
            self::ReadyForCustomer => 'جاهز للتسليم للعميل',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغي',
            self::Delayed => 'متأخر',
            self::IssueOpen => 'توجد مشكلة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft,
            self::Delayed => 'secondary',

            self::PendingFactoryReview,
            self::ModificationRequested => 'warning',

            self::Accepted,
            self::Scheduled,
            self::InPreparation,
            self::Decorating,
            self::QualityCheck,
            self::Ready,
            self::SentToBranch,
            self::ReceivedByBranch,
            self::ReadyForCustomer => 'primary',

            self::Completed => 'success',

            self::Cancelled,
            self::Rejected,
            self::IssueOpen => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Cancelled,
            self::Rejected,
        ], true);
    }
}