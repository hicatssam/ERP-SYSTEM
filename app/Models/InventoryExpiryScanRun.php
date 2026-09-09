<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryExpiryScanRun extends Model
{
    protected $fillable = [
        'started_at',
        'completed_at',
        'status',
        'scanned_batches',
        'qualifying_batches',
        'recipient_count',
        'database_sent',
        'email_sent',
        'whatsapp_sent',
        'failed_count',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'summary' => 'array',
        ];
    }
}
