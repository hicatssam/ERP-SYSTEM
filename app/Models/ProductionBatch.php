<?php

namespace App\Models;

use App\Enums\ProductionBatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductionBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'recipe_id',
        'product_id',
        'location_id',
        'recipe_version',
        'recipe_snapshot',
        'status',
        'planned_output_quantity',
        'actual_output_quantity',
        'accepted_output_quantity',
        'rejected_output_quantity',
        'standard_material_cost',
        'actual_material_cost',
        'actual_unit_cost',
        'quality_required',
        'output_expiry_date',
        'planned_date',
        'released_at',
        'started_at',
        'materials_issued_at',
        'submitted_for_quality_at',
        'output_posted_at',
        'completed_at',
        'cancelled_at',
        'rejected_at',
        'created_by',
        'released_by',
        'started_by',
        'completed_by',
        'cancelled_by',
        'notes',
        'cancellation_reason',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'recipe_version' => 'integer',
            'recipe_snapshot' => 'array',
            'status' => ProductionBatchStatus::class,
            'planned_output_quantity' => 'decimal:3',
            'actual_output_quantity' => 'decimal:3',
            'accepted_output_quantity' => 'decimal:3',
            'rejected_output_quantity' => 'decimal:3',
            'standard_material_cost' => 'decimal:4',
            'actual_material_cost' => 'decimal:4',
            'actual_unit_cost' => 'decimal:4',
            'quality_required' => 'boolean',
            'output_expiry_date' => 'date',
            'planned_date' => 'date',
            'released_at' => 'datetime',
            'started_at' => 'datetime',
            'materials_issued_at' => 'datetime',
            'submitted_for_quality_at' => 'datetime',
            'output_posted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionBatchItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function qualityCheck(): HasOne
    {
        return $this->hasOne(ProductionQualityCheck::class);
    }

    public function outputInventoryBatch(): HasOne
    {
        return $this->hasOne(InventoryBatch::class, 'production_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('location_id', $locationId);
    }
}
