<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Active    = 'active';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Active    => 'نشطة',
            self::Cancelled => 'ملغاة',
        };
    }
}
