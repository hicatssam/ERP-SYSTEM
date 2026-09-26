<?php

namespace App\Listeners;

use App\Events\SpecialCakeOrderTransitioned;
use App\Listeners\Concerns\HasNotificationDedup;
use App\Models\User;
use App\Notifications\SpecialCakeOrderTransitionedNotification;

class NotifyStaffOnSpecialCakeOrderTransitioned
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
        $recipientRules = $this->recipientRulesForStatus(
            $event->toStatus,
            $branchId,
            $factoryId
        );

        $users = User::query()
            ->where('is_active', true)
            ->with(['roles', 'permissions', 'employee.locations'])
            ->get()
            ->filter(function (User $user) use ($recipientRules): bool {
                if (
                    $user->isAdmin()
                    || $user->hasAnyRole(self::GLOBAL_ROLES)
                ) {
                    return true;
                }

                if (! $user->employee) {
                    return false;
                }

                $userLocationIds = $user->employee->locations
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                foreach ($recipientRules as $rule) {
                    $permissions = $rule['permissions'] ?? [];
                    $locationIds = $rule['location_ids'] ?? [];

                    $hasPermission = collect($permissions)
                        ->contains(
                            fn (string $permission): bool =>
                                $user->can($permission)
                        );

                    if (! $hasPermission) {
                        continue;
                    }

                    if (empty($locationIds)) {
                        return true;
                    }

                    if (
                        collect($locationIds)->contains(
                            fn (int $locationId): bool =>
                                in_array($locationId, $userLocationIds, true)
                        )
                    ) {
                        return true;
                    }
                }

                return false;
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

    private function recipientRulesForStatus(
        string $status,
        ?int $branchId,
        ?int $factoryId
    ): array {
        $branch = array_values(
            array_map('intval', array_filter([$branchId]))
        );

        $factory = array_values(
            array_map('intval', array_filter([$factoryId]))
        );

        $both = array_values(
            array_unique([
                ...$branch,
                ...$factory,
            ])
        );

        return match ($status) {
            'pending' => [
                [
                    'location_ids' => $factory,
                    'permissions' => [
                        'cake_orders.review',
                        'cake_orders.accept',
                        'cake_orders.manage',
                    ],
                ],
                [
                    'location_ids' => $branch,
                    'permissions' => [
                        'cake_orders.receive',
                        'cake_orders.manage',
                    ],
                ],
            ],

            'in_progress' => [
                [
                    'location_ids' => $factory,
                    'permissions' => [
                        'cake_orders.prepare',
                        'cake_orders.decorate',
                        'cake_orders.quality_check',
                        'cake_orders.manage',
                    ],
                ],
                [
                    'location_ids' => $branch,
                    'permissions' => [
                        'cake_orders.create',
                        'cake_orders.view',
                        'cake_orders.manage',
                    ],
                ],
            ],

            'ready' => [
                [
                    'location_ids' => $factory,
                    'permissions' => [
                        'cake_orders.dispatch',
                        'cake_orders.manage',
                    ],
                ],
                [
                    'location_ids' => $branch,
                    'permissions' => [
                        'cake_orders.receive',
                        'cake_orders.complete',
                        'cake_orders.manage',
                    ],
                ],
            ],

            'completed' => [
                [
                    'location_ids' => $branch,
                    'permissions' => [
                        'cake_orders.create',
                        'cake_orders.view',
                        'cake_orders.manage',
                    ],
                ],
                [
                    'location_ids' => $factory,
                    'permissions' => [
                        'cake_orders.manage',
                    ],
                ],
            ],

            'cancelled' => [
                [
                    'location_ids' => $branch,
                    'permissions' => [
                        'cake_orders.create',
                        'cake_orders.manage',
                    ],
                ],
                [
                    'location_ids' => $factory,
                    'permissions' => [
                        'cake_orders.review',
                        'cake_orders.manage',
                    ],
                ],
            ],

            default => [
                [
                    'location_ids' => $both,
                    'permissions' => [
                        'cake_orders.view',
                        'cake_orders.manage',
                    ],
                ],
            ],
        };
    }
}
