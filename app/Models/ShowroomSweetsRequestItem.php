<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowroomSweetsRequestItem extends Model
{
    protected $fillable = [
        'showroom_sweets_request_id',
        'product_id',
        'product_name_snapshot',
        'quantity',
        'requested_unit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ShowroomSweetsRequest::class, 'showroom_sweets_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function displayProductName(): string
    {
        return $this->product?->name_ar
            ?? $this->product?->name
            ?? $this->product_name_snapshot
            ?? 'منتج محذوف';
    }
}
