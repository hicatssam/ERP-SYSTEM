<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'image',
        'kicker',
        'title',
        'subtitle',
        'cta_text',
        'cta_link',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * بانرات فعّالة الآن فقط: is_active = true، وضمن فترة starts_at/ends_at لو محددة.
     */
    public function scopeActiveNow(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * بانرات فرع معيّن (تشمل البانرات العامة location_id = null).
     */
    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->where(function (Builder $q) use ($locationId) {
            $q->whereNull('location_id')->orWhere('location_id', $locationId);
        });
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (preg_match('#^https?://#i', $this->image)) {
            return $this->image;
        }

        return asset('storage/' . ltrim($this->image, '/'));
    }
}
