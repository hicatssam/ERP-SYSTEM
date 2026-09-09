<?php

namespace App\Models;

use App\Support\ArabicDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'symbol',
        'decimal_places',
        'is_base',
        'is_active',

        // حقول إدارة/عرض فقط
        'icon',
        'image',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc('is_base')
            ->orderBy('sort_order')
            ->orderBy('code');
    }

    public function displayName(): string
    {
        return $this->name_ar ?: ArabicDisplay::currency($this->code);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }

    public function getImageUrlAttribute(): ?string
    {
        $image = trim((string) $this->image);

        if ($image === '') {
            return null;
        }

        if (
            str_starts_with($image, 'http://')
            || str_starts_with($image, 'https://')
            || str_starts_with($image, '//')
        ) {
            return $image;
        }

        $normalized = ltrim(str_replace('\\', '/', $image), '/');

        if (str_starts_with($normalized, 'storage/')) {
            return asset($normalized);
        }

        return asset('storage/' . $normalized);
    }
}
