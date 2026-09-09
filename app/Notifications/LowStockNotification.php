<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    public function __construct(
        private int    $locationId,
        private int    $productId,
        private float  $currentQty,
        private float  $minimumQty,
        private string $productName,
        private string $locationName
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'low_stock',
            'title'         => 'تنبيه: مخزون منخفض — ' . $this->productName,
            'message'       => 'الكمية المتوفرة في ' . $this->locationName . ': '
                . number_format($this->currentQty, 2)
                . ' (الحد الأدنى: ' . number_format($this->minimumQty, 2) . ')',
            'location_id'   => $this->locationId,
            'location_name' => $this->locationName,
            'product_id'    => $this->productId,
            'product_name'  => $this->productName,
            'current_qty'   => $this->currentQty,
            'minimum_qty'   => $this->minimumQty,
            'url'           => '/inventory',
        ];
    }
}
