<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePurchaseInstallment extends Model
{
    protected $fillable = ['employee_purchase_id', 'sequence', 'due_date', 'amount', 'paid_amount', 'status'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'amount' => 'decimal:2', 'paid_amount' => 'decimal:2'];
    }

    public function purchase(): BelongsTo { return $this->belongsTo(EmployeePurchase::class, 'employee_purchase_id'); }
}
