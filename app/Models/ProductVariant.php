<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'size_id', 'color_id', 'name', 'name_ar',
        'sku', 'barcode', 'selling_price', 'image', 'is_default',
        'is_active', 'sort_order', 'configuration',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'configuration' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductAttributeValue::class,
            'product_variant_attribute_values'
        )->with('attribute');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'product_variant_id')
            ->orderByDesc('version');
    }

    public function modifierGroupLinks(): HasMany
    {
        return $this->hasMany(ProductModifierGroup::class, 'product_variant_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function displayName(): string
    {
        if ($this->name_ar || $this->name) {
            return $this->name_ar ?: $this->name;
        }

        $parts = collect([
            $this->size?->displayName(),
            $this->color?->displayName(),
        ])->filter();

        if ($this->relationLoaded('attributeValues')) {
            $parts = $parts->merge(
                $this->attributeValues->map(fn (ProductAttributeValue $value) => $value->displayName())
            );
        }

        return $parts->isNotEmpty()
            ? $parts->implode(' / ')
            : 'متغير #' . $this->id;
    }

    public function effectivePrice(): string
    {
        return $this->selling_price ?? $this->product->base_selling_price;
    }
}
