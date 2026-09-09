<?php

namespace App\Enums;

enum StockTransferStatus: string
{
    case Draft            = 'draft';
    case Dispatched       = 'dispatched';
    case Received         = 'received';
    case DiscrepancyOpen  = 'discrepancy_open';
    case Resolved         = 'resolved';

    public function label(): string
    {
        return match($this) {
            self::Draft           => 'مسودة',
            self::Dispatched      => 'تم الشحن',
            self::Received        => 'مستلم',
            self::DiscrepancyOpen => 'فروقات مفتوحة',
            self::Resolved        => 'محلول',
        };
    }
}
