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
                '، وهو الآن قيد المراجعة.';


            return [

                'fingerprint' =>
                    'showroom_sweets_created_'
                    .
                    $request->id,


                'type' =>
                    'showroom_sweets_request_created',


                'title' =>
                    'طلب حلويات فرع جديد قيد المراجعة',


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
            'in_progress' => [
                'طلب حلويات فرع قيد التنفيذ',
                "بدأ تنفيذ الطلب {$request->request_number} الخاص بـ {$branchName}.",
            ],

            'ready' => [
                'طلب حلويات فرع جاهز للاستلام',
                "الطلب {$request->request_number} أصبح جاهزًا لاستلام {$branchName}.",
            ],

            'completed' => [
                'اكتمل طلب حلويات الفرع',
                "تم استلام وإكمال الطلب {$request->request_number} الخاص بـ {$branchName}.",
            ],

            'cancelled' => [
                'تم إلغاء طلب حلويات الفرع',
                "تم إلغاء الطلب {$request->request_number} الخاص بـ {$branchName}.",
            ],

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