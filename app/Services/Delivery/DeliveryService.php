<?php

namespace App\Services\Delivery;

use App\Enums\DeliveryStatus;
use App\Models\CustomerAddress;
use App\Models\DeliveryTask;
use App\Models\DeliveryTaskHistory;
use App\Models\DeliveryZone;
use App\Models\Location;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DeliveryTaskNotification;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function create(
        Order $order,
        array $data,
        User $actor
    ): DeliveryTask {
        $task = DB::transaction(
            function () use ($order, $data, $actor): DeliveryTask {
                // Serialize creation against cancellation/other task creation for
                // the same order. unique(order_id) remains the final DB guard.
                $lockedOrder = Order::query()
                    ->with('customer')
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $status = $lockedOrder->status instanceof \BackedEnum
                    ? $lockedOrder->status->value
                    : (string) $lockedOrder->status;

                if (in_array($status, ['cancelled', 'canceled'], true)) {
                    throw ValidationException::withMessages([
                        'order_id' => 'لا يمكن إنشاء توصيل لطلب ملغي.',
                    ]);
                }

                $isActiveBranch = Location::query()
                    ->branches()
                    ->active()
                    ->whereKey($lockedOrder->location_id)
                    ->exists();

                if (! $isActiveBranch) {
                    throw ValidationException::withMessages([
                        'order_id' => 'مهمة التوصيل يجب أن ترتبط بطلب تابع لفرع فعال.',
                    ]);
                }

                if (
                    DeliveryTask::query()
                        ->where('order_id', $lockedOrder->id)
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'order_id' => 'يوجد بالفعل طلب توصيل مرتبط بهذا الطلب.',
                    ]);
                }

                $zone = null;

                if (! empty($data['delivery_zone_id'])) {
                    $zone = DeliveryZone::query()
                        ->active()
                        ->whereKey($data['delivery_zone_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $zone) {
                        throw ValidationException::withMessages([
                            'delivery_zone_id' => 'منطقة التوصيل غير موجودة أو غير فعالة.',
                        ]);
                    }

                    if ((int) $zone->location_id !== (int) $lockedOrder->location_id) {
                        throw ValidationException::withMessages([
                            'delivery_zone_id' => 'منطقة التوصيل لا تتبع فرع الطلب.',
                        ]);
                    }

                    if (
                        (float) $lockedOrder->total_amount
                        < (float) $zone->minimum_order_amount
                    ) {
                        throw ValidationException::withMessages([
                            'delivery_zone_id' => 'قيمة الطلب أقل من الحد الأدنى لمنطقة التوصيل المحددة.',
                        ]);
                    }
                }

                $address = null;

                if (! empty($data['customer_address_id'])) {
                    $address = CustomerAddress::query()
                        ->where('is_active', true)
                        ->whereKey($data['customer_address_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $address) {
                        throw ValidationException::withMessages([
                            'customer_address_id' => 'العنوان المحفوظ غير موجود أو غير فعال.',
                        ]);
                    }

                    if (
                        ! $lockedOrder->customer_id
                        || (int) $address->customer_id !== (int) $lockedOrder->customer_id
                    ) {
                        throw ValidationException::withMessages([
                            'customer_address_id' => 'العنوان المحفوظ لا يتبع عميل هذا الطلب.',
                        ]);
                    }
                }

                $addressSnapshot = $address?->displayAddress()
                    ?: trim((string) ($data['address_snapshot'] ?? ''));

                if ($addressSnapshot === '') {
                    throw ValidationException::withMessages([
                        'address_snapshot' => 'عنوان التوصيل مطلوب.',
                    ]);
                }

                $task = DeliveryTask::query()->create([
                    'order_id' => $lockedOrder->id,
                    'location_id' => $lockedOrder->location_id,
                    'customer_id' => $lockedOrder->customer_id,
                    'customer_address_id' => $address?->id,
                    'delivery_zone_id' => $zone?->id,
                    'status' => DeliveryStatus::Pending,
                    'recipient_name' => $data['recipient_name']
                        ?? $address?->recipient_name
                        ?? $lockedOrder->customer?->name,
                    'recipient_phone' => $data['recipient_phone']
                        ?? $address?->phone
                        ?? $lockedOrder->customer?->phone,
                    'address_snapshot' => $addressSnapshot,
                    // Snapshot only. It deliberately does not alter Order/Invoice.
                    'fee_snapshot' => $zone?->fee ?? 0,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor->id,
                ]);

                DeliveryTaskHistory::query()->create([
                    'delivery_task_id' => $task->id,
                    'from_status' => null,
                    'to_status' => DeliveryStatus::Pending,
                    'changed_by' => $actor->id,
                    'note' => 'إنشاء مهمة التوصيل',
                    'metadata' => [
                        'delivery_zone_id' => $zone?->id,
                        'fee_snapshot' => (float) ($zone?->fee ?? 0),
                        'customer_address_id' => $address?->id,
                    ],
                ]);

                return $task->fresh([
                    'order',
                    'customer',
                    'zone',
                    'driver',
                ]);
            },
            3
        );

        $this->logStatus(
            $actor,
            'delivery.created',
            $task,
            null,
            DeliveryStatus::Pending->value
        );

        return $task;
    }

    public function assign(
        DeliveryTask $task,
        User $driver,
        User $actor
    ): DeliveryTask {
        abort_unless(
            $actor->isAdmin() || $actor->can('delivery.assign'),
            403,
            'لا تملك صلاحية تعيين سائقي التوصيل.'
        );

        if (! $driver->is_active || ! $driver->hasRole('Delivery Driver')) {
            throw ValidationException::withMessages([
                'assigned_driver_id' => 'المستخدم المحدد ليس سائق توصيل فعالاً.',
            ]);
        }

        $driver->loadMissing('employee.locations');

        $driverLocationAllowed = $driver->employee?->locations?->contains(
            'id',
            (int) $task->location_id
        ) ?? false;

        if (! $driverLocationAllowed) {
            throw ValidationException::withMessages([
                'assigned_driver_id' => 'السائق المحدد غير مرتبط بفرع مهمة التوصيل.',
            ]);
        }

        $fromStatus = null;
        $previousDriverId = null;

        $updated = DB::transaction(
            function () use (
                $task,
                $driver,
                $actor,
                &$fromStatus,
                &$previousDriverId
            ): DeliveryTask {
                $locked = DeliveryTask::query()
                    ->whereKey($task->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $fromStatus = $locked->status;
                $previousDriverId = $locked->assigned_driver_id;

                if (! in_array(
                    $fromStatus,
                    [
                        DeliveryStatus::Pending,
                        DeliveryStatus::Failed,
                        DeliveryStatus::Assigned,
                    ],
                    true
                )) {
                    throw ValidationException::withMessages([
                        'status' => 'لا يمكن تعيين سائق في الحالة الحالية.',
                    ]);
                }

                $locked->update([
                    'assigned_driver_id' => $driver->id,
                    'status' => DeliveryStatus::Assigned,
                    'assigned_at' => now(),
                    'failed_at' => null,
                ]);

                DeliveryTaskHistory::query()->create([
                    'delivery_task_id' => $locked->id,
                    'from_status' => $fromStatus,
                    'to_status' => DeliveryStatus::Assigned,
                    'changed_by' => $actor->id,
                    'note' => $fromStatus === DeliveryStatus::Assigned
                        ? 'إعادة تعيين السائق'
                        : 'تعيين السائق',
                    'metadata' => [
                        'previous_driver_id' => $previousDriverId,
                        'assigned_driver_id' => $driver->id,
                    ],
                ]);

                return $locked->fresh([
                    'order',
                    'customer',
                    'zone',
                    'driver',
                ]);
            },
            3
        );

        ActivityLogger::log(
            userId: $actor->id,
            action: 'delivery.assigned',
            module: 'delivery',
            recordType: 'delivery_tasks',
            recordId: $updated->id,
            oldValues: [
                'status' => $fromStatus instanceof \BackedEnum
                    ? $fromStatus->value
                    : $fromStatus,
                'assigned_driver_id' => $previousDriverId,
            ],
            newValues: [
                'status' => DeliveryStatus::Assigned->value,
                'assigned_driver_id' => $updated->assigned_driver_id,
            ],
            metadata: [
                'order_id' => $updated->order_id,
                'location_id' => $updated->location_id,
            ],
        );

        $driver->notify(
            new DeliveryTaskNotification($updated, 'assigned')
        );

        return $updated;
    }

    public function transition(
        DeliveryTask $task,
        DeliveryStatus $target,
        User $actor,
        ?string $note = null
    ): DeliveryTask {
        $fromStatus = null;

        $updated = DB::transaction(
            function () use (
                $task,
                $target,
                $actor,
                $note,
                &$fromStatus
            ): DeliveryTask {
                $locked = DeliveryTask::query()
                    ->whereKey($task->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertActorMayOperate($locked, $actor);

                $fromStatus = $locked->status;

                if (! $fromStatus->canTransitionTo($target)) {
                    throw ValidationException::withMessages([
                        'status' => "لا يمكن الانتقال من {$fromStatus->label()} إلى {$target->label()}.",
                    ]);
                }

                // Assignment is not a generic status change: it must go through
                // assign() so driver ownership and branch membership are checked.
                if ($target === DeliveryStatus::Assigned) {
                    throw ValidationException::withMessages([
                        'status' => 'الانتقال إلى حالة تعيين السائق يتم من إجراء تعيين السائق فقط.',
                    ]);
                }

                if (
                    $actor->hasRole('Delivery Driver')
                    && $target === DeliveryStatus::Cancelled
                ) {
                    abort(403, 'إلغاء مهمة التوصيل يتطلب صلاحية إدارية.');
                }

                $timestamps = match ($target) {
                    DeliveryStatus::PickedUp => ['picked_up_at' => now()],
                    DeliveryStatus::OutForDelivery => ['out_for_delivery_at' => now()],
                    DeliveryStatus::Delivered => ['delivered_at' => now()],
                    DeliveryStatus::Failed => ['failed_at' => now()],
                    DeliveryStatus::Cancelled => ['cancelled_at' => now()],
                    default => [],
                };

                $locked->update(array_merge(
                    ['status' => $target],
                    $timestamps
                ));

                DeliveryTaskHistory::query()->create([
                    'delivery_task_id' => $locked->id,
                    'from_status' => $fromStatus,
                    'to_status' => $target,
                    'changed_by' => $actor->id,
                    'note' => $note,
                ]);

                return $locked->fresh([
                    'order',
                    'customer',
                    'zone',
                    'driver',
                ]);
            },
            3
        );

        $this->logStatus(
            $actor,
            'delivery.status_changed',
            $updated,
            $fromStatus instanceof \BackedEnum
                ? $fromStatus->value
                : (string) $fromStatus,
            $target->value
        );

        $this->notifyRelevantUsers(
            $updated,
            $actor,
            $target->label()
        );

        return $updated;
    }

    private function assertActorMayOperate(
        DeliveryTask $task,
        User $actor
    ): void {
        if ($actor->isAdmin()) {
            return;
        }

        if ($actor->hasRole('Delivery Driver')) {
            abort_unless(
                (int) $task->assigned_driver_id === (int) $actor->id,
                403,
                'هذه المهمة ليست معيّنة لك.'
            );

            abort_unless(
                $actor->can('delivery.update_status'),
                403,
                'لا تملك صلاحية تحديث حالة التوصيل.'
            );

            return;
        }

        abort_unless(
            $actor->can('delivery.update_status'),
            403,
            'لا تملك صلاحية تحديث حالة التوصيل.'
        );
    }

    private function notifyRelevantUsers(
        DeliveryTask $task,
        User $actor,
        string $statusLabel
    ): void {
        $users = User::query()
            ->where('is_active', true)
            ->with(['employee.locations'])
            ->get()
            ->filter(function (User $user) use ($task, $actor): bool {
                if ($user->id === $actor->id) {
                    return false;
                }

                // The assigned driver receives updates to their own task.
                if ((int) $task->assigned_driver_id === (int) $user->id) {
                    return $user->can('delivery.view');
                }

                // Other drivers and ordinary cashiers should not receive status
                // traffic for tasks that are not theirs.
                if (! ($user->isAdmin() || $user->can('delivery.assign'))) {
                    return false;
                }

                if ($user->isAdmin() || $user->can('delivery.view_all_locations')) {
                    return true;
                }

                return $user->employee?->locations?->contains(
                    'id',
                    (int) $task->location_id
                ) ?? false;
            });

        foreach ($users as $user) {
            $user->notify(
                new DeliveryTaskNotification(
                    $task,
                    'status',
                    $statusLabel
                )
            );
        }
    }

    private function logStatus(
        User $actor,
        string $action,
        DeliveryTask $task,
        ?string $from,
        ?string $to
    ): void {
        ActivityLogger::log(
            userId: $actor->id,
            action: $action,
            module: 'delivery',
            recordType: 'delivery_tasks',
            recordId: $task->id,
            oldValues: $from ? ['status' => $from] : null,
            newValues: [
                'status' => $to,
                'assigned_driver_id' => $task->assigned_driver_id,
            ],
            metadata: [
                'order_id' => $task->order_id,
                'location_id' => $task->location_id,
            ],
        );
    }
}
