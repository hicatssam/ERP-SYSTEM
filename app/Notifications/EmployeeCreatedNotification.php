<?php

namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Notifications\Notification;

class EmployeeCreatedNotification extends Notification
{
    public function __construct(private readonly Employee $employee) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'user',
            'title'   => 'موظف جديد',
            'message' => "تم إضافة الموظف: {$this->employee->full_name}",
            'url'     => '/employees/' . $this->employee->id,
            'icon'    => 'user',
        ];
    }
}
