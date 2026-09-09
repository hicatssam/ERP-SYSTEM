<?php

namespace App\Enums;

enum SystemEventStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Processed  = 'processed';
    case Failed     = 'failed';

    public function label(): string
    {
        return match($this) {
            self::Pending    => 'بانتظار المعالجة',
            self::Processing => 'قيد المعالجة',
            self::Processed  => 'تمت المعالجة',
            self::Failed     => 'فشل',
        };
    }
}
