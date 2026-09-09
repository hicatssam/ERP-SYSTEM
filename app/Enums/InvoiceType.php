<?php

namespace App\Enums;

enum InvoiceType: string
{
    case RegularOrder  = 'regular_order';
    case SpecialCake   = 'special_cake';

    public function label(): string
    {
        return match($this) {
            self::RegularOrder => 'طلب عادي',
            self::SpecialCake  => 'طلب كيك خاص',
        };
    }
}
