<?php

namespace App\Enums;

enum StockCountStatus: string
{
    case Draft      = 'draft';
    case InProgress = 'in_progress';
    case Approved   = 'approved';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft      => 'مسودة',
            self::InProgress => 'قيد التنفيذ',
            self::Approved   => 'معتمد',
            self::Cancelled  => 'ملغي',
        };
    }
}
