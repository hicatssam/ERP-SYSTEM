<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ShowroomRequestsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Existing showroom cake-request module.
            'showroom_cake_requests.view',
            'showroom_cake_requests.update_status',

            // New showroom sweets-request module.
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.view_all',
            'showroom_sweets_requests.create',
            'showroom_sweets_requests.update_status',
            'showroom_sweets_requests.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $grant = function (string $roleName, array $rolePermissions): void {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role) {
                $role->givePermissionTo($rolePermissions);
            }
        };

        $grant('Admin', $permissions);

        $grant('General Manager', $permissions);

        $grant('Branch Manager', [
            'showroom_cake_requests.view',
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.create',
            'showroom_sweets_requests.delete',
        ]);

        $grant('Branch Employee', [
            'showroom_cake_requests.view',
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.create',
        ]);

        $grant('Factory Manager', [
            'showroom_cake_requests.view',
            'showroom_cake_requests.update_status',
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.update_status',
        ]);

        $grant('Production Employee', [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.update_status',
        ]);

        $grant('Cake Designer', [
            'showroom_cake_requests.view',
            'showroom_cake_requests.update_status',
        ]);

        $grant('Quality Control', [
            'showroom_cake_requests.view',
            'showroom_cake_requests.update_status',
        ]);

        $grant('Dispatcher', [
            'showroom_cake_requests.view',
            'showroom_cake_requests.update_status',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
