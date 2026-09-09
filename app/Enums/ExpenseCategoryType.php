<?php

namespace App\Enums;

enum ExpenseCategoryType: string
{
    case Operating = 'operating';
    case Administrative = 'administrative';
    case Selling = 'selling';
    case Payroll = 'payroll';
    case Occupancy = 'occupancy';
    case Utilities = 'utilities';
    case Maintenance = 'maintenance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Operating => 'تشغيلية',
            self::Administrative => 'إدارية',
            self::Selling => 'بيع وتسويق',
            self::Payroll => 'رواتب وأجور',
            self::Occupancy => 'إيجارات وإشغال',
            self::Utilities => 'مرافق وخدمات',
            self::Maintenance => 'صيانة',
            self::Other => 'أخرى',
        };
    }
}
