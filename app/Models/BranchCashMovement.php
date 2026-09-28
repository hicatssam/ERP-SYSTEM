<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchCashMovement extends Model
{
    protected $fillable = [
        'location_id', 'type', 'amount', 'occurred_at', 'reference', 'description',
        'created_by', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
}
