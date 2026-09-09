<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RestaurantMenuPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $view = Permission::findOrCreate('restaurant_menu.view', 'web');
        $manage = Permission::findOrCreate('restaurant_menu.manage', 'web');

        /*
         * Existing restaurant users get read-only menu access automatically.
         * This normally includes cashier/restaurant staff who already have restaurant.view.
         */
        Role::query()
            ->whereHas('permissions', fn ($permissions) =>
                $permissions->where('name', 'restaurant.view')
            )
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($view));

        /*
         * Roles already trusted to manage restaurant tables are treated as operational managers.
         * System-role managers also get full menu management.
         */
        Role::query()
            ->where(function ($roles) {
                $roles
                    ->whereHas('permissions', fn ($permissions) =>
                        $permissions->where('name', 'restaurant_tables.manage')
                    )
                    ->orWhereHas('permissions', fn ($permissions) =>
                        $permissions->where('name', 'roles.manage')
                    );
            })
            ->get()
            ->each(function (Role $role) use ($view, $manage) {
                $role->givePermissionTo([$view, $manage]);
            });

        $this->command?->info('Restaurant menu permissions created.');
    }
}
