<?php

namespace App\Models;

use App\Enums\PurchaseReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'return_number', 'supplier_id', 'goods_receipt_id', 'supplier_invoice_id',
        'location_id', 'currency_id', 'exchange_rate', 'status', 'grand_total',
        'base_grand_total', 'returned_by', 'returned_at', 'posted_by', 'posted_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'grand_total' => 'decimal:2',
            'base_grand_total' => 'decimal:2',
            'returned_at' => 'datetime',
            'posted_at' => 'datetime',
            'status' => PurchaseReturnStatus::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function statusValue(): string
    {
        return $this->status instanceof PurchaseReturnStatus
            ? $this->status->value
            : (string) $this->status;
    }

    public function statusLabel(): string
    {
        return $this->status instanceof PurchaseReturnStatus
            ? $this->status->label()
            : ($this->statusValue() ?: '—');
    }
}
