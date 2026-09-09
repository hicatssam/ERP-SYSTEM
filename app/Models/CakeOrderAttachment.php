<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CakeOrderAttachment extends Model
{
    protected $fillable = [
        'special_cake_order_id', 'attachment_type',
        'file_path', 'original_name', 'description', 'uploaded_by',
    ];

    public function cakeOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpecialCakeOrder::class, 'special_cake_order_id');
    }

    public function uploader(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
