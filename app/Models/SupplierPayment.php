<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    protected $fillable = [
        'payment_number', 'supplier_id', 'supplier_invoice_id', 'location_id',
        'currency_id', 'exchange_rate', 'amount', 'base_amount', 'applied_amount',
        'payment_date', 'payment_method_id', 'reference_number', 'status', 'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
