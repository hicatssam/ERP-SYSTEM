<?php

namespace App\Notifications;

use App\Models\SalesChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SalesChannelChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SalesChannel $channel,
        public string $event,
        public ?int $actorId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        [$title, $message] = match ($this->event) {
            'created' => ['قناة بيع جديدة', "تم إنشاء قناة البيع {$this->channel->name}."],
            'enabled' => ['تفعيل قناة بيع', "تم تفعيل قناة البيع {$this->channel->name}."],
            'disabled' => ['تعطيل قناة بيع', "تم تعطيل قناة البيع {$this->channel->name}."],
            'commercial_terms_changed' => ['تغيير شروط قناة بيع', "تم تعديل الخصم أو العمولة لقناة {$this->channel->name}."],
            default => ['تحديث قناة بيع', "تم تحديث قناة البيع {$this->channel->name}."],
        };
        
        return [
            'type' => 'sales_channel',
            'title' => $title,
            'message' => $message,
            'sales_channel_id' => $this->channel->id,
            'actor_id' => $this->actorId,
            'url' => route('settings.sales-channels.show', $this->channel),
        ];
    }
}
