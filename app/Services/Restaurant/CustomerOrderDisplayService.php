<?php

namespace App\Services\Restaurant;

use App\Models\KitchenTicket;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;

class CustomerOrderDisplayService
{
    public function feedForLocation(int $locationId): array
    {
        $historyHours = max(
            1,
            min(
                48,
                (int) SystemSetting::get(
                    'customer_display_history_hours',
                    12
                )
            )
        );

        $tickets = KitchenTicket::query()
            ->where('location_id', $locationId)
            ->whereIn('status', [
                'queued',
                'preparing',
                'ready',
                'served',
            ])
            ->where(
                'created_at',
                '>=',
                now()->subHours($historyHours)
            )
            ->with([
                'order.restaurantTable',
            ])
            ->orderBy('created_at')
            ->get()
            ->filter(function (KitchenTicket $ticket): bool {
                $order = $ticket->order;

                if (! $order) {
                    return false;
                }

                if (
                    $this->scalar($order->status)
                    ===
                    'cancelled'
                ) {
                    return false;
                }

                return filled(
                    $order->restaurant_service_type
                );
            });

        $orders = $tickets
            ->groupBy('order_id')
            ->map(
                fn (Collection $group) =>
                    $this->mapOrder($group)
            )
            ->filter()
            ->values();

        $preparing = $orders
            ->whereIn(
                'display_status',
                ['queued', 'preparing']
            )
            ->sortBy('sort_time')
            ->values();

        $ready = $orders
            ->where(
                'display_status',
                'ready'
            )
            ->sortByDesc('ready_sort_time')
            ->values();

        $fingerprint = sha1(
            $orders
                ->map(
                    fn (array $row): string =>
                        $row['id']
                        . ':'
                        . $row['display_status']
                        . ':'
                        . ($row['ready_at'] ?? '')
                )
                ->sort()
                ->implode('|')
        );

        return [
            'preparing' => $preparing->all(),
            'ready' => $ready->all(),
            'counts' => [
                'preparing' => $preparing->count(),
                'ready' => $ready->count(),
                'total' => $orders->count(),
            ],
            'fingerprint' => $fingerprint,
        ];
    }

    private function mapOrder(
        Collection $tickets
    ): ?array {
        /** @var KitchenTicket|null $first */
        $first = $tickets->first();

        if (! $first || ! $first->order) {
            return null;
        }

        $order = $first->order;

        $statuses = $tickets
            ->map(
                fn (KitchenTicket $ticket): string =>
                    $ticket->statusValue()
            )
            ->values();

        if (
            $statuses->isNotEmpty()
            && $statuses->every(
                fn (string $status): bool =>
                    $status === 'served'
            )
        ) {
            return null;
        }

        $remaining = $statuses
            ->reject(
                fn (string $status): bool =>
                    $status === 'served'
            )
            ->values();

        if (
            $remaining->isNotEmpty()
            && $remaining->every(
                fn (string $status): bool =>
                    $status === 'ready'
            )
        ) {
            $displayStatus = 'ready';
        } elseif (
            $statuses->contains('preparing')
            || $statuses->contains('ready')
            || $statuses->contains('served')
        ) {
            $displayStatus = 'preparing';
        } else {
            $displayStatus = 'queued';
        }

        $serviceType = $this->scalar(
            $order->restaurant_service_type
        );

        $table = $order->restaurantTable;

        $readyAt = $tickets
            ->pluck('ready_at')
            ->filter()
            ->sortDesc()
            ->first();

        $sortTime = optional(
            $order->confirmed_at
            ?? $order->created_at
        )->timestamp ?? 0;

        $readySortTime = $readyAt
            ? $readyAt->timestamp
            : $sortTime;

        return [
            'id' => (int) $order->id,
            'number' => (string) $order->order_number,
            'display_number' => $this->displayNumber(
                (string) $order->order_number
            ),
            'display_status' => $displayStatus,
            'status_label' => match ($displayStatus) {
                'queued' => 'بانتظار التحضير',
                'preparing' => 'قيد التحضير',
                'ready' => 'جاهز للاستلام',
                default => 'قيد المتابعة',
            },
            'service_type' => $serviceType,
            'service_label' => match ($serviceType) {
                'dine_in' => 'داخل المطعم',
                'takeaway',
                'take_away' => 'سفري',
                'delivery' => 'توصيل',
                default => 'طلب مطعم',
            },
            'table' =>
                $serviceType === 'dine_in'
                && $table
                    ? [
                        'id' => (int) $table->id,
                        'code' => (string) $table->code,
                        'name' => (string)
                            $table->displayName(),
                    ]
                    : null,
            'ready_at' =>
                $readyAt?->toIso8601String(),
            'created_at' =>
                $order->created_at?->toIso8601String(),
            'ticket_count' => $tickets->count(),
            'sort_time' => $sortTime,
            'ready_sort_time' => $readySortTime,
        ];
    }

    private function scalar(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) ($value ?? '');
    }

    private function displayNumber(
        string $orderNumber
    ): string {
        if (
            preg_match(
                '/(\d{3,})$/',
                $orderNumber,
                $matches
            )
        ) {
            return $matches[1];
        }

        return $orderNumber;
    }
}
