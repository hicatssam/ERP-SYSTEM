<?php

namespace App\Notifications;

use App\Models\SpecialCakeOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SpecialCakeOrderTransitionedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SpecialCakeOrder $order,
        private readonly string $fromStatus,
        private readonly string $toStatus,
        private readonly ?string $note = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
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

        $priority = in_array(
            $this->toStatus,
            ['rejected', 'cancelled', 'canceled', 'delayed', 'issue_open'],
            true
        ) ? 'high' : 'medium';

        $this->order->loadMissing([
            'originBranch:id,name',
            'factory:id,name',
        ]);

        return [
            'fingerprint' => "cake_transition_{$this->order->id}_{$this->fromStatus}_{$this->toStatus}",
            'type' => 'cake_order_transitioned',
            'title' => 'تحديث طلب كيك #' . $this->order->order_number,
            'message' => "انتقل طلب الكيك {$this->order->order_number} من [{$fromLabel}] إلى [{$toLabel}]",
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'origin_branch_id' => $this->order->origin_branch_id,
            'origin_branch_name' => $this->order->originBranch?->name,
            'factory_location_id' => $this->order->factory_location_id,
            'factory_name' => $this->order->factory?->name,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'note' => $this->note,
            'url' => '/cake-orders/' . $this->order->id,
            'priority' => $priority,
        ];
    }
}
