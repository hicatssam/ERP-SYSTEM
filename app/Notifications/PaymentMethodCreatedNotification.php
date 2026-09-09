<?php

namespace App\Notifications;

use App\Models\PaymentMethod;
use Illuminate\Notifications\Notification;

class PaymentMethodCreatedNotification extends Notification
{
    public function __construct(private readonly PaymentMethod $method) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'payment',
            'title'   => 'طريقة دفع جديدة',
            'message' => "تم إضافة طريقة الدفع: {$this->method->name_ar}",
            'url'     => '/payment-methods',
            'icon'    => 'payment',
        ];
    }
}
