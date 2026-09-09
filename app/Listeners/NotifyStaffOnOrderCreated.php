<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Listeners\Concerns\HasNotificationDedup;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyStaffOnOrderCreated implements ShouldQueue
{
    use HasNotificationDedup;

    private const GLOBAL_ROLES = ['General Manager'];

    private const DEDUP_TTL_SECONDS = 86400;

    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        /*
         * Customer Menu orders have their own immediate, high-priority
         * CustomerMenuOrderCreatedNotification after the public-order
         * transaction commits. Do not also send the generic "طلب جديد".
         */
        if ((string) ($order->order_source ?? '') === 'customer_menu') {
            return;
        }

        $locationId = $order->location_id;
        $fingerprint = 'order_created_' . $order->id;

        $users = User::query()
            ->where('is_active', true)
            ->with([
                'roles',
                'permissions',
                'employee.locations',
            ])
            ->get()
            ->filter(function (User $user) use ($locationId): bool {
                if ($user->isAdmin()) {
                    return true;
                }

                if (! $user->can('orders.view')) {
                    return false;
                }

                if ($user->hasAnyRole(self::GLOBAL_ROLES)) {
                    return true;
                }

                if (! $locationId || ! $user->employee) {
                    return false;
                }

                return $user->employee->locations
                    ->contains('id', (int) $locationId);
            })
            ->values();

        foreach ($users as $user) {
            if (
                $event->createdBy
                && $user->id === $event->createdBy->id
            ) {
                continue;
            }

            if (! $this->claimDedup(
                $user,
                $fingerprint,
                OrderCreatedNotification::class,
                self::DEDUP_TTL_SECONDS
            )) {
                continue;
            }

            $user->notify(
                new OrderCreatedNotification($order)
            );
        }
    }
}
