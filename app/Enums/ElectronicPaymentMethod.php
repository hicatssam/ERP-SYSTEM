<?php

namespace App\Enums;

enum ElectronicPaymentMethod: string
{
    case Cash            = 'cash';
    case PalPay          = 'pal_pay';
    case BankOfPalestine = 'bank_of_palestine';
    case JawwalPay       = 'jawwal_pay';

    public function label(): string
    {
        return match($this) {
            self::Cash            => 'نقداً',
            self::PalPay          => 'Pal Pay',
            self::BankOfPalestine => 'Bank of Palestine',
            self::JawwalPay       => 'Jawwal Pay',
        };
    }

    public function isElectronic(): bool
    {
        return $this !== self::Cash;
    }
}
