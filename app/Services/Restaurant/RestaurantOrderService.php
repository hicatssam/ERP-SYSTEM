<?php

namespace App\Services\Restaurant;

use App\Enums\RestaurantServiceType;
use App\Models\CashSession;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\RestaurantTable;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantOrderService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly RestaurantTableService $tables
    ) {
    }

    public function createPosOrder(array $data, User $user): Order
    {
        $serviceType = RestaurantServiceType::from(
            (string) $data['service_type']
        );

        $openedSession = null;
        $locationId = (int) $data['location_id'];

        $order = DB::transaction(function () use (
            $data,
            $user,
            $serviceType,
            $locationId,
            &$openedSession
        ): Order {
            $payload = $data;

            $payload['location_id'] = $locationId;
            $payload['restaurant_service_type'] = $serviceType->value;
            $payload['order_source'] = $data['order_source'] ?? 'restaurant_pos';

            // created_by records the operator/cashier for every POS order.
            // waiter_id is meaningful only for table service.
            $payload['waiter_id'] = $serviceType === RestaurantServiceType::DineIn
                ? $user->id
                : null;

            $payload['guest_count'] =
                $serviceType === RestaurantServiceType::DineIn
                    ? max(1, (int) ($data['guest_count'] ?? 1))
                    : null;

            if ($serviceType === RestaurantServiceType::DineIn) {
                $table = RestaurantTable::query()
                    ->where('location_id', $locationId)
                    ->whereKey($data['restaurant_table_id'])
                    ->first();

                if (! $table) {
                    throw ValidationException::withMessages([
                        'restaurant_table_id' => 'الطاولة المحددة لا تتبع هذا الفرع.',
                    ]);
                }

                [$session, $wasOpened] = $this->tables->resolveForOrder(
                    $table,
                    $user,
                    (int) ($payload['guest_count'] ?? 1)
                );

                $payload['restaurant_table_id'] = $table->id;
                $payload['restaurant_table_session_id'] = $session->id;

                if ($wasOpened) {
                    $openedSession = $session;
                }
            } else {
                $payload['restaurant_table_id'] = null;
                $payload['restaurant_table_session_id'] = null;
            }

            $paymentMethod = $this->resolvePaymentMethodForOperation(
                $payload,
                $locationId
            );

            $this->assertOpenCashSessionIfRequired(
                $payload,
                $paymentMethod,
                $user,
                $locationId
            );

            $requiresVerification =
                ($payload['payment_arrangement'] ?? null) === 'pending_verification'
                || (bool) $paymentMethod?->requires_verification;

            $order = $this->orders->createOrder(
                $payload,
                $user,
                ! $requiresVerification
            );

            ActivityLogger::log(
                userId: $user->id,
                action: 'restaurant.order.created',
                module: 'restaurant',
                recordType: 'orders',
                recordId: $order->id,
                newValues: [
                    'order_number' => $order->order_number,
                    'restaurant_service_type' => $serviceType->value,
                    'restaurant_table_id' => $order->restaurant_table_id,
                    'restaurant_table_session_id' => $order->restaurant_table_session_id,
                    'waiter_id' => $order->waiter_id,
                    'guest_count' => $order->guest_count,
                    'status' => $order->status?->value ?? $order->status,
                ],
                metadata: [
                    'location_id' => $locationId,
                    'source' => 'restaurant_pos',
                    'operator_id' => $user->id,
                ],
            );

            return $order;
        });

        if ($openedSession) {
            $this->tables->notifyOpened(
                $openedSession->fresh([
                    'table.area',
                    'location',
                    'openedBy.employee',
                ])
            );
        }

        return $order->fresh([
            'items',
            'invoice',
            'restaurantTable.area',
            'restaurantTableSession',
            'waiter.employee',
        ]);
    }

    private function resolvePaymentMethodForOperation(
        array $data,
        int $locationId
    ): ?PaymentMethod {
        $arrangement = (string) ($data['payment_arrangement'] ?? '');

        if (in_array($arrangement, ['pay_on_pickup', 'on_account'], true)) {
            return null;
        }

        if (empty($data['payment_method_id'])) {
            return null;
        }

        $method = PaymentMethod::query()
            ->active()
            ->find($data['payment_method_id']);

        if (! $method) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'طريقة الدفع المحددة غير مفعلة.',
            ]);
        }

        if (! $method->isAvailableAt($locationId)) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'طريقة الدفع المحددة غير متاحة في هذا الفرع.',
            ]);
        }

        return $method;
    }

    private function assertOpenCashSessionIfRequired(
        array $data,
        ?PaymentMethod $method,
        User $user,
        int $locationId
    ): void {
        if (
            ! $method
            || (string) $method->type !== 'cash'
            || ! (bool) SystemSetting::get('pos_require_open_cash_session', true)
        ) {
            return;
        }

        $arrangement = (string) ($data['payment_arrangement'] ?? '');

        if (in_array($arrangement, ['pay_on_pickup', 'on_account'], true)) {
            return;
        }

        $employeeId = (int) ($user->employee?->id ?? 0);

        if ($employeeId <= 0) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'لا يمكن تحصيل دفعة نقدية لأن الحساب غير مرتبط بموظف.',
            ]);
        }

        $hasOpenSession = CashSession::query()
            ->where('employee_id', $employeeId)
            ->where('location_id', $locationId)
            ->where('status', 'open')
            ->exists();

        if (! $hasOpenSession) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'يجب فتح جلسة كاشير لهذا الفرع قبل تحصيل دفعة نقدية من نقطة البيع.',
            ]);
        }
    }
}
