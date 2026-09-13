<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationPaymentMethod extends Model
{
    protected $fillable = [
        'location_id',
        'payment_method_id',
        'is_active',

        // Legacy fields are kept temporarily for backward compatibility.
        // New account data belongs in location_payment_accounts.
        'mobile_number',
        'account_holder_name',
        'bank_name',
        'bank_account_number',
        'iban',
        'wallet_number',
        'payment_instructions',
        'details',
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

    public function accounts(): HasMany
    {
        return $this->hasMany(LocationPaymentAccount::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeAccounts(): HasMany
    {
        return $this->hasMany(LocationPaymentAccount::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}