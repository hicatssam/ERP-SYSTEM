<?php

namespace App\Models;

use App\Enums\IncomingTransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomingBankTransfer extends Model
{
    protected $fillable = [
        'location_id',
        'payment_method_id',
        'location_payment_account_id',
        'sender_name',
        'sender_phone',
        'sender_account_number',
        'reference_number',
        'amount',
        'currency_code',
        'status',
        'payment_proof',
        'notes',
        'received_at',
        'created_by',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => IncomingTransferStatus::class,
            'received_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function locationPaymentAccount(): BelongsTo
    {
        return $this->belongsTo(LocationPaymentAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function statusValue(): string
    {
        return $this->status instanceof \BackedEnum
            ? (string) $this->status->value
            : (string) $this->status;
    }
}
