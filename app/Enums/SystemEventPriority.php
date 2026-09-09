<?php

namespace App\Enums;

enum SystemEventPriority: string
{
    case Normal    = 'normal';
    case Important = 'important';
    case Urgent    = 'urgent';

    public function label(): string
    {
        return match($this) {
            self::Normal    => 'عادي',
            self::Important => 'مهم',
            self::Urgent    => 'عاجل',
        };
    }
}
