<?php

namespace App\Services\SpecialCakes;

use App\Enums\CakeOrderStatus;
use App\Events\SpecialCakeOrderTransitioned;
use App\Models\CakeOrderStatusHistory;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class SpecialCakeStatusTransitionService
{
    /**
     * حالات طلب الكيك المرئية مختصرة إلى أربع مراحل تشغيلية.
     * نبقي الصلاحيات الدقيقة الحالية حتى لا تتغير أدوار الموظفين
     * بالتزامن مع تبسيط الحالات.
     */
    private array $transitionPermissions = [
        'draft' => [
            'pending' => 'cake_orders.create',
            'cancelled' => 'cake_orders.cancel',
        ],
        'pending' => [
            'in_progress' => 'cake_orders.accept',
            'cancelled' => 'cake_orders.cancel',
        ],
        'in_progress' => [
            'ready' => 'cake_orders.quality_check',
            'cancelled' => 'cake_orders.cancel',
        ],
        'ready' => [
            'completed' => 'cake_orders.complete',
            'cancelled' => 'cake_orders.cancel',
        ],
    ];

    public function transition(
        SpecialCakeOrder $order,
        string $toStatus,
        User $user,
        ?string $note = null
    ): void {
        $fromStatus = $this->statusValue($order);

        // يمنع تمرير أي قيمة غير موجودة في الـ Enum قبل محاولة Eloquent حفظها.
        if (! CakeOrderStatus::tryFrom($toStatus)) {
            throw ValidationException::withMessages([
                'to_status' => 'الحالة المطلوبة غير معروفة في النظام.',
            ]);
        }

        if (! in_array($toStatus, $this->allowedTransitions($fromStatus), true)) {
            throw ValidationException::withMessages([
                'to_status' => 'هذا الانتقال غير مسموح من الحالة الحالية.',
            ]);
        }

        if (! $this->canUserTransition($user, $order, $toStatus)) {
            throw new AuthorizationException('ليس لديك صلاحية تنفيذ هذه الخطوة في طلب الكيك.');
        }

        $update = [
            'status' => CakeOrderStatus::from($toStatus),
        ];

        if ($toStatus === CakeOrderStatus::Completed->value) {
            $update['completed_at'] = now();
        }

        if ($toStatus === CakeOrderStatus::Cancelled->value) {
            $update['cancelled_at'] = now();
            $update['cancelled_by'] = $user->id;
            $update['cancellation_reason'] = $note;
        }

        $order->update($update);

        CakeOrderStatusHistory::create([
            'special_cake_order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $user->id,
            'note' => $note,
            'created_at' => now(),
        ]);

        ActivityLogger::log(
            userId: $user->id,
            action: 'cake_order.status_changed',
            module: 'special_cake_orders',
            recordType: 'special_cake_orders',
            recordId: $order->id,
            oldValues: ['status' => $fromStatus],
            newValues: ['status' => $toStatus],
            metadata: [
                'order_number' => $order->order_number,
                'origin_branch_id' => $order->origin_branch_id,
                'factory_id' => $order->factory_location_id,
                'note' => $note,
            ],
        );

        SpecialCakeOrderTransitioned::dispatch(
            $order->fresh(),
            $fromStatus,
            $toStatus,
            $user,
            $note
        );
    }

    /**
     * @return array<int, string>
     */
    public function allowedTransitions(string $fromStatus): array
    {
        $canonical =
            SpecialCakeOrder::normalizeLegacyWorkflowStatus(
                $fromStatus
            );

        return SpecialCakeOrder::allowedTransitions()[
            $canonical
        ] ?? [];
    }

    /**
     * الانتقالات التي يحق للمستخدم الحالي تنفيذها فعلياً.
     *
     * @return array<int, string>
     */
    public function allowedTransitionsForUser(
        SpecialCakeOrder $order,
        User $user
    ): array {
        $fromStatus = $this->statusValue($order);

        return collect($this->allowedTransitions($fromStatus))
            ->filter(fn (string $toStatus): bool =>
                $this->canUserTransition($user, $order, $toStatus)
            )
            ->values()
            ->all();
    }

    public function canUserTransition(
        User $user,
        SpecialCakeOrder $order,
        string $toStatus
    ): bool {
        $fromStatus = $this->statusValue($order);

        if (! in_array($toStatus, $this->allowedTransitions($fromStatus), true)) {
            return false;
        }

        if (! $user->can('view', $order)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $permission = $this->requiredPermission(
            $fromStatus,
            $toStatus
        );

        if (
            $permission !== null
            && $user->can($permission)
        ) {
            return true;
        }

        /*
         * The simplified Ready stage replaces both branch receiving and
         * customer handoff. Existing branch users with receive permission
         * therefore remain able to finish the order without needing a new
         * role migration.
         */
        $canonicalFrom =
            SpecialCakeOrder::normalizeLegacyWorkflowStatus(
                $fromStatus
            );

        return $canonicalFrom === 'ready'
            && $toStatus === 'completed'
            && $user->can('cake_orders.receive');
    }

    public function requiredPermission(
        string $fromStatus,
        string $toStatus
    ): ?string {
        $canonical =
            SpecialCakeOrder::normalizeLegacyWorkflowStatus(
                $fromStatus
            );

        return $this->transitionPermissions[
            $canonical
        ][$toStatus] ?? null;
    }

    private function statusValue(SpecialCakeOrder $order): string
    {
        return $order->status instanceof CakeOrderStatus
            ? $order->status->value
            : (string) $order->status;
    }
}
