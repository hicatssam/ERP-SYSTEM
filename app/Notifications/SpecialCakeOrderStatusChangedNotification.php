<?php

namespace App\Notifications;

use App\Models\SpecialCakeOrder;
use Illuminate\Notifications\Notification;

class SpecialCakeOrderStatusChangedNotification extends Notification
{
    public function __construct(
        private SpecialCakeOrder $order,
        private string $fromStatus,
        private string $toStatus
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $statusLabels = [
            'draft' => 'مسودة',
            'pending_deposit' => 'بانتظار العربون',
            'deposit_paid' => 'تم دفع العربون',
            'pending_factory_review' => 'بانتظار مراجعة المصنع',
            'accepted' => 'مقبول',
            'rejected' => 'مرفوض',
            'modification_requested' => 'طُلب تعديل',
            'scheduled' => 'مجدول',
            'in_preparation' => 'قيد التحضير',
            'decorating' => 'قيد التزيين',
            'in_decoration' => 'قيد التزيين',
            'quality_check' => 'فحص الجودة',
            'ready' => 'جاهز في المصنع',
            'sent_to_branch' => 'تم الإرسال إلى الفرع',
            'dispatched_to_branch' => 'تم الإرسال إلى الفرع',
            'received_by_branch' => 'تم الاستلام في الفرع',
            'received_at_branch' => 'تم الاستلام في الفرع',
            'ready_for_customer' => 'جاهز لاستلام العميل',
            'ready_for_pickup' => 'جاهز للاستلام',
            'delivered' => 'تم التسليم',
            'completed' => 'مكتمل',
            'delayed' => 'متأخر',
            'issue_open' => 'توجد ملاحظة عند الاستلام',
            'cancelled' => 'ملغى',
            'canceled' => 'ملغى',
        ];

        $fromLabel = $statusLabels[$this->fromStatus] ?? 'غير محدد';
        $toLabel = $statusLabels[$this->toStatus] ?? 'غير محدد';

        return [
            'fingerprint' => "cake_transition_{$this->order->id}_{$this->fromStatus}_{$this->toStatus}",
            'type' => 'cake_order_status_changed',
            'title' => 'تحديث طلب كيك #' . $this->order->order_number,
            'message' => 'تغيرت الحالة من: ' . $fromLabel . ' إلى: ' . $toLabel,
            'cake_order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'origin_branch_id' => $this->order->origin_branch_id,
            'factory_id' => $this->order->factory_location_id,
            'url' => '/cake-orders/' . $this->order->id,
            'priority' => in_array(
                $this->toStatus,
                ['rejected', 'cancelled', 'canceled', 'delayed', 'issue_open'],
                true
            ) ? 'high' : 'medium',
        ];
    }
}