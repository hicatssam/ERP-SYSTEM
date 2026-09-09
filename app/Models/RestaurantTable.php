<?php

namespace App\Models;

use App\Enums\RestaurantTableSessionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestaurantTable extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'location_id', 'area_id', 'code', 'name', 'capacity',
        'sort_order', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(RestaurantArea::class, 'area_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(RestaurantTableSession::class, 'restaurant_table_id');
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(RestaurantTableSession::class, 'restaurant_table_id')
            ->where('status', RestaurantTableSessionStatus::Open->value)
            ->latestOfMany('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'restaurant_table_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('location_id', $locationId);
    }

    public function isOccupied(): bool
    {
        if ($this->relationLoaded('activeSession')) {
            return $this->activeSession !== null;
        }

        return $this->activeSession()->exists();
    }

    public function displayName(): string
    {
        return $this->name ?: $this->code;
    }
}
