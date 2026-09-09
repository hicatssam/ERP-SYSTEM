<?php

namespace App\Models;

use App\Enums\QualityInspectionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionQualityInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'status',
        'measurements',
        'notes',
        'rejection_reason',
        'inspected_by',
        'inspected_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QualityInspectionStatus::class,
            'measurements' => 'array',
            'inspected_at' => 'datetime',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
