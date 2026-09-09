<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $statusLabels = [
            'confirmed'            => 'مؤكّد',
            'pending_verification' => 'انتظار التحقق',
            'rejected'             => 'مرفوض',
        ];

        $statusLabel = $statusLabels[$this->payment->status instanceof \BackedEnum
            ? $this->payment->status->value
            : (string) $this->payment->status] ?? (string) $this->payment->status;

        $needsVerification = str_contains(
            (string) ($this->payment->status instanceof \BackedEnum
                ? $this->payment->status->value
                : $this->payment->status),
            'pending'
        );

        return [
            'fingerprint'   => 'payment_received_' . $this->payment->id,
            'type'          => 'payment_received',
            'title'         => $needsVerification ? 'دفعة تحتاج تحققاً' : 'دفعة مستلمة',
            'message'       => "تم استلام دفعة بمبلغ {$this->payment->amount} — الحالة: {$statusLabel}",
            'payment_id'    => $this->payment->id,
            'location_id'   => $this->payment->location_id,
            'amount'        => $this->payment->amount,
            'status'        => $this->payment->status instanceof \BackedEnum
                                ? $this->payment->status->value
                                : (string) $this->payment->status,
            'order_type'    => $this->payment->order_type,
            'order_id'      => $this->payment->order_id,
            'url'           => '/invoices',
            'priority'      => $needsVerification ? 'high' : 'medium',
        ];
    }
}
