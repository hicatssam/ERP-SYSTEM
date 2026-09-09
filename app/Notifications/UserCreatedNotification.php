<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class UserCreatedNotification extends Notification
{
    public function __construct(private readonly User $user) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'user',
            'title'   => 'مستخدم جديد',
            'message' => "تم إنشاء حساب للموظف: {$this->user->display_name}",
            'url'     => '/users',
            'icon'    => 'user',
        ];
    }
}
