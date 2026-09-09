<?php

namespace App\Enums;

enum ShowroomSweetsRequestStatus: string
{
    /*
    |--------------------------------------------------------------------------
    | حالات طلب حلويات الفروع
    |--------------------------------------------------------------------------
    */

    case Submitted = 'submitted';

    case InProgress = 'in_progress';

    case ReadyForDispatch = 'ready_for_dispatch';

    case OutForDelivery = 'out_for_delivery';

    case ReceivedAtBranch = 'received_at_branch';

    /*
     * نبقيها لدعم الطلبات القديمة الموجودة في قاعدة البيانات.
     */
    case Fulfilled = 'fulfilled';

    case Rejected = 'rejected';

    case Cancelled = 'cancelled';


    /*
    |--------------------------------------------------------------------------
    | الاسم العربي
    |--------------------------------------------------------------------------
    */

    public function label(): string
    {
        return match ($this) {

            self::Submitted =>
                'مُرسل للمصنع',

            self::InProgress =>
                'قيد التجهيز',

            self::ReadyForDispatch =>
                'جاهز للتوصيل',

            self::OutForDelivery =>
                'في الطريق إلى الفرع',

            self::ReceivedAtBranch =>
                'تم الاستلام في الفرع',

            self::Fulfilled =>
                'تم التنفيذ',

            self::Rejected =>
                'مرفوض',

            self::Cancelled =>
                'ملغى',
        };
    }


    /*
    |--------------------------------------------------------------------------
    | تنسيق الحالة
    |--------------------------------------------------------------------------
    */

    public function badgeClass(): string
    {
        return match ($this) {

            self::Submitted =>
                'badge-pending',

            self::InProgress =>
                'badge-warning',

            self::ReadyForDispatch =>
                'badge-info',

            self::OutForDelivery =>
                'badge-warning',

            self::ReceivedAtBranch,
            self::Fulfilled =>
                'badge-active',

            self::Rejected =>
                'badge-inactive',

            self::Cancelled =>
                'badge-inactive',
        };
    }


    /*
    |--------------------------------------------------------------------------
    | الانتقالات المسموحة
    |--------------------------------------------------------------------------
    |
    | دورة العملية:
    |
    | مُرسل للمصنع
    |       ↓
    | قيد التجهيز
    |       ↓
    | جاهز للتوصيل
    |       ↓
    | في الطريق إلى الفرع
    |       ↓
    | تم الاستلام في الفرع
    |
    */

    public function allowedTransitions(): array
    {
        return match ($this) {

            /*
             * الطلب وصل حديثًا للمصنع.
             *
             * المصنع يستطيع:
             * - بدء التجهيز
             * - رفض الطلب
             *
             * الفرع يستطيع الإلغاء قبل بدء التجهيز.
             */
            self::Submitted => [
                self::InProgress,
                self::Rejected,
                self::Cancelled,
            ],


            /*
             * المصنع بدأ التجهيز.
             */
            self::InProgress => [
                self::ReadyForDispatch,
                self::Rejected,
            ],


            /*
             * المنتج جاهز ويحتاج موظف التوصيل.
             */
            self::ReadyForDispatch => [
                self::OutForDelivery,
            ],


            /*
             * الطلب خرج من المصنع
             * وينتظر تأكيد الفرع للاستلام.
             */
            self::OutForDelivery => [
                self::ReceivedAtBranch,
            ],


            /*
             * حالات نهائية.
             */
            self::ReceivedAtBranch,
            self::Fulfilled,
            self::Rejected,
            self::Cancelled => [],
        };
    }


    /*
    |--------------------------------------------------------------------------
    | هل يستطيع الفرع إلغاء الطلب؟
    |--------------------------------------------------------------------------
    */

    public function canBeCancelledByBranch(): bool
    {
        return $this === self::Submitted;
    }


    /*
    |--------------------------------------------------------------------------
    | هل الحالة نهائية؟
    |--------------------------------------------------------------------------
    */

    public function isTerminal(): bool
    {
        return in_array(
            $this,
            [
                self::ReceivedAtBranch,
                self::Fulfilled,
                self::Rejected,
                self::Cancelled,
            ],
            true
        );
    }


    /*
    |--------------------------------------------------------------------------
    | هل الطلب داخل المصنع؟
    |--------------------------------------------------------------------------
    */

    public function isFactoryStage(): bool
    {
        return in_array(
            $this,
            [
                self::Submitted,
                self::InProgress,
                self::ReadyForDispatch,
            ],
            true
        );
    }


    /*
    |--------------------------------------------------------------------------
    | هل الطلب في مرحلة التوصيل؟
    |--------------------------------------------------------------------------
    */

    public function isDeliveryStage(): bool
    {
        return $this === self::OutForDelivery;
    }


    /*
    |--------------------------------------------------------------------------
    | هل تم استلام الطلب؟
    |--------------------------------------------------------------------------
    */

    public function isReceived(): bool
    {
        return in_array(
            $this,
            [
                self::ReceivedAtBranch,
                self::Fulfilled,
            ],
            true
        );
    }
}