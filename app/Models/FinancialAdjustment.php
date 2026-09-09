<?php

namespace App\Models;

use App\Enums\AdjustmentType;
use App\Enums\AdjustmentStatus;
use Illuminate\Database\Eloquent\Model;

class FinancialAdjustment extends Model
{
    protected $fillable = [
        'financial_period_id', 'location_id', 'adjustment_type',
        'amount', 'reason', 'status', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'decimal:2',
            'approved_at'     => 'datetime',
            'adjustment_type' => AdjustmentType::class,
            'status'          => AdjustmentStatus::class,
        ];
    }

    public function period(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function location(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
