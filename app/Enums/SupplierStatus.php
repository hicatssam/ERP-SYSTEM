<?php

namespace App\Enums;

enum SupplierStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشط',
            self::Inactive => 'غير نشط',
            self::Blocked => 'موقوف',
        };
    }

    public function canTransact(): bool
    {
        return $this === self::Active;
    }
}
