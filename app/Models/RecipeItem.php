<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'ingredient_product_id',
        'quantity',
        'expected_waste_percent',
        'unit_snapshot',
        'stage',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'expected_waste_percent' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }

    public function grossQuantity(float $factor = 1.0): float
    {
        $base = (float) $this->quantity * $factor;
        $waste = $base * ((float) $this->expected_waste_percent / 100);

        return round($base + $waste, 3);
    }

    public function effectiveQuantity(float $batchFactor = 1.0): float
{
    $batchFactor = max(0.0, $batchFactor);

    $netQuantity =
        max(0.0, (float) $this->quantity)
        * $batchFactor;

    $wastePercent = min(
        max(
            (float) ($this->waste_percent ?? 0),
            0.0
        ),
        99.9999
    );

    if ($wastePercent <= 0) {
        return round($netQuantity, 6);
    }

    $wasteRate = $wastePercent / 100;

    return round(
        $netQuantity / (1 - $wasteRate),
        6
    );
}
}
