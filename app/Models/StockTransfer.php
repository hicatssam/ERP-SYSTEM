<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    protected $fillable = [
        'transfer_number', 'stock_request_id', 'from_location_id', 'to_location_id',
        'status', 'dispatch_notes', 'receiving_notes',
        'dispatched_by', 'dispatched_at', 'received_by', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'received_at'   => 'datetime',
            'status'        => StockTransferStatus::class,
        ];
    }

    public function stockRequest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function fromLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function dispatcher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    /** Alias used by the export mapper (formatRowForExport). */
    public function dispatchedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function discrepancies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TransferDiscrepancy::class);
    }
}
