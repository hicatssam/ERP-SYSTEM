<?php

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Notifications\Notification;

class CustomerCreatedNotification extends Notification
{
    public function __construct(private readonly Customer $customer) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'customer',
            'title'   => 'عميل جديد',
            'message' => "تم تسجيل عميل جديد: {$this->customer->name}",
            'url'     => '/customers/' . $this->customer->id,
            'icon'    => 'user',
        ];
    }
}
