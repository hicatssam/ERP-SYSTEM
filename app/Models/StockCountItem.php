<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCountItem extends Model
{
    protected $fillable = [
        'stock_count_id', 'product_id',
        'system_quantity', 'actual_quantity', 'note',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:3',
            'actual_quantity' => 'decimal:3',
        ];
    }

    public function stockCount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getVarianceAttribute(): ?string
    {
        if ($this->actual_quantity === null) return null;
        return bcsub((string)$this->actual_quantity, (string)$this->system_quantity, 3);
    }
}
