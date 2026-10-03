<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingJournal extends Model
{
    protected $fillable = [
        'number', 'request_key', 'location_id', 'financial_period_id', 'currency_code',
        'entry_date', 'kind', 'description', 'total_debit', 'total_credit',
        'source_type', 'source_id', 'reverses_journal_id', 'created_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date', 'posted_at' => 'datetime',
            'total_debit' => 'decimal:2', 'total_credit' => 'decimal:2',
        ];
    }

    public function lines(): HasMany { return $this->hasMany(AccountingJournalLine::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function period(): BelongsTo { return $this->belongsTo(FinancialPeriod::class, 'financial_period_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function original(): BelongsTo { return $this->belongsTo(self::class, 'reverses_journal_id'); }
    public function reversal(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(self::class, 'reverses_journal_id'); }
}
