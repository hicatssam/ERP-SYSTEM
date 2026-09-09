<?php

namespace App\Enums;

enum QualityCheckResult: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case NotApplicable = 'na';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'ناجح',
            self::Fail => 'غير ناجح',
            self::NotApplicable => 'غير منطبق',
        };
    }
}
