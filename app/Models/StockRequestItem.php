<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRequestItem extends Model
{
    protected $fillable = ['stock_request_id', 'product_id', 'requested_quantity', 'approved_quantity', 'note'];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:3',
            'approved_quantity'  => 'decimal:3',
        ];
    }

    public function stockRequest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
