<?php

namespace App\Enums;

enum DiscrepancyStatus: string
{
    case Open     = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match($this) {
            self::Open     => 'مفتوح',
            self::Resolved => 'محلول',
        };
    }
}
