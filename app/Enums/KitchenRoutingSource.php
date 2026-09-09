<?php

namespace App\Enums;

enum KitchenRoutingSource: string
{
    case PRODUCT = 'product';
    case CATEGORY = 'category';
    case DEFAULT_STATION = 'default_station';
    case FALLBACK_STATION = 'fallback_station';

    public function label(): string
    {
        return match ($this) {
            self::PRODUCT => 'توجيه خاص بالمنتج',
            self::CATEGORY => 'توجيه حسب الفئة',
            self::DEFAULT_STATION => 'المحطة الافتراضية',
            self::FALLBACK_STATION => 'أول محطة متاحة',
        };
    }
}
