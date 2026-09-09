<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModifierGroup extends Model
{
    protected $fillable = [
        'product_id', 'product_variant_id', 'modifier_group_id',
        'is_required_override', 'min_selections_override',
        'max_selections_override', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required_override' => 'boolean',
            'min_selections_override' => 'integer',
            'max_selections_override' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
