<?php

namespace App\Models;

use App\Enums\RestaurantTableSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTableSession extends Model
{
    protected $fillable = [
        'restaurant_table_id', 'location_id', 'opened_by', 'closed_by',
        'status', 'guest_count', 'opened_at', 'closed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => RestaurantTableSessionStatus::class,
            'guest_count' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'restaurant_table_session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === RestaurantTableSessionStatus::Open;
    }
}
