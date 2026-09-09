<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AttendancePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'attendance.view',
            'attendance.manage',
            'attendance.approve',
            'attendance.shifts.manage',
            'attendance.leaves.view',
            'attendance.leaves.manage',
            'attendance.leaves.approve',
            'payroll.attendance.sync',
            'attendance.devices.view',
            'attendance.devices.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
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
