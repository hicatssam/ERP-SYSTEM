<?php

namespace App\Models;

use App\Enums\SupplierInvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_number', 'supplier_id', 'purchase_order_id', 'goods_receipt_id',
        'location_id', 'currency_id', 'exchange_rate', 'invoice_date', 'due_date',
        'subtotal', 'discount_amount', 'tax_amount', 'grand_total', 'base_grand_total',
        'paid_amount', 'credited_amount', 'remaining_amount', 'status', 'notes', 'created_by',
        'cancelled_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'base_grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'credited_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'status' => SupplierInvoiceStatus::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function statusValue(): string
    {
        return $this->status instanceof SupplierInvoiceStatus
            ? $this->status->value
            : (string) $this->status;
    }

    public function statusLabel(): string
    {
        return $this->status instanceof SupplierInvoiceStatus
            ? $this->status->label()
            : ($this->statusValue() ?: '—');
    }

    public function isPayable(): bool
    {
        return in_array($this->statusValue(), [
            SupplierInvoiceStatus::Unpaid->value,
            SupplierInvoiceStatus::PartiallyPaid->value,
            SupplierInvoiceStatus::Overdue->value,
        ], true);
    }
}
