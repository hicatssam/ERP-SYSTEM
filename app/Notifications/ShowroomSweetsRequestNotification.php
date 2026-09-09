<?php

namespace App\Notifications;

use App\Enums\ShowroomSweetsRequestStatus;
use App\Models\ShowroomSweetsRequest;
use Illuminate\Notifications\Notification;

class ShowroomSweetsRequestNotification
    extends Notification
{
    public function __construct(

        private readonly
        ShowroomSweetsRequest
        $showroomSweetsRequest,

        private readonly
        string
        $event,

        private readonly
        ?string
        $fromStatus = null,

        private readonly
        ?string
        $toStatus = null,

    ) {}


    /*
    |--------------------------------------------------------------------------
    | قناة الإشعار
    |--------------------------------------------------------------------------
    */

    public function via(
        object $notifiable
    ): array {

        return [
            'database',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | بيانات الإشعار
    |--------------------------------------------------------------------------
    */

    public function toDatabase(
        object $notifiable
    ): array {

        $request =
            $this
                ->showroomSweetsRequest;


        $branchName =
            $request
                ->requestingLocation
                ?->name
            ??
            'الفرع';


        $factoryName =
            $request
                ->factoryLocation
                ?->name
            ??
            'المصنع';


        $itemsCount =
            $request
                ->items
                ?->count()
            ??
            0;


        $neededBy =
            $request
                ->needed_by
                ?->format(
                    'Y-m-d'
                );


        /*
        |--------------------------------------------------------------------------
        | طلب جديد
        |--------------------------------------------------------------------------
        */

        if (
            $this->event
            ===
            'created'
        ) {

            $message =
                "وصل طلب حلويات جديد رقم {$request->request_number} "
                .
                "من {$branchName} "
                .
                "إلى {$factoryName} "
                .
                "ويحتوي على {$itemsCount} صنف";


            if (
                $neededBy
            ) {

                $message .=
                    "، مطلوب بتاريخ {$neededBy}";
            }


            $message .=
                '، وهو بانتظار بدء التجهيز أو اتخاذ القرار.';


            return [

                'fingerprint' =>
                    'showroom_sweets_created_'
                    .
                    $request->id,


                'type' =>
                    'showroom_sweets_request_created',


                'title' =>
                    'طلب حلويات جديد بانتظار إجراء المصنع',


                'message' =>
                    $message,


                'showroom_sweets_request_id' =>
                    $request->id,


                'request_number' =>
                    $request
                        ->request_number,


                'requesting_location_id' =>
                    $request
                        ->requesting_location_id,


                'factory_location_id' =>
                    $request
                        ->factory_location_id,


                'status' =>
                    $request->status
                    instanceof
                    ShowroomSweetsRequestStatus

                        ? $request
                            ->status
                            ->value

                        : (string)
                            $request
                                ->status,


                'url' =>
                    '/showroom-sweets-requests/'
                    .
                    $request->id,


                'priority' =>
                    'high',
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | أسماء الحالات
        |--------------------------------------------------------------------------
        */

        $fromLabel =
            $this->statusLabel(
                $this->fromStatus
            );


        $toLabel =
            $this->statusLabel(
                $this->toStatus
            );


        /*
        |--------------------------------------------------------------------------
        | نص الإشعار حسب المرحلة
        |--------------------------------------------------------------------------
        */

        [
            $title,
            $message
        ] = match (
            $this->toStatus
        ) {


            /*
             * بدأ التجهيز
             */

            'in_progress' => [

                'طلب حلويات قيد التجهيز',

                "تم بدء تجهيز الطلب {$request->request_number}، وهو الآن بانتظار اعتماد الجاهزية.",
            ],


            /*
             * جاهز للتوصيل
             */

            'ready_for_dispatch' => [

                'طلب حلويات جاهز للتوصيل',

                "الطلب {$request->request_number} أصبح جاهزًا وبانتظار موظف التوصيل لإرساله إلى {$branchName}.",
            ],


            /*
             * خرج للتوصيل
             */

            'out_for_delivery' => [

                'طلب حلويات في الطريق إلى الفرع',

                "تم إرسال الطلب {$request->request_number} من {$factoryName} وهو بانتظار استلام {$branchName}.",
            ],


            /*
             * استلمه الفرع
             */

            'received_at_branch',
            'fulfilled' => [

                'تم استلام طلب الحلويات في الفرع',

                "أكد {$branchName} استلام الطلب {$request->request_number} بنجاح.",
            ],


            /*
             * رفض
             */

            'rejected' => [

                'تم رفض طلب حلويات الفرع',

                "تم رفض الطلب {$request->request_number} الخاص بـ {$branchName}.",
            ],


            /*
             * إلغاء
             */

            'cancelled' => [

                'تم إلغاء طلب حلويات الفرع',

                "تم إلغاء الطلب {$request->request_number} الخاص بـ {$branchName}.",
            ],


            /*
             * أي حالة أخرى
             */

            default => [

                'تحديث حالة طلب حلويات فرع',

                "تغيرت حالة الطلب {$request->request_number} من [{$fromLabel}] إلى [{$toLabel}].",
            ],
        };


        /*
        |--------------------------------------------------------------------------
        | البيانات النهائية
        |--------------------------------------------------------------------------
        */

        return [

            'fingerprint' =>
                "showroom_sweets_status_{$request->id}_{$this->fromStatus}_{$this->toStatus}",


            'type' =>
                'showroom_sweets_request_status_changed',


            'title' =>
                $title,


            'message' =>
                $message,


            'showroom_sweets_request_id' =>
                $request->id,


            'request_number' =>
                $request
                    ->request_number,


            'requesting_location_id' =>
                $request
                    ->requesting_location_id,


            'factory_location_id' =>
                $request
                    ->factory_location_id,


            'from_status' =>
                $this->fromStatus,


            'to_status' =>
                $this->toStatus,


            'url' =>
                '/showroom-sweets-requests/'
                .
                $request->id,


            'priority' =>

                in_array(
                    $this->toStatus,
                    [
                        'rejected',
                        'cancelled',
                    ],
                    true
                )

                    ? 'high'

                    : 'medium',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | اسم الحالة بالعربي
    |--------------------------------------------------------------------------
    */

    private function statusLabel(
        ?string $status
    ): string {

        if (
            ! $status
        ) {

            return
                'غير محدد';
        }


        return
            ShowroomSweetsRequestStatus
                ::tryFrom(
                    $status
                )
                ?->label()
            ??
            $status;
    }
}