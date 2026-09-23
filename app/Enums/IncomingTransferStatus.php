<?php

namespace App\Enums;

enum IncomingTransferStatus: string
{
    case PendingVerification = 'pending_verification';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingVerification => 'بانتظار التحقق',
            self::Confirmed => 'مؤكدة',
            self::Rejected => 'مرفوضة',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PendingVerification => 'badge-warning',
            self::Confirmed => 'badge-active',
            self::Rejected => 'badge-inactive',
        };
    }
}
