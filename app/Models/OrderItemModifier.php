<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemModifier extends Model
{
    protected $fillable = [
        'order_item_id', 'modifier_group_id', 'modifier_id',
        'group_name_snapshot', 'modifier_name_snapshot',
        'price_delta_snapshot', 'quantity', 'total_delta_snapshot',
        'ingredient_cost_delta_snapshot', 'configuration_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'price_delta_snapshot' => 'decimal:3',
            'quantity' => 'integer',
            'total_delta_snapshot' => 'decimal:3',
            'ingredient_cost_delta_snapshot' => 'decimal:4',
            'configuration_snapshot' => 'array',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id')->withTrashed();
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class)->withTrashed();
    }
}
