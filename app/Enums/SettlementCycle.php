<?php

namespace App\Enums;

enum SettlementCycle: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'يومي',
            self::Weekly => 'أسبوعي',
            self::Biweekly => 'كل أسبوعين',
            self::Monthly => 'شهري',
            self::Manual => 'يدوي',
        };
    }
}
