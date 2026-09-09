<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'purchase_order_number', 'supplier_id', 'location_id', 'currency_id',
        'exchange_rate', 'order_date', 'expected_delivery_date', 'status',
        'subtotal', 'discount_amount', 'tax_amount', 'shipping_cost',
        'grand_total', 'base_grand_total', 'notes', 'created_by', 'approved_by',
        'approved_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'base_grand_total' => 'decimal:2',
            'status' => PurchaseOrderStatus::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function statusValue(): string
    {
        return $this->status instanceof PurchaseOrderStatus
            ? $this->status->value
            : (string) $this->status;
    }

    public function statusLabel(): string
    {
        return $this->status instanceof PurchaseOrderStatus
            ? $this->status->label()
            : ($this->statusValue() ?: '—');
    }

    public function isReceivable(): bool
    {
        return in_array($this->statusValue(), [
            PurchaseOrderStatus::Approved->value,
            PurchaseOrderStatus::PartiallyReceived->value,
        ], true);
    }
}
