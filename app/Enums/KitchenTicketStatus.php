<?php

namespace App\Enums;

enum KitchenTicketStatus: string
{
    case QUEUED = 'queued';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case SERVED = 'served';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'بانتظار التحضير',
            self::PREPARING => 'قيد التحضير',
            self::READY => 'جاهز للتسليم',
            self::SERVED => 'تم التسليم',
            self::CANCELLED => 'ملغي',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::QUEUED => 'badge-pending',
            self::PREPARING => 'badge-primary',
            self::READY => 'badge-active',
            self::SERVED => 'badge-grey',
            self::CANCELLED => 'badge-inactive',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::SERVED,
            self::CANCELLED,
        ], true);
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }
}
