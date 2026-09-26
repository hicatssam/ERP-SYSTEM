<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShowroomCakeRequestItem extends Model
{
    protected $fillable = [
        'showroom_cake_request_id',
        'cake_type',
        'cake_size',
        'flavor',
        'shape',
        'quantity',
        'reserved_quantity',
        'reservation_notes',
        'notes',
    ];

    public function availableQuantity(): int
    {
        return max(
            0,
            (int) $this->quantity
                - (int) $this->reserved_quantity
        );
    }

    public function request(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ShowroomCakeRequest::class, 'showroom_cake_request_id');
    }
}
