<?php

namespace App\Enums;

enum ProductionOrderStatus: string
{
    case DRAFT = 'draft';
    case RELEASED = 'released';
    case IN_PROGRESS = 'in_progress';
    case AWAITING_QUALITY = 'awaiting_quality';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    // Backward-compatible names used by the original production workflow.
    public const Draft = self::DRAFT;
    public const Released = self::RELEASED;
    public const InProgress = self::IN_PROGRESS;
    public const AwaitingQuality = self::AWAITING_QUALITY;
    public const Completed = self::COMPLETED;
    public const Rejected = self::REJECTED;
    public const Cancelled = self::CANCELLED;

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::RELEASED => 'معتمد للإنتاج',
            self::IN_PROGRESS => 'قيد الإنتاج',
            self::AWAITING_QUALITY => 'بانتظار فحص الجودة',
            self::COMPLETED => 'مكتمل',
            self::REJECTED => 'مرفوض من الجودة',
            self::CANCELLED => 'ملغي',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::RELEASED,
            self::IN_PROGRESS,
            self::AWAITING_QUALITY,
        ], true);
    }
}
