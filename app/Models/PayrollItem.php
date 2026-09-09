<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'compensation_profile_id',
        'base_salary',
        'allowances_total',
        'bonuses_total',
        'deductions_total',
        'gross_salary',
        'net_salary',
        'payable_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:4',
            'allowances_total' => 'decimal:4',
            'bonuses_total' => 'decimal:4',
            'deductions_total' => 'decimal:4',
            'gross_salary' => 'decimal:4',
            'net_salary' => 'decimal:4',
            'payable_amount' => 'decimal:4',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function compensation(): BelongsTo
    {
        return $this->belongsTo(
            EmployeeCompensationProfile::class,
            'compensation_profile_id'
        );
    }

    public function components(): HasMany
    {
        return $this->hasMany(PayrollItemComponent::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }
}
