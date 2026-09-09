<?php

namespace App\Notifications;

use App\Models\ProductionOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductionOrderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ProductionOrder $order,
        private readonly string $event
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $order = $this->order->loadMissing([
            'product',
            'location',
        ]);

        $productName =
            $order->product?->name_ar
            ?: $order->product?->name
            ?: 'منتج';

        $eventLabel = match ($this->event) {
            'released' => 'تم اعتماد أمر الإنتاج',
            'started' => 'بدأ تنفيذ أمر الإنتاج',
            'completed' => 'اكتمل أمر الإنتاج',
            'cancelled' => 'تم إلغاء أمر الإنتاج',
            default => 'تحديث أمر إنتاج',
        };

        $priority = match ($this->event) {
            'completed' => 'medium',
            'cancelled' => 'high',
            default => 'medium',
        };

        return [
            'fingerprint' =>
                "production_{$this->event}_{$order->id}_{$order->updated_at?->timestamp}",
            'type' => 'production_order',
            'event' => $this->event,
            'title' => $eventLabel,
            'message' =>
                "{$order->production_number} — {$productName}"
                . ($order->location ? " — {$order->location->name}" : ''),
            'production_order_id' => $order->id,
            'production_number' => $order->production_number,
            'location_id' => $order->location_id,
            'product_id' => $order->product_id,
            'url' => '/production/orders/' . $order->id,
            'priority' => $priority,
        ];
    }
}
