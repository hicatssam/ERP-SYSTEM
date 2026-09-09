<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SystemCurrencyPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $view = Permission::findOrCreate(
            'system_currencies.view',
            'web'
        );

        $manage = Permission::findOrCreate(
            'system_currencies.manage',
            'web'
        );

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'Admin',
                'super-admin',
                'Super Admin',
                'General Manager',
            ])
            ->get()
            ->each(function (Role $role) use ($view, $manage): void {
                $role->givePermissionTo([
                    $view,
                    $manage,
                ]);
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
