<?php

namespace App\Enums;

enum ProductType: string
{
    case STANDARD = 'standard';
    case VARIANT = 'variant';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'منتج عادي',
            self::VARIANT => 'منتج بمتغيرات',
        };
    }
}
