<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingJournalLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['accounting_journal_id', 'accounting_account_id', 'debit', 'credit', 'memo'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    public function journal(): BelongsTo { return $this->belongsTo(AccountingJournal::class, 'accounting_journal_id'); }
    public function account(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'accounting_account_id'); }
}
