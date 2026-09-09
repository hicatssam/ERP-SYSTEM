<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'icon',
        'is_active',
        'is_system',
        'sort_order',
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
            'configuration' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(
            Module::class,
            'business_profile_modules'
        )->withPivot([
            'is_required',
            'is_default',
            'sort_order',
        ]);
    }

    public function bundles(): HasMany
    {
        return $this->hasMany(ModuleBundle::class);
    }
}
