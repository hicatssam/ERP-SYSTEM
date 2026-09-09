<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantMenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'product_id',
        'display_name',
        'display_name_ar',
        'description',
        'image',
        'sort_order',
        'is_active',
        'show_in_pos',
        'show_in_qr',
        'show_in_delivery',
        'inventory_mode',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'show_in_pos' => 'boolean',
            'show_in_qr' => 'boolean',
            'show_in_delivery' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeForPos(Builder $query, ?int $locationId = null): Builder
    {
        $query->active()->where('show_in_pos', true);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return $query;
    }

    public function scopeForQr(Builder $query, ?int $locationId = null): Builder
    {
        $query->active()->where('show_in_qr', true);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return $query;
    }

    public function displayName(): string
    {
        return $this->display_name_ar
            ?: $this->display_name
            ?: $this->product?->name_ar
            ?: $this->product?->name
            ?: 'صنف منيو';
    }

    public function effectiveDescription(): ?string
    {
        return $this->description ?: $this->product?->description;
    }

    public function effectiveImage(): ?string
    {
        return $this->image ?: $this->product?->image;
    }
}
