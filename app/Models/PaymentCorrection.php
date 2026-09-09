<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCorrection extends Model
{
    protected $fillable = [
        'original_payment_id', 'original_amount', 'corrected_amount',
        'reason', 'corrected_by',
    ];

    protected function casts(): array
    {
        return [
            'original_amount'  => 'decimal:2',
            'corrected_amount' => 'decimal:2',
        ];
    }

    public function originalPayment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Payment::class, 'original_payment_id');
    }

    public function correctedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
