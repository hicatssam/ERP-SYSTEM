<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id', 'purchase_order_item_id', 'product_id',
        'purchase_unit_id', 'purchase_unit_snapshot', 'conversion_factor',
        'ordered_quantity', 'received_quantity', 'accepted_quantity',
        'rejected_quantity', 'received_base_quantity', 'accepted_base_quantity',
        'rejected_base_quantity', 'unit_cost', 'base_unit_cost', 'line_total',
        'base_line_total', 'batch_number', 'manufacturing_date', 'expiry_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'received_base_quantity' => 'decimal:3',
            'accepted_quantity' => 'decimal:3',
            'accepted_base_quantity' => 'decimal:3',
            'rejected_quantity' => 'decimal:3',
            'rejected_base_quantity' => 'decimal:3',
            'conversion_factor' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'base_unit_cost' => 'decimal:4',
            'line_total' => 'decimal:2',
            'base_line_total' => 'decimal:2',
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function batch(): HasOne
    {
        return $this->hasOne(InventoryBatch::class, 'goods_receipt_item_id');
    }

    public function purchaseReturnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
