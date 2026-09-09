<?php

namespace App\Enums;

enum ModifierSelectionType: string
{
    case Single = 'single';
    case Multiple = 'multiple';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'اختيار واحد',
            self::Multiple => 'اختيارات متعددة',
        };
    }
}
