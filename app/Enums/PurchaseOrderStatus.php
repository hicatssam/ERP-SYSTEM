<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Submitted => 'بانتظار الاعتماد',
            self::Approved => 'معتمد',
            self::PartiallyReceived => 'مستلم جزئياً',
            self::Received => 'مستلم بالكامل',
            self::Cancelled => 'ملغى',
        };
    }

    public function acceptsReceipts(): bool
    {
        return in_array($this, [self::Approved, self::PartiallyReceived], true);
    }
}
