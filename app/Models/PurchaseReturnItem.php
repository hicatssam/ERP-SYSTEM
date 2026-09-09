<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    protected $fillable = [
        'purchase_return_id', 'goods_receipt_item_id', 'inventory_batch_id', 'product_id',
        'return_quantity', 'conversion_factor', 'return_base_quantity',
        'unit_cost', 'base_unit_cost', 'line_total', 'base_line_total',
        'reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'return_quantity' => 'decimal:3',
            'conversion_factor' => 'decimal:6',
            'return_base_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'base_unit_cost' => 'decimal:4',
            'line_total' => 'decimal:2',
            'base_line_total' => 'decimal:2',
        ];
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
