<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'order_item_id', 'product_id', 'description',
        'quantity', 'unit_price', 'discount_amount', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity'        => 'decimal:3',
            'unit_price'      => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total'      => 'decimal:2',
        ];
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function orderItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
