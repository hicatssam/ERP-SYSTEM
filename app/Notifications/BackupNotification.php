<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class BackupNotification extends Notification
{
    public function __construct(
        private readonly bool $success,
        private readonly string $detail = '',
    ) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'system',
            'title'   => $this->success ? 'نسخ احتياطي ناجح' : 'فشل النسخ الاحتياطي',
            'message' => $this->success
                ? 'تمت عملية النسخ الاحتياطي بنجاح' . ($this->detail ? " — {$this->detail}" : '')
                : 'فشلت عملية النسخ الاحتياطي' . ($this->detail ? ": {$this->detail}" : ''),
            'url'     => '/settings',
            'icon'    => 'system',
        ];
    }
}
