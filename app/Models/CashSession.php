<?php

namespace App\Models;

use App\Enums\CashSessionStatus;
use Illuminate\Database\Eloquent\Model;

class CashSession extends Model
{
    protected $fillable = [
        'employee_id', 'location_id', 'opening_balance', 'opened_at',
        'status', 'cash_received', 'cash_refunds',
        'expected_cash', 'actual_cash', 'variance', 'closing_note', 'closed_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'cash_received'   => 'decimal:2',
            'cash_refunds'    => 'decimal:2',
            'expected_cash'   => 'decimal:2',
            'actual_cash'     => 'decimal:2',
            'variance'        => 'decimal:2',
            'opened_at'       => 'datetime',
            'closed_at'       => 'datetime',
            'status'          => CashSessionStatus::class,
        ];
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function location(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function closedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool { return $this->status === CashSessionStatus::Open; }
}
