<?php

namespace App\Notifications;

use App\Models\DeliveryTask;
use Illuminate\Notifications\Notification;

class DeliveryTaskNotification extends Notification
{
    public function __construct(
        private readonly DeliveryTask $task,
        private readonly string $event,
        private readonly ?string $statusLabel = null,
    ) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        $orderNumber = $this->task->order?->order_number ?? '#'.$this->task->order_id;
        $title = $this->event === 'assigned' ? 'تم تعيين مهمة توصيل' : 'تحديث حالة توصيل';
        $message = $this->event === 'assigned'
            ? "تم تعيين توصيل الطلب {$orderNumber} لك."
            : "تغيّرت حالة توصيل الطلب {$orderNumber} إلى ".($this->statusLabel ?? $this->task->status->label()).'.';

        return [
            'type'=>'delivery_task','title'=>$title,'message'=>$message,'delivery_task_id'=>$this->task->id,
            'order_id'=>$this->task->order_id,'order_number'=>$orderNumber,'location_id'=>$this->task->location_id,
            'status'=>$this->task->status instanceof \BackedEnum ? $this->task->status->value : (string)$this->task->status,
            'url'=>'/delivery/tasks/'.$this->task->id,'priority'=>'medium',
        ];
    }
}
