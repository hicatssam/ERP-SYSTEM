<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvanceRepayment extends Model
{
    protected $fillable = [
        'document_number',
        'employee_advance_id',
        'employee_id',
        'amount',
        'currency_id',
        'payment_method_id',
        'status',
        'paid_at',
        'reference',
        'payment_proof',
        'notes',
        'created_by',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(
            EmployeeAdvance::class,
            'employee_advance_id'
        );
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
