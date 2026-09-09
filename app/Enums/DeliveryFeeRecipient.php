<?php

namespace App\Enums;

enum DeliveryFeeRecipient: string
{
    case Restaurant = 'restaurant';
    case Channel = 'channel';

    public function label(): string
    {
        return match ($this) {
            self::Restaurant => 'المطعم',
            self::Channel => 'قناة البيع / التطبيق',
        };
    }
}
