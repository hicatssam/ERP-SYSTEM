<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPayment extends Model
{
    protected $fillable = [
        'customer_id',
        'location_id',
        'payment_method_id',
        'amount',
        'status',
        'reference_number',
        'payment_proof',
        'allocation_mode',
        'allocation_payload',
        'notes',
        'received_by',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'allocation_payload' => 'array',
            'verified_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isPendingVerification(): bool
    {
        return $this->status === 'pending_verification';
    }

    public function allocatedAmount(): float
    {
        $amount = $this->relationLoaded('allocations')
            ? (float) $this->allocations->sum('amount')
            : (float) $this->allocations()->sum('amount');

        return round($amount, 2);
    }

    public function unallocatedAmount(): float
    {
        return round(max(0, (float) $this->amount - $this->allocatedAmount()), 2);
    }
}
