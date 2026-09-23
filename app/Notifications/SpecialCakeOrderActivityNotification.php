<?php

namespace App\Notifications;

use App\Models\SpecialCakeOrder;
use Illuminate\Notifications\Notification;

class SpecialCakeOrderActivityNotification extends Notification
{
    public function __construct(
        private readonly SpecialCakeOrder $order,
        private readonly string $activity,
        private readonly string $actorName,
        private readonly ?string $detail = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        [$title, $message] = match ($this->activity) {
            'comment' => [
                'ملاحظة جديدة على طلب كيك',
                "{$this->actorName} أضاف ملاحظة على الطلب {$this->order->order_number}.",
            ],
            'attachment' => [
                'مرفق جديد على طلب كيك',
                "{$this->actorName} أضاف مرفقًا جديدًا إلى الطلب {$this->order->order_number}.",
            ],
            default => [
                'تحديث على طلب كيك',
                "تم تحديث الطلب {$this->order->order_number}.",
            ],
        };

        if (filled($this->detail)) {
            $message .= ' ' . $this->detail;
        }

        return [
            'fingerprint' => sprintf(
                'cake_activity_%s_%d_%s_%s',
                $this->activity,
                $this->order->id,
                now()->format('YmdHis'),
                substr(md5((string) $this->detail), 0, 8)
            ),
            'type' => 'cake_order_activity',
            'title' => $title,
            'message' => $message,
            'cake_order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'activity' => $this->activity,
            'origin_branch_id' => $this->order->origin_branch_id,
            'factory_location_id' => $this->order->factory_location_id,
            'url' => '/cake-orders/' . $this->order->id,
            'priority' => 'medium',
        ];
    }
}
