<?php

namespace App\Models;

use App\Enums\DiscrepancyType;
use App\Enums\DiscrepancyStatus;
use Illuminate\Database\Eloquent\Model;

class TransferDiscrepancy extends Model
{
    protected $fillable = [
        'stock_transfer_id', 'product_id', 'sent_quantity', 'received_quantity',
        'variance', 'discrepancy_type', 'notes', 'status',
        'resolved_by', 'resolved_at', 'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'sent_quantity'     => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'variance'          => 'decimal:3',
            'resolved_at'       => 'datetime',
            'discrepancy_type'  => DiscrepancyType::class,
            'status'            => DiscrepancyStatus::class,
        ];
    }

    public function stockTransfer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function resolvedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
