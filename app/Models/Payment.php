<?php

namespace App\Models;

use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Jobs\AnalyzePaymentProof;
use App\Services\Finance\FinancialPostingService;
use App\Services\Invoices\InvoiceService;
use App\Services\Payments\OrderPaymentStatusSynchronizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

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
        static::creating(function (Payment $payment): void {
            $locationId = (int) ($payment->location_id ?? 0);
            $methodId = (int) ($payment->payment_method_id ?? 0);

            if ($locationId <= 0 || $methodId <= 0) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'بيانات الفرع وطريقة الدفع مطلوبة للحركة المالية.',
                ]);
            }

            $method = PaymentMethod::query()
                ->active()
                ->find($methodId);

            if (! $method || ! $method->isAvailableAt($locationId)) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'طريقة الدفع المحددة غير متاحة في هذا الفرع.',
                ]);
            }

            $activeAccounts = LocationPaymentAccount::query()
                ->where('is_active', true)
                ->where(function ($query) use ($locationId, $methodId): void {
                    $query->whereHas('locationPaymentMethod', function ($parent) use ($locationId, $methodId): void {
                        $parent
                            ->where('location_id', $locationId)
                            ->where('payment_method_id', $methodId)
                            ->where('is_active', true);
                    })->orWhere(function ($legacy) use ($locationId, $methodId): void {
                        $legacy
                            ->whereNull('location_payment_method_id')
                            ->where('location_id', $locationId)
                            ->where('payment_method_id', $methodId);
                    });
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            // POS/admin legacy screens do not yet expose an account selector.
            // When there is exactly one valid account, bind it automatically so
            // AI verification and payment reporting still know the destination.
            if (
                ! $payment->location_payment_account_id
                && (string) $method->type !== 'cash'
                && $activeAccounts->count() === 1
            ) {
                $payment->location_payment_account_id = $activeAccounts->first()->id;
            }

            if ($payment->location_payment_account_id) {
                $account = $activeAccounts->firstWhere(
                    'id',
                    (int) $payment->location_payment_account_id
                );

                if (! $account) {
                    throw ValidationException::withMessages([
                        'location_payment_account_id' => 'حساب الدفع المحدد لا يتبع طريقة الدفع والفرع المختارين.',
                    ]);
                }
            }
        });

        static::saved(function (Payment $payment): void {
            app(InvoiceService::class)->syncFromPayment($payment);
            app(OrderPaymentStatusSynchronizer::class)->syncFromPayment($payment);

            if (
                in_array($payment->statusValue(), ['confirmed', 'corrected'], true)
                && ($payment->verified_by || $payment->received_by)
                && ($payment->wasRecentlyCreated || $payment->wasChanged(['status', 'amount']))
            ) {
                $actorId = (int) ($payment->verified_by ?: $payment->received_by);
                $actor = User::query()->find($actorId);

                if ($actor) {
                    app(FinancialPostingService::class)->collection($payment, $actor);
                }
            }

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
            app(OrderPaymentStatusSynchronizer::class)->syncFromPayment($payment);
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
