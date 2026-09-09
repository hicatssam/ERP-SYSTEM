<?php

namespace App\Services\Inventory;

use App\Models\StockTransfer;

class StockTransferNumberService
{
    public static function generate(): string
    {
        $year = now()->format('Y');
        $last = StockTransfer::whereYear('created_at', $year)->max('id') ?? 0;
        return "TRF-{$year}-" . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }
}
