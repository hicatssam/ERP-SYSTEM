<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationPaymentMethod extends Model
{
    protected $fillable = [

        'location_id',

        'payment_method_id',

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
}