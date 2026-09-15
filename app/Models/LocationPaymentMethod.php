<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class LocationPaymentMethod extends Model
{
    protected $fillable = [
        'location_id',
        'payment_method_id',

        // Legacy detail columns kept temporarily for existing installations.
        'mobile_number',
        'account_holder_name',
        'bank_name',
        'bank_account_number',
        'iban',
        'wallet_number',
        'payment_instructions',
        'details',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'details' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Canonical account relation for new installations, with a safe fallback
     * for older Dahab databases that still store location_id/payment_method_id
     * directly on location_payment_accounts.
     */
    public function accounts(): HasMany
    {
        $query = $this->accountRelation();

        return $query
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeAccounts(): HasMany
    {
        $query = $this->accountRelation();

        return $query
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    private function accountRelation(): HasMany
    {
        if (Schema::hasColumn('location_payment_accounts', 'location_payment_method_id')) {
            return $this->hasMany(
                LocationPaymentAccount::class,
                'location_payment_method_id',
                'id'
            );
        }

        // Legacy schema: an account belongs to a payment method + location.
        // The relation still returns HasMany so it can be eager-loaded by the
        // public menu payment-options endpoint without throwing SQL errors.
        return $this->hasMany(
            LocationPaymentAccount::class,
            'payment_method_id',
            'payment_method_id'
        )->where('location_id', (int) $this->location_id);
    }
}
