<?php

namespace App\Enums;

enum LoyaltyTransactionType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Earn => 'اكتساب نقاط',
            self::Redeem => 'استبدال نقاط',
            self::Adjustment => 'تعديل يدوي',
            self::Reversal => 'عكس نقاط',
        };
    }
}
