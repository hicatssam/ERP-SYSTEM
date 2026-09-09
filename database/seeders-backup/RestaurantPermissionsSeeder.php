<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RestaurantPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'restaurant.view',
            'restaurant.view_all_locations',
            'restaurant_pos.use',
            'restaurant_tables.view',
            'restaurant_tables.manage',
            'restaurant_tables.open_session',
            'restaurant_tables.close_session',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $grant = function (string $roleName, array $permissions): void {
            $role = Role::findOrCreate($roleName, 'web');
            $role->givePermissionTo($permissions);
        };

        $grant('General Manager', [
            'restaurant.view',
            'restaurant.view_all_locations',
            'restaurant_tables.view',
        ]);

        $grant('Branch Manager', [
            'restaurant.view',
            'restaurant_pos.use',
            'restaurant_tables.view',
            'restaurant_tables.manage',
            'restaurant_tables.open_session',
            'restaurant_tables.close_session',
        ]);

        $grant('Branch Employee', [
            'restaurant.view',
            'restaurant_pos.use',
            'restaurant_tables.view',
            'restaurant_tables.open_session',
            'restaurant_tables.close_session',
        ]);

        $grant('Cashier', [
            'restaurant.view',
            'restaurant_pos.use',
            'restaurant_tables.view',
            'restaurant_tables.open_session',
            'restaurant_tables.close_session',
        ]);

        $waiter = Role::findOrCreate('Waiter', 'web');
        $waiter->givePermissionTo([
            'dashboard.view',
            'orders.view',
            'orders.create',
            'orders.edit',
            'customers.view',
            'products.view',
            'restaurant.view',
            'restaurant_pos.use',
            'restaurant_tables.view',
            'restaurant_tables.open_session',
            'restaurant_tables.close_session',
        ]);

        foreach (['Admin', 'super-admin'] as $adminRoleName) {
            $admin = Role::query()
                ->where('guard_name', 'web')
                ->where('name', $adminRoleName)
                ->first();

            if ($admin) {
                $admin->givePermissionTo(
                    Permission::query()
                        ->where('guard_name', 'web')
                        ->get()
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
