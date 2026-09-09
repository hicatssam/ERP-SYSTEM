<?php

namespace App\Enums;

enum RecipeStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Approved => 'معتمدة',
            self::Archived => 'مؤرشفة',
        };
    }
}
