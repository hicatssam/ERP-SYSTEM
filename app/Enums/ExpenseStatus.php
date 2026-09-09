<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Posted = 'posted';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Submitted => 'بانتظار الاعتماد',
            self::Approved => 'معتمد',
            self::Rejected => 'مرفوض',
            self::Posted => 'مرحّل',
            self::Void => 'ملغى محاسبيًا',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Posted => 'badge-active',
            self::Approved => 'badge-active',
            self::Submitted => 'badge-pending',
            self::Rejected, self::Void => 'badge-inactive',
            self::Draft => 'badge-pending',
        };
    }
}
