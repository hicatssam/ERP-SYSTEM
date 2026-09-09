<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft     = 'draft';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft     => 'مسودة',
            self::Confirmed => 'مؤكد',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغي',
        };
    }

    public function badgeClass(): string
{
    return match($this) {
        self::Draft     => 'badge-pending',
        self::Confirmed => 'badge-primary',
        self::Completed => 'badge-active',
        self::Cancelled => 'badge-inactive',
    };
}

    public function color(): string
    {
        return match($this) {
            self::Draft     => 'secondary',
            self::Confirmed => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
