<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCashReconciliation extends Model
{
    protected $fillable = [
        'location_id', 'business_date', 'opening_balance', 'sales', 'customer_receipts', 'employee_receipts',
        'voucher_receipts', 'voucher_payments',
        'other_income', 'expenses', 'supplier_payments', 'refunds', 'transfer_in',
        'transfer_out', 'expected_closing', 'actual_closing', 'variance', 'note',
        'closed_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date', 'closed_at' => 'datetime',
            ...array_fill_keys([
                'opening_balance', 'sales', 'customer_receipts', 'employee_receipts', 'voucher_receipts', 'voucher_payments', 'other_income',
                'expenses', 'supplier_payments', 'refunds', 'transfer_in', 'transfer_out',
                'expected_closing', 'actual_closing', 'variance',
            ], 'decimal:2'),
        ];
    }

    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function closedBy(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
}
