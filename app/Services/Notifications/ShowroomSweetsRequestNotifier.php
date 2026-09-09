<?php

namespace App\Services\Notifications;

use App\Models\ShowroomSweetsRequest;
use App\Models\User;
use App\Notifications\ShowroomSweetsRequestNotification;
use Illuminate\Support\Collection;

class ShowroomSweetsRequestNotifier
{
    /*
    |--------------------------------------------------------------------------
    | طلب جديد وصل للمصنع
    |--------------------------------------------------------------------------
    */

    public static function requestCreated(
        ShowroomSweetsRequest $request
    ): void {

        if (
            ! $request
                ->factory_location_id
        ) {
            return;
        }

        $request->loadMissing([
            'requestingLocation',
            'factoryLocation',
            'items.product',
        ]);

        /*
         * يستقبل الإشعار:
         *
         * - من عنده بدء تجهيز
         * - من عنده رفض الطلب
         * - أو صلاحية تحديث الحالة القديمة
         *
         * بشرط أن يكون في نفس المصنع.
         */

        $users = self::recipients(

            locationId:
                (int)
                $request
                    ->factory_location_id,

            permissions: [

                'showroom_sweets_requests.start',

                'showroom_sweets_requests.reject',

                /*
                 * للتوافق مع الأدوار القديمة
                 * مثل مدير المصنع إذا كان عنده
                 * update_status فقط.
                 */
                'showroom_sweets_requests.update_status',
            ],
        );

        self::send(

            $users,

            new ShowroomSweetsRequestNotification(

                showroomSweetsRequest:
                    $request,

                event:
                    'created',
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | تغيير حالة الطلب
    |--------------------------------------------------------------------------
    */

    public static function statusChanged(
        ShowroomSweetsRequest $request,
        string $fromStatus,
        string $toStatus,
        ?User $actor = null
    ): void {

        $request->loadMissing([
            'requestingLocation',
            'factoryLocation',
            'items.product',
        ]);


        /*
        |--------------------------------------------------------------------------
        | تحديد صاحب المهمة التالية
        |--------------------------------------------------------------------------
        */

        $users = match ($toStatus) {

            /*
             * بدأ التجهيز.
             *
             * المهمة التالية:
             * اعتماد أن الطلب جاهز.
             */

            'in_progress' =>
                self::recipients(

                    locationId:
                        (int)
                        $request
                            ->factory_location_id,

                    permissions: [

                        'showroom_sweets_requests.ready',

                        'showroom_sweets_requests.update_status',
                    ],
                ),


            /*
             * جاهز للتوصيل.
             *
             * المهمة التالية:
             * موظف التوصيل يرسل الطلب.
             */

            'ready_for_dispatch' =>
                self::recipients(

                    locationId:
                        (int)
                        $request
                            ->factory_location_id,

                    permissions: [

                        'showroom_sweets_requests.dispatch',

                        'showroom_sweets_requests.update_status',
                    ],
                ),


            /*
             * الطلب خرج من المصنع.
             *
             * المهمة التالية:
             * الفرع يؤكد الاستلام.
             */

            'out_for_delivery' =>
                self::recipients(

                    locationId:
                        (int)
                        $request
                            ->requesting_location_id,

                    permissions: [

                        'showroom_sweets_requests.receive',

                        'showroom_sweets_requests.update_status',
                    ],
                ),


            /*
             * الفرع استلم الطلب.
             *
             * نبلغ المصنع أن الدورة انتهت.
             */

            'received_at_branch',
            'fulfilled' =>
                self::recipients(

                    locationId:
                        (int)
                        $request
                            ->factory_location_id,

                    permissions: [

                        'showroom_sweets_requests.view',

                        'showroom_sweets_requests.ready',

                        'showroom_sweets_requests.dispatch',

                        'showroom_sweets_requests.update_status',
                    ],
                ),


            /*
             * المصنع رفض الطلب.
             *
             * يرجع الإشعار للفرع الطالب.
             */

            'rejected' =>
                self::recipients(

                    locationId:
                        (int)
                        $request
                            ->requesting_location_id,

                    permissions: [

                        'showroom_sweets_requests.view',

                        'showroom_sweets_requests.create',
                    ],
                ),


            /*
             * الفرع ألغى الطلب.
             *
             * نبلغ المصنع.
             */

            'cancelled' =>
                self::recipients(

                    locationId:
                        (int)
                        $request
                            ->factory_location_id,

                    permissions: [

                        'showroom_sweets_requests.start',

                        'showroom_sweets_requests.reject',

                        'showroom_sweets_requests.update_status',
                    ],
                ),


            default =>
                collect(),
        };


        /*
        |--------------------------------------------------------------------------
        | لا نرسل للشخص الذي نفذ العملية نفسها
        |--------------------------------------------------------------------------
        */

        if ($actor) {

            $users =
                $users
                    ->reject(
                        fn (User $user): bool =>
                            (int)
                            $user->id
                            ===
                            (int)
                            $actor->id
                    )
                    ->values();
        }


        if (
            $users->isEmpty()
        ) {
            return;
        }


        self::send(

            $users,

            new ShowroomSweetsRequestNotification(

                showroomSweetsRequest:
                    $request,

                event:
                    'status_changed',

                fromStatus:
                    $fromStatus,

                toStatus:
                    $toStatus,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | تحديد المستلمين
    |--------------------------------------------------------------------------
    |
    | القاعدة:
    |
    | 1. المستخدم فعال.
    |
    | 2. Admin يستقبل.
    |
    | 3. صاحب view_all يستقبل
    |    على مستوى جميع المواقع.
    |
    | 4. غير ذلك:
    |    لازم عنده صلاحية المهمة
    |    ومربوط بنفس الموقع.
    |
    */

    private static function recipients(
        int $locationId,
        array $permissions
    ): Collection {

        return User::query()

            ->where(
                'is_active',
                true
            )

            ->with([

                'roles',

                'permissions',

                'employee.locations',
            ])

            ->get()

            ->filter(

                function (
                    User $user
                ) use (
                    $locationId,
                    $permissions
                ): bool {


                    /*
                     * Admin
                     */

                    if (
                        $user->isAdmin()
                    ) {
                        return true;
                    }


                    /*
                     * مستخدم عالمي
                     */

                    if (
                        $user->can(
                            'showroom_sweets_requests.view_all'
                        )
                    ) {
                        return true;
                    }


                    /*
                     * هل عنده إحدى صلاحيات المهمة؟
                     */

                    $hasTaskPermission =
                        collect(
                            $permissions
                        )
                        ->contains(

                            fn (
                                string $permission
                            ): bool =>

                                $user->can(
                                    $permission
                                )
                        );


                    if (
                        ! $hasTaskPermission
                    ) {
                        return false;
                    }


                    /*
                     * يجب أن يكون المستخدم
                     * مرتبطًا بموقع.
                     */

                    if (
                        ! $user->employee
                    ) {
                        return false;
                    }


                    /*
                     * فحص الموقع.
                     */

                    return $user
                        ->employee
                        ->locations
                        ->contains(
                            'id',
                            $locationId
                        );
                }
            )

            ->unique('id')

            ->values();
    }


    /*
    |--------------------------------------------------------------------------
    | الإرسال
    |--------------------------------------------------------------------------
    */

    private static function send(
        Collection $users,
        ShowroomSweetsRequestNotification
        $notification
    ): void {

        foreach (
            $users
            as $user
        ) {

            $user->notify(
                clone $notification
            );
        }
    }
}