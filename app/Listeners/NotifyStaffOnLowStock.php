<?php

namespace App\Listeners;

use App\Events\LowStockDetected;
use App\Listeners\Concerns\HasNotificationDedup;
use App\Models\User;
use App\Notifications\LowStockDetectedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyStaffOnLowStock implements ShouldQueue
{
    use HasNotificationDedup;

    private const GLOBAL_ROLES = ['General Manager', 'Inventory Manager'];

    private const PERMISSIONS = [
        'inventory.view',
        'inventory.adjust',
        'inventory.count',
        'stock_requests.view',
        'stock_requests.create',
    ];

    private const DEDUP_TTL_SECONDS = 21600;

    public function handle(LowStockDetected $event): void
    {
        $inventory = $event->inventory;
        $locationId = $inventory->location_id;
        $fingerprint = "low_stock_{$locationId}_{$inventory->product_id}";

        $users = User::query()
            ->where('is_active', true)
            ->with(['roles', 'permissions', 'employee.locations'])
            ->get()
            ->filter(function (User $user) use ($locationId): bool {
                if ($user->isAdmin()) {
                    return true;
                }

                $hasPermission = collect(self::PERMISSIONS)
                    ->contains(fn (string $permission): bool => $user->can($permission));

                if (! $hasPermission) {
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
            if (! $this->claimDedup(
                $user,
                $fingerprint,
                LowStockDetectedNotification::class,
                self::DEDUP_TTL_SECONDS
            )) {
                continue;
            }

            $user->notify(new LowStockDetectedNotification(
                $inventory,
                $event->currentQuantity,
                $event->minimumLevel,
            ));
        }
    }
}