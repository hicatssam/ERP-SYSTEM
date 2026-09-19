<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProofAnalysis extends Model
{
    protected $fillable = [
        'payment_id',
        'status',
        'provider',
        'model',
        'proof_sha256',
        'sender_name',
        'sender_account',
        'recipient_name',
        'recipient_account',
        'transaction_reference',
        'extracted_amount',
        'extracted_currency',
        'transaction_at',
        'confidence',
        'risk_level',
        'risk_signals',
        'raw_text',
        'raw_payload',
        'failure_reason',
        'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'extracted_amount' => 'decimal:2',
            'transaction_at' => 'datetime',
            'confidence' => 'integer',
            'risk_signals' => 'array',
            'raw_payload' => 'array',
            'analyzed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
