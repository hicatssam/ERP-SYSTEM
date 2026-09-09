<?php

namespace App\Models;

use App\Enums\RecipeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'product_id',
        'product_variant_id',
        'name',
        'version',
        'yield_quantity',
        'labor_cost_per_batch',
        'overhead_percent',
        'status',
        'is_active',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'yield_quantity' => 'decimal:3',
            'labor_cost_per_batch' => 'decimal:4',
            'overhead_percent' => 'decimal:3',
            'status' => RecipeStatus::class,
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function productionBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', RecipeStatus::Approved->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isEditable(): bool
    {
        return $this->status === RecipeStatus::Draft;
    }
}
