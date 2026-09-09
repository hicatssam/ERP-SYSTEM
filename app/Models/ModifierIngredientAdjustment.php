<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierIngredientAdjustment extends Model
{
    protected $fillable = [
        'modifier_id', 'ingredient_product_id', 'unit_id',
        'quantity_delta', 'conversion_to_stock_unit', 'unit_snapshot', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:6',
            'conversion_to_stock_unit' => 'decimal:6',
        ];
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }

    public function ingredientProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockQuantityDelta(): float
    {
        return round(
            (float) $this->quantity_delta * (float) $this->conversion_to_stock_unit,
            6
        );
    }
}
