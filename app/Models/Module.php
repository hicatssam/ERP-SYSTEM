<?php

namespace App\Models;

use App\Enums\ModuleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'icon',
        'route_prefix',
        'is_core',
        'is_active',
        'is_system',
        'sort_order',
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'type' => ModuleType::class,
            'is_core' => 'boolean',
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

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'module_dependencies',
            'module_id',
            'required_module_id'
        );
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'module_dependencies',
            'required_module_id',
            'module_id'
        );
    }

    public function businessProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            BusinessProfile::class,
            'business_profile_modules'
        )->withPivot([
            'is_required',
            'is_default',
            'sort_order',
        ]);
    }

    public function bundles(): BelongsToMany
    {
        return $this->belongsToMany(
            ModuleBundle::class,
            'module_bundle_modules'
        )->withPivot('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCore(Builder $query): Builder
    {
        return $query->where('type', ModuleType::CORE->value);
    }

    public function scopeIndustry(Builder $query): Builder
    {
        return $query->where('type', ModuleType::INDUSTRY->value);
    }

    public function scopeOptional(Builder $query): Builder
    {
        return $query->where('type', ModuleType::OPTIONAL->value);
    }

    public function isImplemented(): bool
    {
        return (bool) data_get($this->configuration, 'implemented', false);
    }
}
