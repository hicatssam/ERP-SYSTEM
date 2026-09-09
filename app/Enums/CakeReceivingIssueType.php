<?php

namespace App\Enums;

enum CakeReceivingIssueType: string
{
    case Damaged      = 'damaged';
    case WrongDesign  = 'wrong_design';
    case WrongText    = 'wrong_text';
    case WrongSize    = 'wrong_size';
    case Other        = 'other';

    public function label(): string
    {
        return match($this) {
            self::Damaged     => 'تالف',
            self::WrongDesign => 'تصميم خاطئ',
            self::WrongText   => 'نص خاطئ',
            self::WrongSize   => 'حجم خاطئ',
            self::Other       => 'أخرى',
        };
    }
}
