<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CakeOrderStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'special_cake_order_id', 'from_status', 'to_status',
        'changed_by', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function cakeOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpecialCakeOrder::class, 'special_cake_order_id');
    }

    public function changedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
