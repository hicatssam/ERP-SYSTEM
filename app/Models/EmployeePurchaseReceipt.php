<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePurchaseReceipt extends Model
{
    protected $fillable = [
        'request_key', 'employee_purchase_id', 'location_id', 'payment_method_id',
        'amount', 'status', 'received_at', 'posted_at', 'reference', 'payment_proof',
        'notes', 'created_by', 'verified_by', 'verified_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'received_at' => 'datetime', 'posted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function purchase(): BelongsTo { return $this->belongsTo(EmployeePurchase::class, 'employee_purchase_id'); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
