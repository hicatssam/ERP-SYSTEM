<?php

namespace App\Services\Restaurant;

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentArrangement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;

class CustomerOrderStatusService
{
    public function payload(Order $order): array
    {
        /*
         * Always read fresh order/payment/kitchen state on every poll so the
         * public tracking page immediately reflects cashier and KDS changes.
         */
        $order->refresh();

        $state = $this->resolveState($order);
        $payment = $this->paymentPayload($order);

        $message = trim((string) ($order->customer_status_message ?? ''));
        $messageUpdatedAt = $order->customer_status_message_updated_at?->toIso8601String();

        $statusKey = $state['state'];

        $label = trim((string) SystemSetting::get(
            "customer_menu_status_{$statusKey}_title",
            $state['default_label']
        )) ?: $state['default_label'];

        $description = trim((string) SystemSetting::get(
            "customer_menu_status_{$statusKey}_message",
            $state['default_description']
        )) ?: $state['default_description'];

        $serviceType = $this->scalar($order->restaurant_service_type);

        $table = $order->restaurantTable()
            ->with('area:id,name')
            ->first();

        $items = $order->items()
            ->orderBy('id')
            ->get(['id', 'product_name', 'quantity', 'line_total'])
            ->map(fn ($item): array => [
                'name' => (string) $item->product_name,
                'quantity' => (float) $item->quantity,
                'line_total' => (float) $item->line_total,
            ])
            ->values()
            ->all();

        $invoice = $order->invoice()->first();

        $fingerprint = sha1(json_encode([
            'order_status' => $order->statusValue(),
            'state' => $statusKey,
            'event_at' => $state['event_at'],
            'updated_at' => $order->updated_at?->toIso8601String(),
            'payment_status' => $payment['status'],
            'paid_amount' => $payment['paid_amount'],
            'remaining_amount' => $payment['remaining_amount'],
            'pending_amount' => $payment['pending_amount'],
            'invoice_id' => $invoice?->id,
            'message' => $message,
            'message_updated_at' => $messageUpdatedAt,
        ], JSON_UNESCAPED_UNICODE));

        return [
            'order_number' => (string) $order->order_number,
            'status' => $order->statusValue(),
            'state' => $statusKey,
            'kitchen_status' => $state['kitchen_status'],
            'label' => $label,
            'description' => $description,
            'step' => $state['step'],
            'total' => (float) $order->total_amount,
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'event_at' => $state['event_at'],
            'fingerprint' => $fingerprint,

            'service_type' => $serviceType,
            'service_label' => match ($serviceType) {
                'dine_in' => 'داخل المطعم',
                'takeaway', 'take_away' => 'سفري',
                'outdoor' => 'استلام من الباب',
                'delivery' => 'توصيل',
                default => 'طلب مطعم',
            },

            'estimated_ready_minutes' => $this->estimatedReadyMinutes($order, $statusKey),

            'payment_status' => $payment['status'],
            'payment_arrangement' => $payment['arrangement'],
            'payment' => $payment,

            'invoice' => $invoice ? [
                'id' => (int) $invoice->id,
                'number' => (string) $invoice->invoice_number,
                'status' => $this->scalar($invoice->status),
                'total' => (float) $invoice->total_amount,
                'paid' => (float) $invoice->paid_amount,
                'remaining' => (float) $invoice->remaining_amount,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
                'url' => $order->public_token
                    ? route('customer-menu.invoice', ['token' => $order->public_token])
                    : null,
            ] : null,

            'table' => $table
                ? [
                    'id' => (int) $table->id,
                    'code' => (string) $table->code,
                    'name' => (string) $table->displayName(),
                    'area' => (string) ($table->area?->name ?? ''),
                ]
                : null,

            'location' => $order->location
                ? [
                    'id' => (int) $order->location->id,
                    'name' => (string) $order->location->name,
                    'code' => (string) ($order->location->code ?? ''),
                ]
                : null,

            'items' => $items,
            'item_count' => count($items),

            'toast_enabled' => (bool) SystemSetting::get(
                'customer_menu_status_toast_enabled',
                true
            ),
            'sound_enabled' => (bool) SystemSetting::get(
                'customer_menu_status_sound_enabled',
                true
            ),
            'toast_title' => $label,
            'toast_message' => $description,

            'customer_message' => $message !== '' ? $message : null,
            'customer_message_updated_at' => $messageUpdatedAt,
            'customer_message_signature' => $message !== ''
                ? sha1($message . '|' . ($messageUpdatedAt ?? ''))
                : null,

            'track_url' => $order->public_token
                ? route('customer-menu.track', ['token' => $order->public_token])
                : null,
        ];
    }

