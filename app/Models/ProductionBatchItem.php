<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_batch_id',
        'recipe_item_id',
        'ingredient_product_id',
        'ingredient_name_snapshot',
        'unit_snapshot',
        'stage_snapshot',
        'planned_quantity',
        'issued_quantity',
        'actual_consumed_quantity',
        'waste_quantity',
        'unit_cost_snapshot',
        'actual_cost',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:3',
            'issued_quantity' => 'decimal:3',
            'actual_consumed_quantity' => 'decimal:3',
            'waste_quantity' => 'decimal:3',
            'unit_cost_snapshot' => 'decimal:4',
            'actual_cost' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function recipeItem(): BelongsTo
    {
        return $this->belongsTo(RecipeItem::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ProductionMaterialAllocation::class);
    }
}
