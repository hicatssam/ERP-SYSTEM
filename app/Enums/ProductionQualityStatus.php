<?php

namespace App\Enums;

enum ProductionQualityStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Partial = 'partial';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الفحص',
            self::Approved => 'مقبول',
            self::Partial => 'قبول جزئي',
            self::Rejected => 'مرفوض',
        };
    }
}
