<?php

namespace App\Notifications;

use App\Enums\ShowroomCakeRequestStatus;
use App\Models\ShowroomCakeRequest;
use Illuminate\Notifications\Notification;

class ShowroomCakeRequestNotification extends Notification
{
    public function __construct(
        private readonly ShowroomCakeRequest $request,
        private readonly string $event,
        private readonly ?string $fromStatus = null,
        private readonly ?string $toStatus = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->request->loadMissing([
            'requestingLocation',
            'factoryLocation',
            'items',
        ]);

        $branch =
            $this->request->requestingLocation?->name
            ?? 'الفرع';

        $factory =
            $this->request->factoryLocation?->name
            ?? 'المصنع';

        if ($this->event === 'created') {
            return [
                'fingerprint' =>
                    'showroom_cake_created_'
                    . $this->request->id,

                'type' =>
                    'showroom_cake_request_created',

                'title' =>
                    'طلب كيك فرع جديد قيد المراجعة',

                'message' =>
                    "وصل طلب كيك الفروع {$this->request->request_number} "
                    . "من {$branch} إلى {$factory} وهو الآن قيد المراجعة.",

                'showroom_cake_request_id' =>
                    $this->request->id,

                'request_number' =>
                    $this->request->request_number,

                'requesting_location_id' =>
                    $this->request->requesting_location_id,

                'factory_location_id' =>
                    $this->request->factory_location_id,

                'status' => 'pending',

                'url' =>
                    '/showroom-cake-requests/'
                    . $this->request->id,

                'priority' => 'medium',
            ];
        }

        $fromLabel =
            $this->statusLabel(
                $this->fromStatus
            );

        $toLabel =
            $this->statusLabel(
                $this->toStatus
            );

        [$title, $message] =
            match ($this->toStatus) {
                'in_progress' => [
                    'طلب كيك فرع قيد التنفيذ',
                    "بدأ تنفيذ الطلب {$this->request->request_number} الخاص بـ {$branch}.",
                ],

                'ready' => [
                    'طلب كيك فرع جاهز للاستلام',
                    "الطلب {$this->request->request_number} أصبح جاهزًا لاستلام {$branch}.",
                ],

                'completed' => [
                    'اكتمل طلب كيك الفرع',
                    "تم استلام وإكمال الطلب {$this->request->request_number} الخاص بـ {$branch}.",
                ],

                'cancelled' => [
                    'تم إلغاء طلب كيك الفرع',
                    "تم إلغاء الطلب {$this->request->request_number} الخاص بـ {$branch}.",
                ],

                default => [
                    'تحديث طلب كيك فرع',
                    "تغيرت حالة الطلب {$this->request->request_number} "
                    . "من [{$fromLabel}] إلى [{$toLabel}].",
                ],
            };

        return [
            'fingerprint' =>
                "showroom_cake_status_{$this->request->id}_{$this->fromStatus}_{$this->toStatus}",

            'type' =>
                'showroom_cake_request_status_changed',

            'title' => $title,
            'message' => $message,

            'showroom_cake_request_id' =>
                $this->request->id,

            'request_number' =>
                $this->request->request_number,

            'requesting_location_id' =>
                $this->request->requesting_location_id,

            'factory_location_id' =>
                $this->request->factory_location_id,

            'from_status' =>
                $this->fromStatus,

            'to_status' =>
                $this->toStatus,

            'url' =>
                '/showroom-cake-requests/'
                . $this->request->id,

            'priority' =>
                $this->toStatus === 'cancelled'
                    ? 'high'
                    : 'medium',
        ];
    }

    private function statusLabel(?string $status): string
    {
        if (! $status) {
            return 'غير محدد';
        }

        return ShowroomCakeRequestStatus::tryFrom(
            $status
        )?->label() ?? $status;
    }
}
