<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CustomerOrderDisplayPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $permission = Permission::findOrCreate(
            'customer_display.view',
            'web'
        );

        foreach ([
            'General Manager',
            'Branch Manager',
            'Cashier',
            'Kitchen Staff',
        ] as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role) {
                $role->givePermissionTo(
                    $permission
                );
            }
        }

        $admin = Role::query()
            ->where('name', 'Admin')
            ->where('guard_name', 'web')
            ->first();

        if ($admin) {
            $admin->givePermissionTo(
                $permission
            );
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
