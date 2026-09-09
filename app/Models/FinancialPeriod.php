<?php

namespace App\Models;

use App\Enums\FinancialPeriodStatus;
use Illuminate\Database\Eloquent\Model;

class FinancialPeriod extends Model
{
    protected $fillable = [
        'name', 'year', 'month', 'start_date', 'end_date', 'status',
        'opened_at', 'opened_by', 'closed_at', 'closed_by',
        'opening_balance', 'closing_balance', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date'      => 'date',
            'end_date'        => 'date',
            'opened_at'       => 'datetime',
            'closed_at'       => 'datetime',
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'status'          => FinancialPeriodStatus::class,
        ];
    }

    public function openedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function summaries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinancialPeriodSummary::class);
    }

    public function adjustments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinancialAdjustment::class);
    }

    public function isOpen(): bool { return $this->status === FinancialPeriodStatus::Open; }
}
