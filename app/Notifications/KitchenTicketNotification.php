<?php

namespace App\Notifications;

use App\Models\KitchenTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class KitchenTicketNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly KitchenTicket $ticket,
        private readonly string $event
    ) {
    }

    public function via(object $notifiable): array
    {
        return [
            'database',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $ticket = $this->ticket->loadMissing([
            'station',
            'location',
            'order.restaurantTable.area',
            'order.waiter.employee',
        ]);

        $order = $ticket->order;
        $table = $order?->restaurantTable;

        $title = match ($this->event) {
            'queued' => 'طلب جديد وصل إلى المطبخ',
            'ready' => 'الطلب جاهز للتسليم',
            'priority' => $ticket->isUrgent()
                ? 'تم رفع أولوية طلب المطبخ'
                : 'تم إلغاء أولوية طلب المطبخ',
            default => 'تحديث في المطبخ',
        };

        $parts = array_filter([
            $ticket->station?->name,
            $order?->order_number,
            $table
                ? trim(
                    ($table->area?->name ? $table->area->name . ' — ' : '')
                    . $table->displayName()
                )
                : $order?->restaurant_service_type?->label(),
        ]);

        return [
            'fingerprint' =>
                'kitchen_ticket_'
                . $this->event
                . '_'
                . $ticket->id,

            'type' => 'kitchen_ticket',
            'title' => $title,
            'message' => implode(' — ', $parts),
            'event' => $this->event,
            'kitchen_ticket_id' => $ticket->id,
            'order_id' => $ticket->order_id,
            'location_id' => $ticket->location_id,
            'kitchen_station_id' => $ticket->kitchen_station_id,
            'url' => $this->event === 'ready'
                ? '/orders/' . $ticket->order_id
                : '/kds?location_id=' . $ticket->location_id,
            'priority' => $ticket->isUrgent() || $this->event === 'ready'
                ? 'high'
                : 'medium',
        ];
    }
}
