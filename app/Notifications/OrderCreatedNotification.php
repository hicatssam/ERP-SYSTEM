<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'fingerprint'  => 'order_created_' . $this->order->id,
            'type'         => 'order_created',
            'title'        => 'طلب جديد',
            'message'      => "تم إنشاء طلب جديد رقم {$this->order->order_number}",
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'location_id'  => $this->order->location_id,
            'total_amount' => $this->order->total_amount,
            'url'          => '/orders/' . $this->order->id,
            'priority'     => 'medium',
        ];
    }
}
