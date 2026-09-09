<?php

namespace App\Enums;

enum OrderType: string
{
    case Order           = 'order';
    case SpecialCakeOrder = 'special_cake_order';

    public function label(): string
    {
        return match($this) {
            self::Order            => 'طلب عادي',
            self::SpecialCakeOrder => 'طلب كيك خاص',
        };
    }
}
