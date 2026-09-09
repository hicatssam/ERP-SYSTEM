<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProductionModuleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $permissions = [
            'recipes.view',
            'recipes.manage',
            'recipes.approve',
            'recipes.cost.view',

            'production.view',
            'production.view_all_locations',
            'production.create',
            'production.release',
            'production.start',
            'production.finish',
            'production.cancel',
            'production.cost.view',

            'quality_control.view',
            'quality_control.decide',
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
                'recipes.cost.view',
                'production.view',
                'production.view_all_locations',
                'production.cost.view',
                'quality_control.view',
            ],

            'Factory Manager' => [
                'recipes.view',
                'recipes.manage',
                'recipes.approve',
                'recipes.cost.view',
                'production.view',
                'production.create',
                'production.release',
                'production.start',
                'production.finish',
                'production.cancel',
                'production.cost.view',
                'quality_control.view',
            ],

            'Production Employee' => [
                'recipes.view',
                'production.view',
                'production.create',
                'production.start',
                'production.finish',
            ],

            'Quality Control' => [
                'production.view',
                'quality_control.view',
                'quality_control.decide',
            ],

            'Inventory Manager' => [
                'production.view',
            ],

            'Accountant' => [
                'recipes.view',
                'recipes.cost.view',
                'production.view',
                'production.cost.view',
            ],
        ];

        foreach ($grants as $roleName => $rolePermissions) {
            $role =
                Role::findOrCreate(
                    $roleName,
                    'web'
                );

            $role->givePermissionTo(
                $rolePermissions
            );
        }

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'Admin',
                'super-admin',
            ])
            ->get()
            ->each(
                fn (Role $role) =>
                    $role->givePermissionTo(
                        $permissions
                    )
            );

        Role::findOrCreate(
            'Admin',
            'web'
        )->givePermissionTo(
            $permissions
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
