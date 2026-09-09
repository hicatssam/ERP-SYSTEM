<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modifier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'modifier_group_id', 'code', 'name', 'name_ar', 'price_delta',
        'allow_quantity', 'max_quantity', 'is_default', 'is_active',
        'sort_order', 'configuration',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:3',
            'allow_quantity' => 'boolean',
            'max_quantity' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'configuration' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    public function ingredientAdjustments(): HasMany
    {
        return $this->hasMany(ModifierIngredientAdjustment::class);
    }
}
