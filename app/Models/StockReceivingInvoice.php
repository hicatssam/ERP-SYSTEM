<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReceivingInvoice extends Model
{
    protected $fillable = [
        'invoice_number', 'stock_transfer_id', 'received_by',
        'receiving_location_id', 'sending_location_id',
        'total_items_ordered', 'total_items_received', 'total_items_damaged',
        'notes', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public static function generateNumber(): string
    {
        $year  = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;
        return 'STR-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    public function stockTransfer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function receivedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function receivingLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class, 'receiving_location_id');
    }

    public function sendingLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class, 'sending_location_id');
    }
}
