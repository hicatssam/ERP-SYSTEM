<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProductionPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $permissions = [
            'recipes.view',
            'recipes.create',
            'recipes.update',
            'recipes.activate',
            'recipes.cost',
            'production.view',
            'production.view_all_locations',
            'production.create',
            'production.release',
            'production.start',
            'production.complete',
            'production.cancel',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        $grants = [
            'General Manager' => [
                'recipes.view',
                'recipes.cost',
                'production.view',
                'production.view_all_locations',
            ],

            'Factory Manager' => $permissions,

            'Production Employee' => [
                'recipes.view',
                'production.view',
                'production.create',
                'production.start',
                'production.complete',
            ],

            'Inventory Manager' => [
                'recipes.view',
                'production.view',
            ],

            'Accountant' => [
                'recipes.view',
                'recipes.cost',
                'production.view',
                'production.view_all_locations',
            ],
        ];

        foreach ($grants as $roleName => $rolePermissions) {
            $role = Role::query()
                ->where('guard_name', 'web')
                ->where('name', $roleName)
                ->first();

            if ($role) {
                $role->givePermissionTo(
                    $rolePermissions
                );
            }
        }

        foreach (['Admin', 'super-admin'] as $roleName) {
            $role = Role::query()
                ->where('guard_name', 'web')
                ->where('name', $roleName)
                ->first();

            if ($role) {
                $role->givePermissionTo(
                    $permissions
                );
            }
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
