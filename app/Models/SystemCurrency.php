<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemCurrency extends Model
{
    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'symbol',
        'icon',
        'image',
        'decimal_places',
        'is_active',
        'is_default',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->orderBy('code');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name_ar ?: ($this->name_en ?: $this->code);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image
            ? asset('storage/' . ltrim($this->image, '/'))
            : null;
    }
}
