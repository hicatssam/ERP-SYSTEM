<?php

namespace App\Notifications;

use App\Models\StockRequest;
use Illuminate\Notifications\Notification;

class StockRequestCreatedNotification extends Notification
{
    public function __construct(private readonly StockRequest $stockRequest) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'stock_request',
            'title'   => 'طلب مخزون جديد',
            'message' => "تم إنشاء طلب مخزون رقم {$this->stockRequest->request_number}",
            'url'     => '/stock-requests/' . $this->stockRequest->id,
            'icon'    => 'inventory',
        ];
    }
}
