<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'name_ar',
        'code',
        'logo',
        'logo_path',
        'type',
        'requires_verification',
        'requires_reference',
        'is_active',
        'sort_order',
        'description',
        'api_key',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_verification' => 'boolean',
            'requires_reference' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function locationPaymentMethods(): HasMany
    {
        return $this->hasMany(LocationPaymentMethod::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Transitional branch-availability rule.
     *
     * Methods that do not have any branch mappings yet remain globally available
     * for legacy installations. Once a method has at least one mapping, it is
     * available only where an active mapping exists.
     */
    public function isAvailableAt(int $locationId): bool
    {
        if (! $this->is_active || $locationId <= 0) {
            return false;
        }

        if (! $this->locationPaymentMethods()->exists()) {
            return true;
        }

        return $this->locationPaymentMethods()
            ->where('location_id', $locationId)
            ->where('is_active', true)
            ->exists();
    }
}
