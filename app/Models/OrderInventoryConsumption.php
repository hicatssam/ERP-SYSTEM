<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderInventoryConsumption extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'location_id',
        'sold_product_id',
        'stock_product_id',
        'recipe_id',
        'recipe_item_id',
        'source',
        'inventory_mode',
        'order_quantity',
        'recipe_yield_quantity',
        'quantity_per_yield',
        'waste_percent',
        'consumed_quantity',
        'unit_cost_snapshot',
        'total_cost_snapshot',
        'revision',
        'restored_at',
    ];

    protected function casts(): array
    {
        return [
            'order_quantity' => 'decimal:3',
            'recipe_yield_quantity' => 'decimal:4',
            'quantity_per_yield' => 'decimal:4',
            'waste_percent' => 'decimal:3',
            'consumed_quantity' => 'decimal:3',
            'unit_cost_snapshot' => 'decimal:4',
            'total_cost_snapshot' => 'decimal:4',
            'revision' => 'integer',
            'restored_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function soldProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'sold_product_id');
    }

    public function stockProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'stock_product_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function recipeItem(): BelongsTo
    {
        return $this->belongsTo(RecipeItem::class);
    }
}
