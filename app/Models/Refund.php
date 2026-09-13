<?php

namespace App\Models;

use App\Services\Payments\OrderPaymentStatusSynchronizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'payment_id', 'order_type', 'order_id',
        'payment_method_id', 'amount', 'reason',
        'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Refund $refund): void {
            $payment = $refund->payment()->first();

            if ($payment) {
                app(OrderPaymentStatusSynchronizer::class)->syncFromPayment($payment);
            }
        });

        static::deleted(function (Refund $refund): void {
            $payment = $refund->payment()->first();

            if ($payment) {
                app(OrderPaymentStatusSynchronizer::class)->syncFromPayment($payment);
            }
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
