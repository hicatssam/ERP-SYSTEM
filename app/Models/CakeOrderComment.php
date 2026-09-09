<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CakeOrderComment extends Model
{
    protected $fillable = ['special_cake_order_id', 'user_id', 'comment', 'is_internal'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function cakeOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpecialCakeOrder::class, 'special_cake_order_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
