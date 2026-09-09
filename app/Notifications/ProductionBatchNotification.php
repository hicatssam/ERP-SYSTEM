<?php

namespace App\Notifications;

use App\Models\ProductionBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductionBatchNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ProductionBatch $batch,
        private readonly string $event
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->batch->loadMissing([
            'product',
            'location',
        ]);

        $title = match ($this->event) {
            'released' => 'دفعة إنتاج جاهزة للبدء',
            'awaiting_quality' => 'دفعة إنتاج بانتظار فحص الجودة',
            'completed' => 'تم إكمال دفعة إنتاج',
            'rejected' => 'تم رفض دفعة إنتاج في الجودة',
            default => 'تحديث على دفعة إنتاج',
        };

        return [
            'type' => 'production_batch',
            'event' => $this->event,
            'title' => $title,
            'message' =>
                ($this->batch->batch_number ?? '#'.$this->batch->id)
                . ' — '
                . ($this->batch->product?->name_ar
                    ?: $this->batch->product?->name
                    ?: 'منتج')
                . ' — '
                . ($this->batch->location?->name ?? '—'),
            'production_batch_id' => $this->batch->id,
            'location_id' => $this->batch->location_id,
            'url' => '/production/batches/'.$this->batch->id,
            'priority' => $this->event === 'awaiting_quality'
                ? 'high'
                : 'medium',
        ];
    }
}
