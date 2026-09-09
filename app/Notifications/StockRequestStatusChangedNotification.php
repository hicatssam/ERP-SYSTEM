<?php

namespace App\Notifications;

use App\Models\StockRequest;
use App\Support\ArabicDisplay;
use Illuminate\Notifications\Notification;

class StockRequestStatusChangedNotification extends Notification
{
    public function __construct(
        private readonly StockRequest $stockRequest,
        private readonly string $newStatus,
    ) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        $label = ArabicDisplay::status($this->newStatus);
        return [
            'type'    => 'stock_request',
            'title'   => "طلب مخزون — {$label}",
            'message' => "طلب المخزون {$this->stockRequest->request_number} — {$label}",
            'url'     => '/stock-requests/' . $this->stockRequest->id,
            'icon'    => 'inventory',
        ];
    }
}
