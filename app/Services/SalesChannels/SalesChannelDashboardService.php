<?php

namespace App\Services\SalesChannels;

use App\Models\Order;
use Illuminate\Support\Collection;

class SalesChannelDashboardService
{
    public function summary(?int $locationId = null): array
    {
        $base = Order::query()
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('sales_channel_id')
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId));

        $byChannel = (clone $base)
            ->join('sales_channels', 'sales_channels.id', '=', 'orders.sales_channel_id')
            ->selectRaw('sales_channels.id, sales_channels.name')
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->selectRaw('COALESCE(SUM(orders.subtotal),0) as gross_sales')
            ->selectRaw('COALESCE(SUM(orders.channel_discount_amount),0) as discounts')
            ->selectRaw('COALESCE(SUM(orders.channel_net_revenue),0) as net_revenue')
            ->groupBy('sales_channels.id', 'sales_channels.name')
            ->orderByDesc('gross_sales')
            ->get();

        return [
            'orders_count' => (clone $base)->count(),
            'gross_sales' => (float) (clone $base)->sum('subtotal'),
            'discounts' => (float) (clone $base)->sum('channel_discount_amount'),
            'net_revenue' => (float) (clone $base)->sum('channel_net_revenue'),
            'top_channel' => $byChannel->first(),
            'by_channel' => $byChannel,
        ];
    }
}
