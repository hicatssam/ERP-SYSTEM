<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeePurchase extends Model
{
    protected $fillable = [
        'number', 'request_key', 'employee_id', 'location_id', 'currency_id',
        'payment_plan', 'status', 'total_amount', 'paid_amount', 'outstanding_amount',
        'purchased_at', 'notes', 'created_by', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2', 'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2', 'purchased_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(EmployeePurchaseItem::class); }
    public function installments(): HasMany { return $this->hasMany(EmployeePurchaseInstallment::class)->orderBy('sequence'); }
    public function receipts(): HasMany { return $this->hasMany(EmployeePurchaseReceipt::class)->latest('id'); }
}
