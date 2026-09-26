<?php

namespace App\Services\Notifications;

use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderStatusChangedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\SpecialCakeOrderStatusChangedNotification;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Collection;
use Throwable;

class NotificationDispatcher
{
    

    protected static function recipients(
        array|string $permissions = [],
        array|int|null $locationIds = null,
        array|string $globalPermissions = []
    ): Collection {
        $permissions = array_values(
            array_filter((array) $permissions)
        );

        $globalPermissions = array_values(
            array_filter((array) $globalPermissions)
        );

        $locationIds = array_values(
            array_unique(
                array_map(
                    'intval',
                    array_filter((array) $locationIds)
                )
            )
        );

        return User::query()
            ->where('is_active', true)
            ->with([
                'roles',
                'permissions',
                'employee.locations',
            ])
            ->get()
            ->filter(function (User $user) use (
                $permissions,
                $globalPermissions,
                $locationIds
            ) {
                /*
                |--------------------------------------------------------------------------
                | Admin
                |--------------------------------------------------------------------------
                */

                if ($user->isAdmin()) {
                    return true;
                }

               

                if (
                    ! empty($globalPermissions)
                    && self::hasAnyPermission(
                        $user,
                        $globalPermissions
                    )
                ) {
                    return true;
                }

              

                if (
                    ! empty($permissions)
                    && ! self::hasAnyPermission(
                        $user,
                        $permissions
                    )
                ) {
                    return false;
                }

              

                if (empty($locationIds)) {
                    return true;
                }

              

                if (! $user->employee) {
                    return false;
                }

                $userLocationIds = $user->employee
                    ->locations
                    ->pluck('id')
                    ->map(
                        fn ($id) => (int) $id
                    )
                    ->all();

                return collect($locationIds)
                    ->contains(
                        fn ($locationId) =>
                            in_array(
                                (int) $locationId,
                                $userLocationIds,
                                true
                            )
                    );
            })
            ->unique('id')
            ->values();
    }

    

    protected static function hasAnyPermission(
        User $user,
        array $permissions
    ): bool {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }


