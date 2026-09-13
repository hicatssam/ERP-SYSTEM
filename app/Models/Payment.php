<?php

namespace App\Models;

use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Jobs\AnalyzePaymentProof;
use App\Services\Invoices\InvoiceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = [
        'order_type',
        'order_id',
        'payment_method_id',
        'location_payment_account_id',
        'location_id',
        'amount',
        'status',
        'reference_number',
        'payment_proof',
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
            'verified_at' => 'datetime',
            'paid_at' => 'datetime',
            'status' => PaymentStatus::class,
            'order_type' => OrderType::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Payment $payment): void {
            app(InvoiceService::class)->syncFromPayment($payment);

            if (
                config('services.payment_proof_ai.enabled')
                && config('services.payment_proof_ai.auto_analyze')
                && filled($payment->payment_proof)
                && $payment->isPendingVerification()
                && ($payment->wasRecentlyCreated || $payment->wasChanged('payment_proof'))
            ) {
                AnalyzePaymentProof::dispatchAfterResponse((int) $payment->id);
            }
        });

        static::deleted(function (Payment $payment): void {
            app(InvoiceService::class)->syncFromPayment($payment);
        });
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function locationPaymentAccount(): BelongsTo
    {
        return $this->belongsTo(LocationPaymentAccount::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(PaymentCorrection::class, 'original_payment_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function proofAnalyses(): HasMany
    {
        return $this->hasMany(PaymentProofAnalysis::class);
    }

    public function latestProofAnalysis(): HasOne
    {
        return $this->hasOne(PaymentProofAnalysis::class)->latestOfMany();
    }

    public function isConfirmed(): bool
    {
        return $this->status === PaymentStatus::Confirmed;
    }

    public function isPendingVerification(): bool
    {
        return $this->status === PaymentStatus::PendingVerification;
    }

    public function statusValue(): string
    {
        return $this->status instanceof \BackedEnum ? (string) $this->status->value : (string) $this->status;
    }

    public function orderTypeValue(): string
    {
        return $this->order_type instanceof \BackedEnum ? (string) $this->order_type->value : (string) $this->order_type;
    }
}
