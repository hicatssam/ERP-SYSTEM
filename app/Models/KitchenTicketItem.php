<?php

namespace App\Models;

use App\Enums\KitchenRoutingSource;
use App\Enums\KitchenTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenTicketItem extends Model
{
    protected $fillable = [
        'kitchen_ticket_id',
        'order_item_id',
        'product_id',
        'product_name',
        'quantity',
        'status',
        'routing_source',
        'kitchen_notes',
        'started_at',
        'ready_at',
        'served_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'status' => KitchenTicketStatus::class,
            'routing_source' => KitchenRoutingSource::class,
            'started_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(KitchenTicket::class, 'kitchen_ticket_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