    protected static function send(
        Collection $users,
        LaravelNotification $notification
    ): void {
        foreach ($users as $user) {
            try {
                $user->notify(clone $notification);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * Send an operational notification to every active user who has at least
     * one relevant permission and belongs to one of the affected locations.
     * Administrators and explicitly global permission holders are included.
     */
    public static function notifyByPermissions(
        LaravelNotification $notification,
        array|string $permissions,
        array|int|null $locationIds = null,
        array|string $globalPermissions = [],
        ?int $excludeUserId = null
    ): void {
        $users = self::recipients(
            permissions: $permissions,
            locationIds: $locationIds,
            globalPermissions: $globalPermissions,
        );

        if ($excludeUserId !== null) {
            $users = $users
                ->reject(fn (User $user): bool => (int) $user->id === $excludeUserId)
                ->values();
        }

        self::send($users, $notification);
    }

  

    public static function notifyLocationUsers(
        int $locationId,
        LaravelNotification $notification,
        array|string $permissions = []
    ): void {
        $users = self::recipients(
            permissions: $permissions,
            locationIds: [$locationId],
        );

        $users = $users
            ->filter(function (User $user) {
                return ! $user->isAdmin();
            })
            ->values();

        self::send(
            $users,
            $notification
        );
    }

    

    public static function notifyAdmins(
        LaravelNotification $notification
    ): void {
        $admins = User::query()
            ->where('is_active', true)
            ->get()
            ->filter(
                fn (User $user) =>
                    $user->isAdmin()
            )
            ->values();

        self::send(
            $admins,
            $notification
        );
    }

   

    public static function notifyUser(
        User $user,
        LaravelNotification $notification
    ): void {
        if (! $user->is_active) {
            return;
        }

        $user->notify($notification);
    }

    /*
    |--------------------------------------------------------------------------
    | Location + Admins
    |--------------------------------------------------------------------------
    |
    | أبقينا نفس اسم الدالة القديمة حتى لا نخرب أي استدعاءات موجودة.
    |
    */

    public static function notifyLocationAndAdmins(
        int $locationId,
        LaravelNotification $notification,
        array|string $permissions = [],
        array|string $globalPermissions = []
    ): void {
        $users = self::recipients(
            permissions: $permissions,
            locationIds: [$locationId],
            globalPermissions: $globalPermissions,
        );

        self::send(
            $users,
            $notification
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Order Created
    |--------------------------------------------------------------------------
    */

    public static function orderCreated(
        Order $order
    ): void {
        if (! $order->location_id) {
            return;
        }

        $notification =
            new OrderCreatedNotification(
                $order
            );

        /*
         * يستقبل:
         *
         * - مستخدم فعال
         * - عنده orders.view
         * - تابع لنفس الفرع
         * - Admin دائمًا
         */

        $users = self::recipients(
            permissions: [
                'orders.view',
            ],
            locationIds: [
                $order->location_id,
            ],
        );

        self::send(
            $users,
            $notification
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Order Status Changed
    |--------------------------------------------------------------------------
    */

    public static function orderStatusChanged(
        Order $order,
        string $oldStatus
    ): void {
        if (! $order->location_id) {
            return;
        }

        $toStatus =
            $order->status instanceof \BackedEnum
                ? $order->status->value
                : (string) $order->status;

        $notification =
            new OrderStatusChangedNotification(
                $order,
                $oldStatus,
                $toStatus
            );

        $users = self::recipients(
            permissions: [
                'orders.view',
            ],
            locationIds: [
                $order->location_id,
            ],
        );

        self::send(
            $users,
            $notification
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Received
    |--------------------------------------------------------------------------
    */

    public static function paymentReceived(
        Payment $payment
    ): void {
        $notification =
            new PaymentReceivedNotification(
                $payment
            );

        /*
        |--------------------------------------------------------------------------
        | Payment With Location
        |--------------------------------------------------------------------------
        |
        | مستخدم الفرع يستقبل لو عنده واحدة من:
        |
        | payments.record
        | payments.verify
        | financial.branch.view
        | financial.collections.view
        |
        | وصاحب financial.global.view يستقبل بغض النظر عن الفرع.
        |
        */

        if ($payment->location_id) {
            $users = self::recipients(
                permissions: [
                    'payments.record',
                    'payments.verify',
                    'financial.branch.view',
                    'financial.collections.view',
                ],

                locationIds: [
                    $payment->location_id,
                ],

                globalPermissions: [
                    'financial.global.view',
                ],
            );

            self::send(
                $users,
                $notification
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Without Location
        |--------------------------------------------------------------------------
        */

        $users = self::recipients(
            permissions: [
                'payments.record',
                'payments.verify',
                'financial.collections.view',
                'financial.global.view',
            ],
        );

        self::send(
            $users,
            $notification
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Low Stock
    |--------------------------------------------------------------------------
    */

    public static function lowStockCheck(
        int $locationId,
        int $productId,
        float $afterQty
    ): void {
        $locationProduct =
            LocationProduct::with([
                'location',
                'product',
            ])
                ->where(
                    'location_id',
                    $locationId
                )
                ->where(
                    'product_id',
                    $productId
                )
                ->first();

        if (! $locationProduct) {
            return;
        }

        $minimumQty =
            (float)
            $locationProduct
                ->minimum_stock_level;

        /*
         * المخزون ما زال أعلى من الحد الأدنى.
         */

        if ($afterQty > $minimumQty) {
            return;
        }

        $notification =
            new LowStockNotification(
                locationId:
                    $locationId,

                productId:
                    $productId,

                currentQty:
                    $afterQty,

                minimumQty:
                    $minimumQty,

                productName:
                    $locationProduct
                        ->product
                        ?->name
                    ?? "#{$productId}",

                locationName:
                    $locationProduct
                        ->location
                        ?->name
                    ?? "#{$locationId}",
            );

        /*
         * إشعار المخزون المنخفض يذهب إلى أصحاب:
         *
         * inventory.view
         * inventory.adjust
         * inventory.count
         * stock_requests.view
         * stock_requests.create
         *
         * في نفس الموقع فقط.
         */

        $users = self::recipients(
            permissions: [
                'inventory.view',
                'inventory.adjust',
                'inventory.count',
                'stock_requests.view',
                'stock_requests.create',
            ],

            locationIds: [
                $locationId,
            ],
        );

        self::send(
            $users,
            $notification
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Special Cake Order Status Changed
    |--------------------------------------------------------------------------
    */

    public static function cakeOrderStatusChanged(
        SpecialCakeOrder $order,
        string $fromStatus,
        string $toStatus
    ): void {
        $notification =
            new SpecialCakeOrderStatusChangedNotification(
                $order,
                $fromStatus,
                $toStatus
            );

        $originBranchId =
            $order->origin_branch_id
                ? (int) $order->origin_branch_id
                : null;

        $factoryLocationId =
            $order->factory_location_id
                ? (int) $order->factory_location_id
                : null;

        if ($toStatus === 'pending') {
            if (! $factoryLocationId) {
                return;
            }

            self::send(
                self::recipients(
                    permissions: [
                        'cake_orders.view',
                        'cake_orders.review',
                        'cake_orders.accept',
                        'cake_orders.manage',
                    ],
                    locationIds: [
                        $factoryLocationId,
                    ],
                ),
                $notification
            );

            return;
        }

        if ($toStatus === 'in_progress') {
            self::send(
                self::recipients(
                    permissions: [
                        'cake_orders.view',
                        'cake_orders.prepare',
                        'cake_orders.decorate',
                        'cake_orders.quality_check',
                        'cake_orders.manage',
                    ],
                    locationIds: array_filter([
                        $originBranchId,
                        $factoryLocationId,
                    ]),
                ),
                $notification
            );

            return;
        }

        if ($toStatus === 'ready') {
            self::send(
                self::recipients(
                    permissions: [
                        'cake_orders.view',
                        'cake_orders.dispatch',
                        'cake_orders.receive',
                        'cake_orders.manage',
                    ],
                    locationIds: array_filter([
                        $originBranchId,
                        $factoryLocationId,
                    ]),
                ),
                $notification
            );

            return;
        }

        if ($toStatus === 'completed') {
            if (! $originBranchId) {
                return;
            }

            self::send(
                self::recipients(
                    permissions: [
                        'cake_orders.view',
                        'cake_orders.complete',
                        'cake_orders.manage',
                    ],
                    locationIds: [
                        $originBranchId,
                    ],
                ),
                $notification
            );

            return;
        }

        if ($toStatus === 'cancelled') {
            self::send(
                self::recipients(
                    permissions: [
                        'cake_orders.view',
                        'cake_orders.cancel',
                        'cake_orders.manage',
                        'cake_orders.review',
                    ],
                    locationIds: array_filter([
                        $originBranchId,
                        $factoryLocationId,
                    ]),
                ),
                $notification
            );

            return;
        }

        /*
         * Fallback for historical/unknown values: keep operational users
         * informed instead of silently dropping the notification.
         */
        self::send(
            self::recipients(
                permissions: [
                    'cake_orders.view',
                    'cake_orders.manage',
                ],
                locationIds: array_filter([
                    $originBranchId,
                    $factoryLocationId,
                ]),
            ),
            $notification
        );
    }

}
