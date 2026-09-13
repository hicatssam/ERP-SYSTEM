<?php

namespace App\Enums;

enum RestaurantServiceType: string
{
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';
    case Outdoor = 'outdoor';
    case Delivery = 'delivery';
    case Phone = 'phone';
    case Web = 'web';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'داخل المطعم',
            self::Takeaway => 'سفري',
            self::Outdoor => 'استلام من الباب',
            self::Delivery => 'توصيل',
            self::Phone => 'هاتف',
            self::Web => 'ويب',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DineIn => '🍽️',
            self::Takeaway => '🥡',
            self::Outdoor => '🚗',
            self::Delivery => '🛵',
            self::Phone => '☎️',
            self::Web => '🌐',
        };
    }
}
