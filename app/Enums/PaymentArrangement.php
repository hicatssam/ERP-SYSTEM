<?php

namespace App\Enums;

enum PaymentArrangement: string
{
    case PayNow = 'pay_now';
    case Deposit = 'deposit';
    case PartialPayment = 'partial_payment';
    case PayOnPickup = 'pay_on_pickup';
    case PendingVerification = 'pending_verification';
    case OnAccount = 'on_account';

    public function label(): string
    {
        return match ($this) {
            self::PayNow => 'دفع فوري',
            self::Deposit => 'عربون',
            self::PartialPayment => 'دفع جزئي',
            self::PayOnPickup => 'الدفع عند الاستلام',
            self::PendingVerification => 'بانتظار التحقق',
            self::OnAccount => 'على الحساب',
        };
    }
}
