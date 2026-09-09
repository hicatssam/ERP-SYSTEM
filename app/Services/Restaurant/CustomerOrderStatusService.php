<?php

namespace App\Services\Restaurant;

use App\Models\Order;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;

class CustomerOrderStatusService
{
    public function payload(Order $order): array
    {
        /*
         * Always read kitchen tickets from the database on every poll.
         * Do not rely on an already-loaded relation so the public customer page
         * reflects KDS changes immediately.
         */
        $order->refresh();

        $state = $this->resolveState($order);

        $message = trim((string) ($order->customer_status_message ?? ''));
        $messageUpdatedAt = $order->customer_status_message_updated_at?->toIso8601String();

        $statusKey = $state['state'];

        $label = trim((string) SystemSetting::get(
            "customer_menu_status_{$statusKey}_title",
            $state['default_label']
        )) ?: $state['default_label'];

        $description = trim((string) SystemSetting::get(
            "customer_menu_status_{$statusKey}_message",
            $state['default_description']
        )) ?: $state['default_description'];

        $serviceType = $this->scalar($order->restaurant_service_type);

        $table = $order->restaurantTable()
            ->with('area:id,name')
            ->first();

        $items = $order->items()
            ->orderBy('id')
            ->get(['id', 'product_name', 'quantity', 'line_total'])
            ->map(fn ($item): array => [
                'name' => (string) $item->product_name,
                'quantity' => (float) $item->quantity,
                'line_total' => (float) $item->line_total,
            ])
            ->values()
            ->all();

        $fingerprint = sha1(json_encode([
            'order_status' => $order->statusValue(),
            'state' => $statusKey,
            'event_at' => $state['event_at'],
            'updated_at' => $order->updated_at?->toIso8601String(),
            'message' => $message,
            'message_updated_at' => $messageUpdatedAt,
        ], JSON_UNESCAPED_UNICODE));

        return [
            'order_number' => (string) $order->order_number,
            'status' => $order->statusValue(),
            'state' => $statusKey,
            'kitchen_status' => $state['kitchen_status'],
            'label' => $label,
            'description' => $description,
            'step' => $state['step'],
            'total' => (float) $order->total_amount,
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'event_at' => $state['event_at'],
            'fingerprint' => $fingerprint,

            'service_type' => $serviceType,
            'service_label' => match ($serviceType) {
                'dine_in' => 'داخل المطعم',
                'takeaway', 'take_away' => 'سفري',
                'delivery' => 'توصيل',
                default => 'طلب مطعم',
            },

            'table' => $table
                ? [
                    'id' => (int) $table->id,
                    'code' => (string) $table->code,
                    'name' => (string) $table->displayName(),
                    'area' => (string) ($table->area?->name ?? ''),
                ]
                : null,

            'location' => $order->location
                ? [
                    'id' => (int) $order->location->id,
                    'name' => (string) $order->location->name,
                    'code' => (string) ($order->location->code ?? ''),
                ]
                : null,

            'items' => $items,
            'item_count' => count($items),

            'toast_enabled' => (bool) SystemSetting::get(
                'customer_menu_status_toast_enabled',
                true
            ),
            'sound_enabled' => (bool) SystemSetting::get(
                'customer_menu_status_sound_enabled',
                true
            ),
            'toast_title' => $label,
            'toast_message' => $description,

            'customer_message' => $message !== '' ? $message : null,
            'customer_message_updated_at' => $messageUpdatedAt,
            'customer_message_signature' => $message !== ''
                ? sha1($message . '|' . ($messageUpdatedAt ?? ''))
                : null,

            'track_url' => $order->public_token
                ? route('customer-menu.track', ['token' => $order->public_token])
                : null,
        ];
    }

