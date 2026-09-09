<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierProduct extends Model
{
    protected $fillable = [
        'supplier_id', 'product_id', 'supplier_sku', 'supplier_product_name',
        'purchase_price', 'currency_id', 'minimum_order_quantity',
        'purchase_unit_id', 'conversion_factor', 'package_description',
        'lead_time_days', 'is_preferred', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:4',
            'minimum_order_quantity' => 'decimal:3',
            'conversion_factor' => 'decimal:6',
            'lead_time_days' => 'integer',
            'is_preferred' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(SupplierProductPriceHistory::class)->latest('effective_at');
    }
}