    public function resolveState(Order $order): array
    {
        $orderStatus = $order->statusValue();

        if ($orderStatus === 'cancelled') {
            return [
                'state' => 'cancelled',
                'step' => 0,
                'kitchen_status' => 'cancelled',
                'event_at' => $order->cancelled_at?->toIso8601String(),
                'default_label' => 'تم إلغاء الطلب',
                'default_description' => 'تم إلغاء الطلب. تواصل مع الفرع إذا احتجت للمساعدة.',
            ];
        }

        $tickets = $order->kitchenTickets()
            ->orderBy('id')
            ->get();

        $ticketStatuses = $tickets
            ->map(fn ($ticket): string => $ticket->statusValue())
            ->values();

        if ($orderStatus === 'completed') {
            return [
                'state' => 'completed',
                'step' => 4,
                'kitchen_status' => 'served',
                'event_at' => $order->completed_at?->toIso8601String(),
                'default_label' => 'تم تسليم الطلب',
                'default_description' => 'تم تسليم طلبك. شكراً لاختيارك لنا.',
            ];
        }

        if ($ticketStatuses->isNotEmpty()) {
            $nonCancelled = $ticketStatuses
                ->reject(fn (string $status): bool => $status === 'cancelled')
                ->values();

            if (
                $nonCancelled->isNotEmpty()
                && $nonCancelled->every(fn (string $status): bool => $status === 'served')
            ) {
                return [
                    'state' => 'completed',
                    'step' => 4,
                    'kitchen_status' => 'served',
                    'event_at' => $this->latestTicketTime($tickets, 'served_at'),
                    'default_label' => 'تم تسليم الطلب',
                    'default_description' => 'تم تسليم طلبك. شكراً لاختيارك لنا.',
                ];
            }

            $remaining = $nonCancelled
                ->reject(fn (string $status): bool => $status === 'served')
                ->values();

            if (
                $remaining->isNotEmpty()
                && $remaining->every(fn (string $status): bool => $status === 'ready')
            ) {
                return [
                    'state' => 'ready',
                    'step' => 4,
                    'kitchen_status' => 'ready',
                    'event_at' => $this->latestTicketTime($tickets, 'ready_at'),
                    'default_label' => 'طلبك جاهز 🎉',
                    'default_description' => 'طلبك جاهز الآن للاستلام. نتمنى لك وجبة شهية!',
                ];
            }

            if (
                $ticketStatuses->contains('preparing')
                || $ticketStatuses->contains('ready')
                || $ticketStatuses->contains('served')
            ) {
                return [
                    'state' => 'preparing',
                    'step' => 3,
                    'kitchen_status' => 'preparing',
                    'event_at' => $this->latestTicketTime($tickets, 'started_at')
                        ?? $this->latestTicketTime($tickets, 'queued_at'),
                    'default_label' => 'طلبك قيد التحضير',
                    'default_description' => 'فريقنا يجهز طلبك الآن.',
                ];
            }

            if ($ticketStatuses->contains('queued')) {
                return [
                    'state' => 'accepted',
                    'step' => 2,
                    'kitchen_status' => 'queued',
                    'event_at' => $this->latestTicketTime($tickets, 'queued_at'),
                    'default_label' => 'تم قبول الطلب',
                    'default_description' => 'تم قبول طلبك وسيدخل التحضير الآن.',
                ];
            }
        }

        if ($orderStatus === 'confirmed') {
            return [
                'state' => 'accepted',
                'step' => 2,
                'kitchen_status' => null,
                'event_at' => $order->confirmed_at?->toIso8601String(),
                'default_label' => 'تم قبول الطلب',
                'default_description' => 'تم قبول طلبك وسيدخل التحضير الآن.',
            ];
        }

        return [
            'state' => 'received',
            'step' => 1,
            'kitchen_status' => null,
            'event_at' => $order->created_at?->toIso8601String(),
            'default_label' => 'تم استلام طلبك',
            'default_description' => 'وصل طلبك للفرع وهو بانتظار القبول.',
        ];
    }

