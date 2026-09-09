<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PendingVerification = 'pending_verification';
    case Confirmed           = 'confirmed';
    case Rejected            = 'rejected';
    case Corrected           = 'corrected';
    case Refunded            = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::PendingVerification => 'بانتظار التحقق',
            self::Confirmed           => 'مؤكد',
            self::Rejected            => 'مرفوض',
            self::Corrected           => 'مُصحَّح',
            self::Refunded            => 'مُسترجع',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PendingVerification => 'warning',
            self::Confirmed           => 'success',
            self::Rejected            => 'danger',
            self::Corrected           => 'info',
            self::Refunded            => 'secondary',
        };
    }
}
