<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        return $this->hasMany(
            LocationPaymentMethod::class
        );
    }

    public function scopeActive($query)
{
    return $query->where('is_active', true);
}
}