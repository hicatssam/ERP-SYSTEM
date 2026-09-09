<?php

namespace App\Models;

use App\Enums\ProductionOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    protected $fillable = [
        'production_number',
        'location_id',
        'recipe_id',
        'product_id',
        'output_unit_id',
        'recipe_version',
        'status',
        'quality_required',
        'recipe_yield_quantity',
        'planned_output_quantity',
        'actual_output_quantity',
        'output_variance_quantity',
        'output_variance_percent',
        'estimated_material_cost',
        'planned_material_cost',
        'planned_labor_cost',
        'planned_overhead_cost',
        'planned_total_cost',
        'planned_unit_cost',
        'actual_material_cost',
        'actual_labor_cost',
        'actual_overhead_cost',
        'actual_total_cost',
        'actual_unit_cost',
        'labor_cost',
        'overhead_percent_snapshot',
        'overhead_cost',
        'total_cost',
        'unit_cost',
        'cost_is_complete',
        'planned_at',
        'released_at',
        'started_at',
        'materials_consumed_at',
        'submitted_quality_at',
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
            'status' => ProductionOrderStatus::class,
            'quality_required' => 'boolean',
            'recipe_yield_quantity' => 'decimal:4',
            'planned_output_quantity' => 'decimal:4',
            'actual_output_quantity' => 'decimal:4',
            'output_variance_quantity' => 'decimal:4',
            'output_variance_percent' => 'decimal:4',
            'estimated_material_cost' => 'decimal:4',
            'planned_material_cost' => 'decimal:4',
            'planned_labor_cost' => 'decimal:4',
            'planned_overhead_cost' => 'decimal:4',
            'planned_total_cost' => 'decimal:4',
            'planned_unit_cost' => 'decimal:6',
            'actual_material_cost' => 'decimal:4',
            'actual_labor_cost' => 'decimal:4',
            'actual_overhead_cost' => 'decimal:4',
            'actual_total_cost' => 'decimal:4',
            'actual_unit_cost' => 'decimal:6',
            'labor_cost' => 'decimal:4',
            'overhead_percent_snapshot' => 'decimal:3',
            'overhead_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'cost_is_complete' => 'boolean',
            'planned_at' => 'datetime',
            'released_at' => 'datetime',
            'started_at' => 'datetime',
            'materials_consumed_at' => 'datetime',
            'submitted_quality_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function outputUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'output_unit_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class)
            ->orderBy('id');
    }

    public function qualityInspection(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductionQualityInspection::class);
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

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ProductionOrderStatus::DRAFT->value,
            ProductionOrderStatus::RELEASED->value,
            ProductionOrderStatus::IN_PROGRESS->value,
        ]);
    }

    public function statusValue(): string
    {
        return $this->status instanceof ProductionOrderStatus
            ? $this->status->value
            : (string) $this->status;
    }
}
