<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuBanner extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'product_id',
        'image',
        'title',
        'subtitle',
        'badge_text',
        'link_url',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'location_id' => 'integer',
            'product_id' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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

    /**
     * Active, in-date-range banners visible at a given branch.
     * NULL/0 are treated as global for compatibility with older data.
     */
    public function scopeVisibleFor(Builder $query, int $locationId): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($locationId): void {
                $q->whereNull('location_id')
                    ->orWhere('location_id', 0)
                    ->orWhere('location_id', $locationId);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
