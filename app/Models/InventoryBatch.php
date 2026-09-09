<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBatch extends Model
{
    protected $fillable = [
        'goods_receipt_item_id',
        'production_batch_id',
        'product_id',
        'location_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'received_quantity',
        'available_quantity',
        'unit_cost',
        'base_unit_cost',
        'currency_id',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
            'received_quantity' => 'decimal:3',
            'available_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'base_unit_cost' => 'decimal:4',
        ];
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
