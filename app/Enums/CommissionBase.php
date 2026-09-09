<?php

namespace App\Enums;

enum CommissionBase: string
{
    case GrossSales = 'gross_sales';
    case NetSales = 'net_sales';

    public function label(): string
    {
        return match ($this) {
            self::GrossSales => 'قبل خصم قناة البيع',
            self::NetSales => 'بعد خصم قناة البيع',
        };
    }
}