    private function paymentPayload(Order $order): array
    {
        $payments = $order->payments()
            ->with(['paymentMethod', 'refunds'])
            ->orderByDesc('id')
            ->get();

        $paid = round($payments
            ->filter(fn (Payment $payment): bool => in_array(
                $payment->statusValue(),
                ['confirmed', 'corrected', 'refunded'],
                true
            ))
            ->sum(function (Payment $payment): float {
                $refunded = (float) $payment->refunds->sum('amount');
                return max(0, (float) $payment->amount - $refunded);
            }), 2);

        $pending = round($payments
            ->filter(fn (Payment $payment): bool => $payment->statusValue() === 'pending_verification')
            ->sum(fn (Payment $payment): float => (float) $payment->amount), 2);

        $total = round((float) $order->total_amount, 2);
        $remaining = round(max(0, $total - $paid), 2);

        $status = $this->scalar($order->payment_status) ?: OrderPaymentStatus::PaymentPending->value;
        $arrangement = $this->scalar($order->payment_arrangement);
        $latest = $payments->first();

        [$label, $message] = match ($status) {
            OrderPaymentStatus::Paid->value => [
                'تم الدفع بالكامل',
                'تم اعتماد دفعتك بالكامل.',
            ],
            OrderPaymentStatus::PartiallyPaid->value => [
                'مدفوع جزئياً',
                'تم اعتماد جزء من المبلغ، والمتبقي ' . number_format($remaining, 2) . ' ₪.',
            ],
            OrderPaymentStatus::PendingPaymentVerification->value => [
                'بانتظار التحقق من الدفع',
                'تم استلام إثبات الدفع وهو قيد المراجعة.',
            ],
            OrderPaymentStatus::PartiallyRefunded->value => [
                'تم استرجاع جزء من المبلغ',
                'تم تنفيذ استرداد جزئي على هذا الطلب.',
            ],
            OrderPaymentStatus::Refunded->value => [
                'تم استرجاع المبلغ',
                'تم استرجاع المبلغ المدفوع لهذا الطلب.',
            ],
            default => $arrangement === PaymentArrangement::PayOnPickup->value
                ? ['الدفع عند الاستلام', 'لم يتم تحصيل المبلغ بعد. سيتم الدفع عند الاستلام.']
                : ['في انتظار الدفع', 'لم يتم اعتماد أي دفعة على هذا الطلب بعد.'],
        };

        if ($latest?->statusValue() === 'rejected') {
            $label = 'تم رفض عملية الدفع';
            $message = filled($latest->rejection_reason)
                ? 'سبب الرفض: ' . trim((string) $latest->rejection_reason)
                : 'تعذر اعتماد عملية الدفع. راجع بيانات التحويل أو تواصل مع الفرع.';
        }

        return [
            'status' => $status,
            'arrangement' => $arrangement,
            'label' => $label,
            'message' => $message,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'pending_amount' => $pending,
            'remaining_amount' => $remaining,
            'latest_payment' => $latest ? [
                'id' => (int) $latest->id,
                'status' => $latest->statusValue(),
                'amount' => (float) $latest->amount,
                'method' => $latest->paymentMethod
                    ? (string) ($latest->paymentMethod->name_ar ?: $latest->paymentMethod->name)
                    : null,
                'reference' => $latest->reference_number,
                'rejection_reason' => $latest->rejection_reason,
                'paid_at' => $latest->paid_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function estimatedReadyMinutes(Order $order, string $statusKey): ?int
    {
        if (in_array($statusKey, ['ready', 'completed', 'cancelled'], true)) {
            return null;
        }

        $defaultPrepMinutes = max(1, (int) SystemSetting::get('customer_menu_default_prep_minutes', 12));
        $queueMinutesPerOrder = max(0, (int) SystemSetting::get('customer_menu_queue_minutes_per_order', 4));

        $basePrepMinutes = $order->items()
            ->with('product:id,prep_time_minutes')
            ->get()
            ->pluck('product.prep_time_minutes')
            ->filter()
            ->max();

        $basePrepMinutes = $basePrepMinutes !== null ? (int) $basePrepMinutes : $defaultPrepMinutes;

        $ordersAhead = $order->location_id
            ? Order::query()
                ->where('location_id', $order->location_id)
                ->where('id', '!=', $order->id)
                ->whereIn('status', ['draft', 'confirmed'])
                ->where('created_at', '<', $order->created_at)
                ->count()
            : 0;

        return $basePrepMinutes + ($ordersAhead * $queueMinutesPerOrder);
    }

    private function latestTicketTime(Collection $tickets, string $field): ?string
    {
        $time = $tickets
            ->pluck($field)
            ->filter()
            ->sortDesc()
            ->first();

        return $time?->toIso8601String();
    }

    private function scalar(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) ($value ?? '');
    }
}
