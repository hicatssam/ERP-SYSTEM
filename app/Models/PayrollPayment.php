<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayment extends Model
{
    protected $fillable = [
        'document_number',
        'payroll_item_id',
        'employee_id',
        'financial_period_id',
        'location_id',
        'currency_id',
        'amount',
        'exchange_rate',
        'base_amount',
        'status',
        'payment_method_id',
        'paid_at',
        'reference',
        'payment_proof',
        'notes',
        'created_by',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'exchange_rate' => 'decimal:8',
            'base_amount' => 'decimal:4',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(
            PayrollItem::class,
            'payroll_item_id'
        );
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(
            PaymentMethod::class,
            'payment_method_id'
        );
    }

    public function financialPeriod(): BelongsTo
    {
        return $this->belongsTo(
            FinancialPeriod::class,
            'financial_period_id'
        );
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'verified_by'
        );
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'voided_by'
        );
    }
}
