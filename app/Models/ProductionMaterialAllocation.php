<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionMaterialAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_batch_item_id',
        'inventory_batch_id',
        'quantity_issued',
        'quantity_returned',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity_issued' => 'decimal:3',
            'quantity_returned' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(
            ProductionBatchItem::class,
            'production_batch_item_id'
        );
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function netQuantity(): float
    {
        return round(
            (float) $this->quantity_issued
            - (float) $this->quantity_returned,
            3
        );
    }
}
