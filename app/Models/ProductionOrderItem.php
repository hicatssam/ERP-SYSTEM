<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderItem extends Model
{
    protected $fillable = [
        'production_order_id',
        'recipe_item_id',
        'product_id',
        'unit_id',
        'recipe_quantity',
        'waste_percent_snapshot',
        'waste_percent',
        'planned_quantity',
        'reserved_quantity',
        'issued_quantity',
        'actual_quantity',
        'returned_quantity',
        'waste_quantity',
        'unit_cost_snapshot',
        'planned_unit_cost',
        'planned_cost',
        'actual_unit_cost',
        'actual_cost',
        'consumed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recipe_quantity' => 'decimal:4',
            'waste_percent_snapshot' => 'decimal:3',
            'waste_percent' => 'decimal:3',
            'planned_quantity' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
            'issued_quantity' => 'decimal:4',
            'actual_quantity' => 'decimal:4',
            'returned_quantity' => 'decimal:4',
            'waste_quantity' => 'decimal:4',
            'unit_cost_snapshot' => 'decimal:4',
            'planned_unit_cost' => 'decimal:4',
            'planned_cost' => 'decimal:4',
            'actual_unit_cost' => 'decimal:4',
            'actual_cost' => 'decimal:4',
            'consumed_at' => 'datetime',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function recipeItem(): BelongsTo
    {
        return $this->belongsTo(RecipeItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
