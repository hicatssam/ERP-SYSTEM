<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Listeners\Concerns\HasNotificationDedup;
use App\Models\User;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyStaffOnOrderStatusChanged implements ShouldQueue
{
    use HasNotificationDedup;

    private const GLOBAL_ROLES = ['General Manager'];
    private const DEDUP_TTL_SECONDS = 86400;

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;
        $locationId = $order->location_id;
        $fingerprint = "order_status_{$order->id}_{$event->fromStatus}_{$event->toStatus}";

        $users = User::query()
            ->where('is_active', true)
            ->with(['roles', 'permissions', 'employee.locations'])
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
            if ($event->changedBy && $user->id === $event->changedBy->id) {
                continue;
            }

            if (! $this->claimDedup(
                $user,
                $fingerprint,
                OrderStatusChangedNotification::class,
                self::DEDUP_TTL_SECONDS
            )) {
                continue;
            }

            $user->notify(new OrderStatusChangedNotification(
                $order,
                $event->fromStatus,
                $event->toStatus,
            ));
        }
    }
}