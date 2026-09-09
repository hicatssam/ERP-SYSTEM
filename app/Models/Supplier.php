<?php

namespace App\Models;

use App\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_code', 'name', 'company_name', 'contact_person', 'phone',
        'whatsapp', 'email', 'address', 'city', 'country', 'tax_number',
        'commercial_registration', 'currency_id', 'payment_terms',
        'credit_limit', 'opening_balance', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'status' => SupplierStatus::class,
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SupplierStatus::Active->value);
    }

    public function isTransactable(): bool
    {
        return $this->status === SupplierStatus::Active;
    }

    public function statusValue(): string
    {
        return $this->status instanceof SupplierStatus
            ? $this->status->value
            : (string) $this->status;
    }

    public function statusLabel(): string
    {
        return $this->status instanceof SupplierStatus
            ? $this->status->label()
            : ($this->statusValue() ?: '—');
    }
}
