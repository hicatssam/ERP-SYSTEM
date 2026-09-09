<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_id', 'supplier_product_id', 'purchase_unit_id',
        'supplier_sku_snapshot', 'purchase_unit_snapshot', 'conversion_factor',
        'description', 'ordered_quantity', 'ordered_base_quantity',
        'received_quantity', 'unit_price', 'discount_amount', 'tax_amount',
        'line_total', 'base_unit_cost', 'base_line_total', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:3',
            'ordered_base_quantity' => 'decimal:3',
            'conversion_factor' => 'decimal:6',
            'received_quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'base_unit_cost' => 'decimal:4',
            'base_line_total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function remainingQuantity(): float
    {
        return max(0, (float) $this->ordered_quantity - (float) $this->received_quantity);
    }
}
