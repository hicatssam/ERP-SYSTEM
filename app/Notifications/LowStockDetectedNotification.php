<?php

namespace App\Notifications;

use App\Models\Inventory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LowStockDetectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Inventory $inventory,
        private readonly float     $currentQuantity,
        private readonly float     $minimumLevel,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $productName  = $this->inventory->product?->name ?? "منتج #{$this->inventory->product_id}";
        $locationName = $this->inventory->location?->name ?? "فرع #{$this->inventory->location_id}";

        return [
            'fingerprint'     => "low_stock_{$this->inventory->location_id}_{$this->inventory->product_id}",
            'type'            => 'low_stock_detected',
            'title'           => 'تحذير: مخزون منخفض',
            'message'         => "مخزون [{$productName}] في [{$locationName}] وصل إلى {$this->currentQuantity} (الحد الأدنى: {$this->minimumLevel})",
            'inventory_id'    => $this->inventory->id,
            'location_id'     => $this->inventory->location_id,
            'product_id'      => $this->inventory->product_id,
            'current_quantity'=> $this->currentQuantity,
            'minimum_level'   => $this->minimumLevel,
            'url'             => '/inventory',
            'priority'        => 'high',
        ];
    }
}
