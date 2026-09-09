<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly string $fromStatus,
        private readonly string $toStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $statusLabels = [
            'draft' => 'مسودة',
            'pending' => 'معلّق',
            'confirmed' => 'مؤكّد',
            'cancelled' => 'ملغى',
            'canceled' => 'ملغى',
            'completed' => 'مكتمل',
        ];

        $fromLabel = $statusLabels[$this->fromStatus] ?? 'غير محدد';
        $toLabel = $statusLabels[$this->toStatus] ?? 'غير محدد';

        $priority = in_array(
            $this->toStatus,
            ['cancelled', 'canceled'],
            true
        ) ? 'high' : 'medium';

        return [
            'fingerprint' => "order_status_{$this->order->id}_{$this->fromStatus}_{$this->toStatus}",
            'type' => 'order_status_changed',
            'title' => 'تغيير حالة طلب',
            'message' => "تغيّرت حالة الطلب {$this->order->order_number} من [{$fromLabel}] إلى [{$toLabel}]",
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'location_id' => $this->order->location_id,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'url' => '/orders/' . $this->order->id,
            'priority' => $priority,
        ];
    }
}