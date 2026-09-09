<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'expense_number',
        'expense_category_id',
        'location_id',
        'financial_period_id',
        'amount',
        'expense_date',
        'payee',
        'reference_number',
        'description',
        'status',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'posted_by',
        'posted_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'status' => ExpenseStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo { return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function financialPeriod(): BelongsTo { return $this->belongsTo(FinancialPeriod::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function submittedBy(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function rejectedBy(): BelongsTo { return $this->belongsTo(User::class, 'rejected_by'); }
    public function postedBy(): BelongsTo { return $this->belongsTo(User::class, 'posted_by'); }
    public function voidedBy(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }

    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('location_id', $locationId);
    }

    public function statusValue(): string
    {
        return $this->status instanceof ExpenseStatus ? $this->status->value : (string) $this->status;
    }

    public function isEditable(): bool
    {
        return in_array($this->statusValue(), [
            ExpenseStatus::Draft->value,
            ExpenseStatus::Rejected->value,
        ], true);
    }
}
