<?php

namespace App\Services\Inventory;

use App\Models\StockRequest;
use App\Models\SystemSetting;

class StockRequestNumberService
{
    public static function generate(): string
    {
        $prefix = SystemSetting::get('stock_request_prefix', 'SR');
        $year   = now()->format('Y');
        $last   = StockRequest::whereYear('created_at', $year)->max('id') ?? 0;
        return "{$prefix}-{$year}-" . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }
}
