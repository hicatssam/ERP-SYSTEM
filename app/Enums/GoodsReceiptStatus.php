<?php

namespace App\Enums;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Posted => 'مُرحّل إلى المخزون',
            self::Cancelled => 'ملغى',
        };
    }
}
