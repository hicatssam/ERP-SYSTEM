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
        'notes',
    ];

    public function request(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ShowroomCakeRequest::class, 'showroom_cake_request_id');
    }
}
