<?php

namespace App\Enums;

enum ModuleType: string
{
    case CORE = 'core';
    case INDUSTRY = 'industry';
    case OPTIONAL = 'optional';

    public function label(): string
    {
        return match ($this) {
            self::CORE => 'أساسي',
            self::INDUSTRY => 'قطاعي',
            self::OPTIONAL => 'اختياري',
        };
    }
}
