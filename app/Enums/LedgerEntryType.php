<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Sale               = 'sale';
    case SaleCancellation   = 'sale_cancellation';
    case Discount           = 'discount';
    case Refund             = 'refund';
    case PaymentCollection  = 'payment_collection';
    case PaymentReversal    = 'payment_reversal';
    case Adjustment         = 'adjustment';
    case OpeningBalance     = 'opening_balance';
    case ClosingBalance     = 'closing_balance';
    case CarryForward       = 'carry_forward';
    case Expense            = 'expense';
    case ExpenseReversal    = 'expense_reversal';

    public function label(): string
    {
        return match($this) {
            self::Sale              => 'بيع',
            self::SaleCancellation  => 'إلغاء بيع',
            self::Discount          => 'خصم',
            self::Refund            => 'استرجاع',
            self::PaymentCollection => 'تحصيل دفعة',
            self::PaymentReversal   => 'عكس دفعة',
            self::Adjustment        => 'تعديل',
            self::OpeningBalance    => 'رصيد افتتاحي',
            self::ClosingBalance    => 'رصيد ختامي',
            self::CarryForward      => 'ترحيل',
            self::Expense           => 'مصروف',
            self::ExpenseReversal   => 'عكس مصروف',
        };
    }
}
