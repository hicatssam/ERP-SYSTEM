<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    protected $fillable = ['code', 'name', 'type', 'subtype', 'is_active', 'is_system'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean'];
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(AccountingJournalLine::class);
    }

    public function typeLabel(): string
    {
        return [
            'asset' => 'أصول', 'liability' => 'التزامات', 'equity' => 'حقوق ملكية',
            'revenue' => 'إيرادات', 'expense' => 'مصروفات',
        ][$this->type] ?? $this->type;
    }
}
