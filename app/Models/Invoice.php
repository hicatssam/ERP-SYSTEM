<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\OrderType;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'invoice_type',
        'order_type',
        'order_id',
        'location_id',
        'customer_id',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'issued_by',
        'issued_at',
        'due_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => InvoiceStatus::class,
            'invoice_type' => InvoiceType::class,
            'order_type' => OrderType::class,
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }


    public function customerPaymentAllocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    public function order(): BelongsTo
    {
        if ($this->orderTypeValue() === OrderType::Order->value) {
            return $this->belongsTo(Order::class, 'order_id');
        }

        return $this->belongsTo(SpecialCakeOrder::class, 'order_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Active->value);
    }

    public function isActive(): bool
    {
        return $this->statusValue() === InvoiceStatus::Active->value;
    }

    public function statusValue(): string
    {
        return $this->enumValue($this->status);
    }

    public function statusLabel(): string
    {
        return match ($this->statusValue()) {
            'active' => 'نشطة',
            'cancelled' => 'ملغاة',
            default => $this->statusValue(),
        };
    }

    public function statusBadgeClass(): string
    {
        return $this->isActive() ? 'badge-active' : 'badge-inactive';
    }

    public function paymentStatusValue(): string
    {
        $paid = $this->money($this->paid_amount);
        $remaining = $this->money($this->remaining_amount);
        $total = $this->money($this->total_amount);

        if (bccomp($paid, '0.00', 2) <= 0) {
            return 'unpaid';
        }

        if (
            bccomp($remaining, '0.00', 2) <= 0
            || bccomp($paid, $total, 2) >= 0
        ) {
            return 'paid';
        }

        return 'partially_paid';
    }

    public function paymentStatusLabel(): string
    {
        return match ($this->paymentStatusValue()) {
            'paid' => 'مدفوعة',
            'partially_paid' => 'مدفوعة جزئيًا',
            default => 'غير مدفوعة',
        };
    }

    public function paymentStatusBadgeClass(): string
    {
        return match ($this->paymentStatusValue()) {
            'paid' => 'badge-active',
            'partially_paid' => 'badge-pending',
            default => 'badge-inactive',
        };
    }

    public function invoiceTypeValue(): string
    {
        return $this->enumValue($this->invoice_type);
    }

    public function orderTypeValue(): string
    {
        return $this->enumValue($this->order_type);
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    private function money(mixed $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, '.', '');
    }
}
