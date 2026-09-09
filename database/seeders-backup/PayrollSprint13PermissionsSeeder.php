<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PayrollSprint13PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'payroll.documents.print',
            'payroll.reports.view',
            'payroll.payments.verify',
            'payroll.payments.void',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        $admin = Role::query()
            ->where('name', 'Admin')
            ->where('guard_name', 'web')
            ->first();

        if ($admin) {
            $admin->givePermissionTo(
                $permissions
            );
        }

        app(
            \Spatie\Permission\PermissionRegistrar::class
        )->forgetCachedPermissions();
    }
}
