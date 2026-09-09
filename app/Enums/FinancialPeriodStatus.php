<?php

namespace App\Enums;

enum FinancialPeriodStatus: string
{
    case Open   = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match($this) {
            self::Open   => 'مفتوحة',
            self::Closed => 'مغلقة',
        };
    }
}
