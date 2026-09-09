<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesLedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'location_id',
        'financial_period_id',
        'entry_date',
        'entry_type',
        'amount',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'invoice_id',
        'order_id',
        'special_cake_order_id',
        'description',
        'currency_code',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => 'decimal:2',
            'entry_type' => LedgerEntryType::class,
            'created_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
