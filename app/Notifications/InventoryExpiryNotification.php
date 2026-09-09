<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InventoryExpiryNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly array $payload,
        private readonly string $channel = 'database',
    ) {
    }

    public function via(object $notifiable): array
    {
        return [$this->channel];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('تنبيه صلاحية مخزون — ' . ($this->payload['product_name'] ?? 'منتج'))
            ->greeting('تنبيه صلاحية مخزون')
            ->line('المنتج: ' . ($this->payload['product_name'] ?? '—'))
            ->line('التشغيلة: ' . ($this->payload['batch_number'] ?? '—'))
            ->line('الموقع: ' . ($this->payload['location_name'] ?? '—'))
            ->line('الكمية المتبقية: ' . ($this->payload['available_quantity'] ?? '0'))
            ->line('تاريخ الانتهاء: ' . ($this->payload['expiry_date'] ?? '—'))
            ->line('الحالة: ' . ($this->payload['status_label'] ?? '—'));

        if (! empty($this->payload['url'])) {
            $mail->action('فتح لوحة الصلاحية', url((string) $this->payload['url']));
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
