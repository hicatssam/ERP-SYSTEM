<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryExpiryAlertDelivery extends Model
{
    protected $fillable = [
        'scan_run_id',
        'inventory_batch_id',
        'product_id',
        'location_id',
        'recipient_user_id',
        'expiry_date',
        'threshold_days',
        'channel',
        'days_left',
        'severity',
        'status',
        'attempted_at',
        'sent_at',
        'failure_reason',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'days_left' => 'integer',
            'threshold_days' => 'integer',
            'expiry_date' => 'date',
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
