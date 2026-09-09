<?php

namespace App\Enums;

enum LocationType: string
{
    case Branch  = 'branch';
    case Factory = 'factory';

    public function label(): string
    {
        return match($this) {
            self::Branch  => 'فرع',
            self::Factory => 'مصنع',
        };
    }
}
