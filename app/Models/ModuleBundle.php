<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ModuleBundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_profile_id',
        'name',
        'code',
        'description',
        'is_active',
        'sort_order',
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'configuration' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(
            Module::class,
            'module_bundle_modules'
        )->withPivot('sort_order');
    }
}
