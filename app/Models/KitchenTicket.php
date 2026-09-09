<?php

namespace App\Models;

use App\Enums\KitchenTicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'order_id',
        'location_id',
        'kitchen_station_id',
        'status',
        'priority',
        'notes',
        'queued_at',
        'started_at',
        'ready_at',
        'served_at',
        'cancelled_at',
        'dispatched_by',
        'started_by',
        'ready_by',
        'served_by',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => KitchenTicketStatus::class,
            'priority' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'kitchen_station_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitchenTicketItem::class, 'kitchen_ticket_id');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function readyBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ready_by');
    }

    public function servedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            KitchenTicketStatus::QUEUED->value,
            KitchenTicketStatus::PREPARING->value,
            KitchenTicketStatus::READY->value,
        ]);
    }

    public function statusValue(): string
    {
        return $this->status instanceof KitchenTicketStatus
            ? $this->status->value
            : (string) $this->status;
    }

    public function isUrgent(): bool
    {
        return $this->priority >= 10;
    }

    public function ageSeconds(): int
    {
        return $this->queued_at
            ? max(0, (int) $this->queued_at->diffInSeconds(now()))
            : 0;
    }
}