    public function resolveState(Order $order): array
    {
        $orderStatus = $order->statusValue();

        if ($orderStatus === 'cancelled') {
            return [
                'state' => 'cancelled',
                'step' => 0,
                'kitchen_status' => 'cancelled',
                'event_at' => $order->cancelled_at?->toIso8601String(),
                'default_label' => 'تم إلغاء الطلب',
                'default_description' => 'تم إلغاء الطلب. تواصل مع الفرع إذا احتجت للمساعدة.',
            ];
        }

        /*
         * Read fresh tickets every time. This is intentionally the same status
         * logic used by the customer-facing kitchen display:
         *
         * queued -> accepted
         * preparing / mixed ready -> preparing
         * all non-served tickets ready -> ready
         * all tickets served -> completed
         */
        $tickets = $order->kitchenTickets()
            ->orderBy('id')
            ->get();

        $ticketStatuses = $tickets
            ->map(fn ($ticket): string => $ticket->statusValue())
            ->values();

        if ($orderStatus === 'completed') {
            return [
                'state' => 'completed',
                'step' => 4,
                'kitchen_status' => 'served',
                'event_at' => $order->completed_at?->toIso8601String(),
                'default_label' => 'تم تسليم الطلب',
                'default_description' => 'تم تسليم طلبك. شكراً لاختيارك لنا.',
            ];
        }

        if ($ticketStatuses->isNotEmpty()) {
            $nonCancelled = $ticketStatuses
                ->reject(fn (string $status): bool => $status === 'cancelled')
                ->values();

            if (
                $nonCancelled->isNotEmpty()
                && $nonCancelled->every(fn (string $status): bool => $status === 'served')
            ) {
                return [
                    'state' => 'completed',
                    'step' => 4,
                    'kitchen_status' => 'served',
                    'event_at' => $this->latestTicketTime($tickets, 'served_at'),
                    'default_label' => 'تم تسليم الطلب',
                    'default_description' => 'تم تسليم طلبك. شكراً لاختيارك لنا.',
                ];
            }

            $remaining = $nonCancelled
                ->reject(fn (string $status): bool => $status === 'served')
                ->values();

            if (
                $remaining->isNotEmpty()
                && $remaining->every(fn (string $status): bool => $status === 'ready')
            ) {
                return [
                    'state' => 'ready',
                    'step' => 4,
                    'kitchen_status' => 'ready',
                    'event_at' => $this->latestTicketTime($tickets, 'ready_at'),
                    'default_label' => 'طلبك جاهز 🎉',
                    'default_description' => 'طلبك جاهز الآن للاستلام. نتمنى لك وجبة شهية!',
                ];
            }

            if (
                $ticketStatuses->contains('preparing')
                || $ticketStatuses->contains('ready')
                || $ticketStatuses->contains('served')
            ) {
                return [
                    'state' => 'preparing',
                    'step' => 3,
                    'kitchen_status' => 'preparing',
                    'event_at' => $this->latestTicketTime($tickets, 'started_at')
                        ?? $this->latestTicketTime($tickets, 'queued_at'),
                    'default_label' => 'طلبك قيد التحضير',
                    'default_description' => 'فريقنا يجهز طلبك الآن.',
                ];
            }

            if ($ticketStatuses->contains('queued')) {
                return [
                    'state' => 'accepted',
                    'step' => 2,
                    'kitchen_status' => 'queued',
                    'event_at' => $this->latestTicketTime($tickets, 'queued_at'),
                    'default_label' => 'تم قبول الطلب',
                    'default_description' => 'تم قبول طلبك وسيدخل التحضير الآن.',
                ];
            }
        }

        if ($orderStatus === 'confirmed') {
            return [
                'state' => 'accepted',
                'step' => 2,
                'kitchen_status' => null,
                'event_at' => $order->confirmed_at?->toIso8601String(),
                'default_label' => 'تم قبول الطلب',
                'default_description' => 'تم قبول طلبك وسيدخل التحضير الآن.',
            ];
        }

        return [
            'state' => 'received',
            'step' => 1,
            'kitchen_status' => null,
            'event_at' => $order->created_at?->toIso8601String(),
            'default_label' => 'تم استلام طلبك',
            'default_description' => 'وصل طلبك للفرع وهو بانتظار القبول.',
        ];
    }

    private function latestTicketTime(Collection $tickets, string $field): ?string
    {
        $time = $tickets
            ->pluck($field)
            ->filter()
            ->sortDesc()
            ->first();

        return $time?->toIso8601String();
    }

    private function scalar(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) ($value ?? '');
    }
}
