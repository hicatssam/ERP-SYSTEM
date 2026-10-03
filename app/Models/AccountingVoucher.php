<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingVoucher extends Model
{
    protected $fillable = [
        'request_key', 'number', 'type', 'status', 'location_id', 'payment_method_id',
        'treasury_account_id', 'counter_account_id', 'currency_id', 'voucher_date',
        'amount', 'party_name', 'external_reference', 'description', 'payment_proof',
        'created_by', 'posted_by', 'posted_at', 'accounting_journal_id',
        'reversed_by', 'reversed_at', 'reversal_reason', 'reversal_journal_id',
    ];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date', 'amount' => 'decimal:2',
            'posted_at' => 'datetime', 'reversed_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }
    public function treasuryAccount(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'treasury_account_id'); }
    public function counterAccount(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'counter_account_id'); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function poster(): BelongsTo { return $this->belongsTo(User::class, 'posted_by'); }
    public function journal(): BelongsTo { return $this->belongsTo(AccountingJournal::class, 'accounting_journal_id'); }
    public function reversalJournal(): BelongsTo { return $this->belongsTo(AccountingJournal::class, 'reversal_journal_id'); }
}
