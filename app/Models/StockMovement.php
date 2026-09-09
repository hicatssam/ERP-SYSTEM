<?php

namespace App\Models;

use App\Enums\MovementReason;
use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'location_id', 'product_id', 'movement_type', 'reason', 'quantity',
        'balance_before', 'balance_after', 'reference_type', 'reference_id',
        'created_by', 'note', 'created_at', 'currency_id', 'exchange_rate',
        'unit_cost', 'base_unit_cost', 'total_cost', 'base_total_cost',
        'inventory_batch_id', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'balance_before' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'exchange_rate' => 'decimal:8',
            'unit_cost' => 'decimal:4',
            'base_unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
            'base_total_cost' => 'decimal:2',
            'created_at' => 'datetime',
            'movement_type' => MovementType::class,
            'reason' => MovementReason::class,
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }
}
