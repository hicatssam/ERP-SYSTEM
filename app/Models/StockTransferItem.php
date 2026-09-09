<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    protected $fillable = [
        'stock_transfer_id', 'product_id', 'sent_quantity', 'received_quantity',
        'damaged_quantity', 'unit_cost', 'currency_id',
    ];

    protected function casts(): array
    {
        return [
            'sent_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'damaged_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
