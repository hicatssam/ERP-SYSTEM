<?php

namespace App\Enums;

enum QualityInspectionStatus: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'مقبول',
            self::Rejected => 'مرفوض',
        };
    }
}
