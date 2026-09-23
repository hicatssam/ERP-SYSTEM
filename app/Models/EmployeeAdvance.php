<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAdvance extends Model
{
    protected $fillable = [
        'employee_id',
        'amount',
        'recovered_amount',
        'outstanding_amount',
        'issued_at',
        'status',
        'payment_method_id',
        'reference',
        'notes',
        'created_by',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'recovered_amount' => 'decimal:4',
            'outstanding_amount' => 'decimal:4',
            'issued_at' => 'date',
            'settled_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(
            EmployeeAdvanceRepayment::class,
            'employee_advance_id'
        )->latest('paid_at')->latest('id');
    }

}
