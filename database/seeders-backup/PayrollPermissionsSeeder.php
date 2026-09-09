<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PayrollPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'payroll.view',
            'payroll.manage',
            'payroll.approve',
            'payroll.pay',
            'payroll.adjustments.manage',
            'payroll.advances.manage',
            'employee_ledger.view',
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
            $admin->givePermissionTo($permissions);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
