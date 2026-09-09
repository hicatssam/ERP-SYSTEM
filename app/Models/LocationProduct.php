<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationProduct extends Model
{
    protected $fillable = ['location_id', 'product_id', 'is_available', 'local_selling_price', 'minimum_stock_level'];

    protected function casts(): array
    {
        return [
            'is_available'        => 'boolean',
            'local_selling_price' => 'decimal:2',
            'minimum_stock_level' => 'decimal:3',
        ];
    }

    public function location(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Inventory::class, 'product_id', 'product_id')
                    ->where('location_id', $this->location_id);
    }

    public function getEffectivePrice(): string
    {
        return $this->local_selling_price ?? $this->product->base_selling_price;
    }
}
