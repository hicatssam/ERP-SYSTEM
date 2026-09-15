<?php

namespace App\Services\Auth;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RestaurantOperatorPermissionRepairService
{
    /**
     * Re-apply the operational permissions that the legacy prefix-based seeder
     * misses because permissions such as restaurant_pos.use and
     * restaurant_tables.* use underscores rather than the old dotted prefix.
     *
     * This is additive only: it never removes permissions that an installation
     * may have granted intentionally.
     */
    public function repair(): void
    {
        $matrix = [
            'Cashier' => [
                'restaurant.view',
                'restaurant_pos.use',
                'orders.view',
                'orders.create',
                'orders.update',
                'orders.confirm',
                'orders.complete',
                'cash_sessions.manage',
            ],
            'Waiter' => [
                'restaurant.view',
                'restaurant_pos.use',
                'restaurant_tables.view',
                'restaurant_tables.open_session',
                'restaurant_tables.close_session',
                'orders.view',
                'orders.create',
                'orders.confirm',
                'orders.complete',
                'kitchen.view',
                'kitchen.ticket.serve',
            ],
            'Branch Manager' => [
                'restaurant.view',
                'restaurant_pos.use',
                'restaurant_tables.view',
                'restaurant_tables.manage',
                'restaurant_tables.open_session',
                'restaurant_tables.close_session',
                'cash_sessions.manage',
            ],
        ];

        foreach ($matrix as $roleName => $permissionNames) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if (! $role) {
                continue;
            }

            foreach ($permissionNames as $permissionName) {
                $permission = Permission::findOrCreate($permissionName, 'web');
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
