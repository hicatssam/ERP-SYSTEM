<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;


class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'name_ar', 'sku', 'barcode',
        'description', 'image', 'unit', 'unit_id', 'product_type',
        'base_selling_price', 'is_active', 'tracks_batch', 'tracks_expiry',
    ];

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'base_selling_price' => 'decimal:2',
            'is_active' => 'boolean',
            'tracks_batch' => 'boolean',
            'tracks_expiry' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unitDefinition(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function locationProducts(): HasMany
    {
        return $this->hasMany(LocationProduct::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_products')
            ->withPivot('is_available', 'local_selling_price', 'minimum_stock_level')
            ->withTimestamps();
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function inventoryBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }


    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class)
            ->orderByDesc('version');
    }

    public function activeRecipe(): HasOne
    {
        return $this->hasOne(
            Recipe::class,
            'product_id'
        )->ofMany(
            [
                'id' => 'max',
            ],
            function (Builder $query) {
                $query
                    ->whereNull('product_variant_id')
                    ->where('status', \App\Enums\RecipeStatus::Approved->value)
                    ->where('is_active', true);
            }
        );
    }

    public function modifierGroupLinks(): HasMany
    {
        return $this->hasMany(ProductModifierGroup::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function recipeIngredientUsages(): HasMany
    {
        return $this->hasMany(
            RecipeItem::class,
            'ingredient_product_id'
        );
    }

    public function productionBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class);
    }

    public function scopeActive($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    public function isVariantProduct(): bool
    {
        return $this->product_type === ProductType::VARIANT;
    }

    public function getEffectivePriceForLocation(int $locationId): string
    {
        $locationProduct = $this->locationProducts()->where('location_id', $locationId)->first();

        return $locationProduct?->local_selling_price ?? $this->base_selling_price;
    }

    public function restaurantMenuItems(): HasMany
{
    return $this->hasMany(
        RestaurantMenuItem::class,
        'product_id'
    );
}
}
