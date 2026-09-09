<?php

namespace App\Models;

use App\Enums\ProductionQualityStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionQualityCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_batch_id',
        'status',
        'accepted_quantity',
        'rejected_quantity',
        'notes',
        'checked_by',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductionQualityStatus::class,
            'accepted_quantity' => 'decimal:3',
            'rejected_quantity' => 'decimal:3',
            'checked_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            ProductionBatch::class,
            'production_batch_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionQualityCheckItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
