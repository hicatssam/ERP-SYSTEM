<?php

namespace App\Enums;

enum ProductionBatchStatus: string
{
    case Draft = 'draft';
    case Released = 'released';
    case InProgress = 'in_progress';
    case AwaitingQuality = 'awaiting_quality';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Released => 'مفرج عنها للإنتاج',
            self::InProgress => 'قيد الإنتاج',
            self::AwaitingQuality => 'بانتظار فحص الجودة',
            self::Completed => 'مكتملة',
            self::Rejected => 'مرفوضة بالجودة',
            self::Cancelled => 'ملغاة',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Rejected,
            self::Cancelled,
        ], true);
    }
}
