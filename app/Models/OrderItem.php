<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'product_name', 'variant_name_snapshot',
        'unit_price', 'quantity', 'discount_amount', 'line_total',
        'kitchen_notes',
        'unit_cost_snapshot', 'cost_total_snapshot', 'net_revenue_snapshot',
        'gross_profit_snapshot', 'cost_source', 'cost_snapshotted_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'      => 'decimal:2',
            'quantity'        => 'decimal:3',
            'discount_amount' => 'decimal:2',
            'line_total'      => 'decimal:2',
            'unit_cost_snapshot' => 'decimal:4',
            'cost_total_snapshot' => 'decimal:2',
            'net_revenue_snapshot' => 'decimal:2',
            'gross_profit_snapshot' => 'decimal:2',
            'cost_snapshotted_at' => 'datetime',
        ];
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function modifiers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }

    public function kitchenTicketItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(KitchenTicketItem::class);
    }
}
