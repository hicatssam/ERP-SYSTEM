<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationPaymentAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_payment_method_id',
        'name',
        'provider_name',
        'account_holder_name',
        'account_number',
        'iban',
        'phone_number',
        'wallet_number',
        'instructions',
        'is_active',
        'sort_order',

        // Kept during the compatibility period for existing installations.
        'location_id',
        'payment_method_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function locationPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(LocationPaymentMethod::class);
    }

    /**
     * Legacy convenience relation. Prefer locationPaymentMethod.location.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Legacy convenience relation. Prefer locationPaymentMethod.paymentMethod.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}