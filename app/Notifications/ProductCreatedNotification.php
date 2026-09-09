<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Notification;

class ProductCreatedNotification extends Notification
{
    public function __construct(private readonly Product $product) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'product',
            'title'   => 'منتج جديد',
            'message' => "تم إضافة المنتج: {$this->product->name}",
            'url'     => '/products/' . $this->product->id,
            'icon'    => 'product',
        ];
    }
}
