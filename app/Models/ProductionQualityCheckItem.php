<?php

namespace App\Models;

use App\Enums\QualityCheckResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionQualityCheckItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_quality_check_id',
        'criterion',
        'result',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'result' => QualityCheckResult::class,
            'sort_order' => 'integer',
        ];
    }

    public function qualityCheck(): BelongsTo
    {
        return $this->belongsTo(
            ProductionQualityCheck::class,
            'production_quality_check_id'
        );
    }
}
