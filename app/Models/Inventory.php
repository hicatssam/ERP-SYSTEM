<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * quantity is the usable on-hand quantity for this product and location.
 * Reserved and damaged quantities are held separately so availability never
 * has to be inferred in a Blade template.
 */
class Inventory extends Model
{
    protected $fillable = [
        'location_id', 'product_id', 'quantity', 'reserved_quantity',
        'damaged_quantity', 'in_transit_quantity', 'unit_cost', 'last_movement_at',
    ];

    protected $appends = ['available_quantity', 'inventory_value'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'damaged_quantity' => 'decimal:3',
            'in_transit_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'last_movement_at' => 'datetime',
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

    public function locationProduct(): BelongsTo
    {
        return $this->belongsTo(LocationProduct::class, 'product_id', 'product_id')
            ->where('location_id', $this->location_id);
    }

    public function getAvailableQuantityAttribute(): string
    {
        return number_format(max(0, (float) $this->quantity - (float) $this->reserved_quantity), 3, '.', '');
    }

    public function getInventoryValueAttribute(): string
    {
        return number_format((float) $this->quantity * (float) $this->unit_cost, 2, '.', '');
    }

    public function isLowStock(): bool
    {
        $locationProduct = LocationProduct::query()
            ->where('location_id', $this->location_id)
            ->where('product_id', $this->product_id)
            ->first();

        return $locationProduct
            && (float) $this->available_quantity <= (float) $locationProduct->minimum_stock_level;
    }
}
