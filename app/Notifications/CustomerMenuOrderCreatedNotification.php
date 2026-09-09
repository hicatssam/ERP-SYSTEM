<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class CustomerMenuOrderCreatedNotification extends Notification
{
    public function __construct(
        private readonly Order $order
    ) {
    }

    public function via(object $notifiable): array
    {
        /*
         * Database-only and synchronous.
         * The existing notification polling/header sound can surface it instantly.
         */
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->order->loadMissing([
            'customer',
            'location',
            'restaurantTable.area',
        ]);

        $serviceType = $this->order->restaurant_service_type instanceof \BackedEnum
            ? (string) $this->order->restaurant_service_type->value
            : (string) ($this->order->restaurant_service_type ?? '');

        $serviceLabel = match ($serviceType) {
            'dine_in' => 'داخل المطعم',
            'takeaway', 'take_away' => 'سفري',
            'delivery' => 'توصيل',
            default => 'طلب عميل',
        };

        $tableLabel = null;

        if ($this->order->restaurantTable) {
            $area = trim((string) ($this->order->restaurantTable->area?->name ?? ''));
            $table = trim((string) $this->order->restaurantTable->displayName());

            $tableLabel = collect([
                $area !== '' ? $area : null,
                $table !== '' ? $table : null,
            ])->filter()->implode(' — ');
        }

        $customerName = trim(
            (string) ($this->order->customer?->name ?? '')
        );

        $parts = [
            "طلب عميل جديد رقم {$this->order->order_number}",
            $customerName !== '' ? "العميل: {$customerName}" : null,
            "الخدمة: {$serviceLabel}",
            $tableLabel ? "الطاولة: {$tableLabel}" : null,
            'الإجمالي: ₪' . number_format(
                (float) $this->order->total_amount,
                2
            ),
        ];

        return [
            'fingerprint' => 'customer_menu_order_created_' . $this->order->id,
            'type' => 'customer_menu_order_created',
            'source' => 'customer_menu',

            'title' => '🔔 طلب عميل جديد',
            'message' => collect($parts)
                ->filter()
                ->implode(' • '),

            'order_id' => (int) $this->order->id,
            'order_number' => (string) $this->order->order_number,
            'location_id' => (int) $this->order->location_id,
            'location_name' => (string) ($this->order->location?->name ?? ''),

            'customer_id' => $this->order->customer_id
                ? (int) $this->order->customer_id
                : null,
            'customer_name' => $customerName ?: null,

            'service_type' => $serviceType ?: null,
            'service_label' => $serviceLabel,

            'restaurant_table_id' => $this->order->restaurant_table_id
                ? (int) $this->order->restaurant_table_id
                : null,
            'table_label' => $tableLabel,

            'total_amount' => (float) $this->order->total_amount,

            'url' => '/orders/' . $this->order->id,
            'priority' => 'high',
        ];
    }
}
