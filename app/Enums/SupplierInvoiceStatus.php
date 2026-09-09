<?php

namespace App\Enums;

enum SupplierInvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'غير مدفوعة',
            self::PartiallyPaid => 'مدفوعة جزئياً',
            self::Paid => 'مدفوعة',
            self::Overdue => 'متأخرة',
            self::Cancelled => 'ملغاة',
        };
    }
}
