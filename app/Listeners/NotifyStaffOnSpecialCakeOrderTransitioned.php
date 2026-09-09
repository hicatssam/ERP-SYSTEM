<?php

namespace App\Listeners;

use App\Events\SpecialCakeOrderTransitioned;
use App\Listeners\Concerns\HasNotificationDedup;
use App\Models\User;
use App\Notifications\SpecialCakeOrderTransitionedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyStaffOnSpecialCakeOrderTransitioned implements ShouldQueue
{
    use HasNotificationDedup;

    private const GLOBAL_ROLES = ['General Manager', 'Factory Manager'];
    private const DEDUP_TTL_SECONDS = 86400;

    public function handle(SpecialCakeOrderTransitioned $event): void
    {
        $order = $event->order;
        $branchId = $order->origin_branch_id;
        $factoryId = $order->factory_location_id;

        $fingerprint = "cake_transition_{$order->id}_{$event->fromStatus}_{$event->toStatus}";
        $permissions = $this->permissionsForStatus($event->toStatus);
        $locationIds = $this->locationsForStatus(
            $event->toStatus,
            $branchId,
            $factoryId
        );

        $users = User::query()
            ->where('is_active', true)
            ->with(['roles', 'permissions', 'employee.locations'])
            ->get()
            ->filter(function (User $user) use ($permissions, $locationIds): bool {
                if ($user->isAdmin()) {
                    return true;
                }

                $hasPermission = collect($permissions)
                    ->contains(fn (string $permission): bool => $user->can($permission));

                if (! $hasPermission) {
                    return false;
                }

                if ($user->hasAnyRole(self::GLOBAL_ROLES)) {
                    return true;
                }

                if (empty($locationIds) || ! $user->employee) {
                    return false;
                }

                $userLocationIds = $user->employee->locations
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                return collect($locationIds)
                    ->contains(
                        fn (int $locationId): bool =>
                            in_array($locationId, $userLocationIds, true)
                    );
            })
            ->values();

        foreach ($users as $user) {
            if ($event->changedBy && $user->id === $event->changedBy->id) {
                continue;
            }

            if (! $this->claimDedup(
                $user,
                $fingerprint,
                SpecialCakeOrderTransitionedNotification::class,
                self::DEDUP_TTL_SECONDS
            )) {
                continue;
            }

            $user->notify(new SpecialCakeOrderTransitionedNotification(
                $order,
                $event->fromStatus,
                $event->toStatus,
                $event->note,
            ));
        }
    }

    private function permissionsForStatus(string $status): array
    {
        return match ($status) {
            'pending_deposit',
            'deposit_paid' => [
                'cake_orders.view',
                'cake_orders.manage',
            ],

            'pending_factory_review' => [
                'cake_orders.view',
                'cake_orders.review',
                'cake_orders.accept',
                'cake_orders.reject',
                'cake_orders.request_modification',
                'cake_orders.manage',
            ],

            'accepted' => [
                'cake_orders.view',
                'cake_orders.schedule',
                'cake_orders.manage',
            ],

            'modification_requested' => [
                'cake_orders.view',
                'cake_orders.review',
                'cake_orders.request_modification',
                'cake_orders.manage',
            ],

            'scheduled' => [
                'cake_orders.view',
                'cake_orders.prepare',
                'cake_orders.manage',
            ],

            'in_preparation' => [
                'cake_orders.view',
                'cake_orders.prepare',
                'cake_orders.decorate',
                'cake_orders.manage',
            ],

            'decorating',
            'in_decoration' => [
                'cake_orders.view',
                'cake_orders.decorate',
                'cake_orders.quality_check',
                'cake_orders.manage',
            ],

            'quality_check' => [
                'cake_orders.view',
                'cake_orders.quality_check',
                'cake_orders.dispatch',
                'cake_orders.manage',
            ],

            'ready' => [
                'cake_orders.view',
                'cake_orders.dispatch',
                'cake_orders.receive',
                'cake_orders.manage',
            ],

            'sent_to_branch',
            'dispatched_to_branch' => [
                'cake_orders.view',
                'cake_orders.dispatch',
                'cake_orders.receive',
                'cake_orders.manage',
            ],

            'received_by_branch',
            'received_at_branch',
            'ready_for_customer',
            'ready_for_pickup' => [
                'cake_orders.view',
                'cake_orders.receive',
                'cake_orders.manage',
            ],

            'delivered',
            'completed' => [
                'cake_orders.view',
                'cake_orders.manage',
            ],

            'rejected',
            'cancelled',
            'delayed',
            'issue_open' => [
                'cake_orders.view',
                'cake_orders.review',
                'cake_orders.manage',
            ],

            default => [
                'cake_orders.view',
                'cake_orders.manage',
            ],
        };
    }

    private function locationsForStatus(
        string $status,
        ?int $branchId,
        ?int $factoryId
    ): array {
        $branchOnlyStatuses = [
            'pending_deposit',
            'deposit_paid',
            'received_by_branch',
            'received_at_branch',
            'ready_for_customer',
            'ready_for_pickup',
            'delivered',
            'completed',
        ];

        if (in_array($status, $branchOnlyStatuses, true)) {
            return array_values(
                array_map('intval', array_filter([$branchId]))
            );
        }

        return array_values(
            array_unique(
                array_map(
                    'intval',
                    array_filter([$branchId, $factoryId])
                )
            )
        );
    }
}