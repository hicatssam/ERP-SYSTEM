<?php

namespace App\Enums;

enum DiscrepancyType: string
{
    case Shortage = 'shortage';
    case Damage   = 'damage';
    case Overage  = 'overage';
    case Other    = 'other';

    public function label(): string
    {
        return match($this) {
            self::Shortage => 'عجز',
            self::Damage   => 'تلف',
            self::Overage  => 'زيادة',
            self::Other    => 'أخرى',
        };
    }
}
