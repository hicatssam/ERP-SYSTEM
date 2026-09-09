<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case PaymentPending            = 'payment_pending';
    case PartiallyPaid             = 'partially_paid';
    case Paid                      = 'paid';
    case PendingPaymentVerification = 'pending_payment_verification';
    case PartiallyRefunded         = 'partially_refunded';
    case Refunded                  = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::PaymentPending             => 'في انتظار الدفع',
            self::PartiallyPaid              => 'مدفوع جزئياً',
            self::Paid                       => 'مدفوع',
            self::PendingPaymentVerification => 'بانتظار التحقق',
            self::PartiallyRefunded          => 'مُسترجع جزئياً',
            self::Refunded                   => 'مُسترجع',
        };
    }
}
