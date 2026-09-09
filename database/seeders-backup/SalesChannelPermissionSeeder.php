<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SalesChannelPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            'sales_channels.view',
            'sales_channels.create',
            'sales_channels.update',
            'sales_channels.delete',
            'sales_channels.activate',
            'sales_channels.reports',
        ])->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['Admin', 'Super Admin', 'admin', 'super-admin'])
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));
    }
}
