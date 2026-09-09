<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification
{
    public function __construct(private readonly User $user) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'user',
            'title'   => 'إعادة تعيين كلمة المرور',
            'message' => "تم إعادة تعيين كلمة مرور المستخدم: {$this->user->display_name}",
            'url'     => '/users',
            'icon'    => 'user',
        ];
    }
}
