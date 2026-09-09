<?php

namespace App\Models;

use App\Enums\GoodsReceiptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receipt_number', 'purchase_order_id', 'supplier_id', 'location_id',
        'currency_id', 'exchange_rate', 'status', 'received_by', 'received_at',
        'posted_by', 'posted_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'received_at' => 'datetime',
            'posted_at' => 'datetime',
            'status' => GoodsReceiptStatus::class,
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function statusValue(): string
    {
        return $this->status instanceof GoodsReceiptStatus
            ? $this->status->value
            : (string) $this->status;
    }

    public function statusLabel(): string
    {
        return $this->status instanceof GoodsReceiptStatus
            ? $this->status->label()
            : ($this->statusValue() ?: '—');
    }
}
