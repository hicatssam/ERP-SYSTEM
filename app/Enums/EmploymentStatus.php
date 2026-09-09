<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Active     = 'active';
    case Inactive   = 'inactive';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match($this) {
            self::Active     => 'نشط',
            self::Inactive   => 'غير نشط',
            self::Terminated => 'منتهي الخدمة',
        };
    }
}
