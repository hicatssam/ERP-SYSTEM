<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProductPriceHistory extends Model
{
    protected $fillable = [
        'supplier_product_id', 'purchase_price', 'currency_id', 'purchase_unit_id',
        'purchase_unit_snapshot', 'conversion_factor', 'exchange_rate',
        'base_purchase_price', 'reference_type', 'reference_id', 'effective_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:4',
            'conversion_factor' => 'decimal:6',
            'exchange_rate' => 'decimal:8',
            'base_purchase_price' => 'decimal:4',
            'effective_at' => 'datetime',
        ];
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
