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
     * كل انتقال في خط إنتاج الكيك مربوط بصلاحية العملية الفعلية.
     * مصدر الانتقالات المسموحة نفسه هو SpecialCakeOrder::allowedTransitions()
     * حتى لا تتكرر حالات قديمة مثل in_decoration.
     */
    private array $transitionPermissions = [
        'draft' => [
            'pending_factory_review' => 'cake_orders.create',
            'cancelled' => 'cake_orders.cancel',
        ],
        'pending_factory_review' => [
            'accepted' => 'cake_orders.accept',
            'rejected' => 'cake_orders.reject',
            'modification_requested' => 'cake_orders.request_modification',
            'cancelled' => 'cake_orders.cancel',
        ],
        'modification_requested' => [
            'pending_factory_review' => 'cake_orders.edit',
            'cancelled' => 'cake_orders.cancel',
        ],
        'accepted' => [
            'scheduled' => 'cake_orders.schedule',
            'cancelled' => 'cake_orders.cancel',
        ],
        'scheduled' => [
            'in_preparation' => 'cake_orders.prepare',
            'delayed' => 'cake_orders.schedule',
            'cancelled' => 'cake_orders.cancel',
        ],
        'in_preparation' => [
            'decorating' => 'cake_orders.decorate',
            'delayed' => 'cake_orders.prepare',
        ],
        'decorating' => [
            'quality_check' => 'cake_orders.quality_check',
        ],
        'quality_check' => [
            'ready' => 'cake_orders.quality_check',
            'decorating' => 'cake_orders.quality_check',
            'in_preparation' => 'cake_orders.quality_check',
        ],
        'ready' => [
            'sent_to_branch' => 'cake_orders.dispatch',
        ],
        'sent_to_branch' => [
            'received_by_branch' => 'cake_orders.receive',
        ],
        'received_by_branch' => [
            'ready_for_customer' => 'cake_orders.receive',
            'issue_open' => 'cake_orders.receive',
        ],
        'issue_open' => [
            'ready_for_customer' => 'cake_orders.receive',
        ],
        'ready_for_customer' => [
            'completed' => 'cake_orders.complete',
        ],
        'delayed' => [
            'in_preparation' => 'cake_orders.prepare',
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

        $order->update([
            'status' => CakeOrderStatus::from($toStatus),
        ]);

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
        return SpecialCakeOrder::allowedTransitions()[$fromStatus] ?? [];
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

        $permission = $this->requiredPermission($fromStatus, $toStatus);

        return $permission !== null && $user->can($permission);
    }

    public function requiredPermission(
        string $fromStatus,
        string $toStatus
    ): ?string {
        return $this->transitionPermissions[$fromStatus][$toStatus] ?? null;
    }

    private function statusValue(SpecialCakeOrder $order): string
    {
        return $order->status instanceof CakeOrderStatus
            ? $order->status->value
            : (string) $order->status;
    }
}
