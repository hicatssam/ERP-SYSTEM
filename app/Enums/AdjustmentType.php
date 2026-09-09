<?php

namespace App\Enums;

enum AdjustmentType: string
{
    case Increase                = 'increase';
    case Decrease                = 'decrease';
    case Correction              = 'correction';
    case CarryForwardAdjustment  = 'carry_forward_adjustment';

    public function label(): string
    {
        return match($this) {
            self::Increase               => 'زيادة',
            self::Decrease               => 'نقص',
            self::Correction             => 'تصحيح',
            self::CarryForwardAdjustment => 'تعديل ترحيل',
        };
    }
}
