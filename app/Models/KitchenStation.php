<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenStation extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'name',
        'code',
        'description',
        'target_minutes',
        'is_default',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_minutes' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(KitchenTicket::class, 'kitchen_station_id');
    }

    public function productRoutes(): HasMany
    {
        return $this->hasMany(KitchenProductRoute::class, 'kitchen_station_id');
    }

    public function categoryRoutes(): HasMany
    {
        return $this->hasMany(KitchenCategoryRoute::class, 'kitchen_station_id');
    }

    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function displayName(): string
    {
        return $this->name . ($this->is_default ? ' — الافتراضية' : '');
    }
}
