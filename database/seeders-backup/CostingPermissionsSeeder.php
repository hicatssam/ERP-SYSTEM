<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CostingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'costing.view',
            'costing.view_all_locations',
            'costing.backfill',
            'costing.manage',

            'expenses.view',
            'expenses.view_all_locations',
            'expenses.create',
            'expenses.update',
            'expenses.submit',
            'expenses.approve',
            'expenses.approve_own',
            'expenses.post',
            'expenses.void',
            'expense_categories.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $categories = [
            ['code' => 'OPERATING', 'name' => 'مصروفات تشغيلية', 'classification' => 'operating', 'sort_order' => 10],
            ['code' => 'SALARIES', 'name' => 'رواتب وأجور', 'classification' => 'payroll', 'sort_order' => 20],
            ['code' => 'RENT', 'name' => 'إيجارات وإشغال', 'classification' => 'occupancy', 'sort_order' => 30],
            ['code' => 'UTILITIES', 'name' => 'مرافق وخدمات', 'classification' => 'utilities', 'sort_order' => 40],
            ['code' => 'MARKETING', 'name' => 'تسويق ومبيعات', 'classification' => 'selling', 'sort_order' => 50],
            ['code' => 'MAINTENANCE', 'name' => 'صيانة', 'classification' => 'maintenance', 'sort_order' => 60],
            ['code' => 'ADMIN', 'name' => 'مصروفات إدارية', 'classification' => 'administrative', 'sort_order' => 70],
            ['code' => 'OTHER', 'name' => 'مصروفات أخرى', 'classification' => 'other', 'sort_order' => 99],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::updateOrCreate(
                ['code' => $category['code']],
                $category + ['is_active' => true, 'is_system' => true]
            );
        }

        $rolePermissions = [
            'General Manager' => [
                'costing.view', 'costing.view_all_locations',
                'expenses.view', 'expenses.view_all_locations',
                'expenses.approve',
            ],
            'Branch Manager' => [
                'costing.view',
                'expenses.view', 'expenses.create', 'expenses.update', 'expenses.submit',
            ],
            'Factory Manager' => [
                'costing.view',
                'expenses.view', 'expenses.create', 'expenses.update', 'expenses.submit',
            ],
            'Accountant' => [
                'costing.view', 'costing.view_all_locations', 'costing.backfill', 'costing.manage',
                'expenses.view', 'expenses.view_all_locations', 'expenses.create', 'expenses.update', 'expenses.submit',
                'expenses.approve', 'expenses.post', 'expenses.void', 'expense_categories.manage',
            ],
        ];

        foreach ($rolePermissions as $roleName => $grants) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($grants);
            }
        }

        foreach (['Admin', 'super-admin'] as $adminRole) {
            $role = Role::query()->where('name', $adminRole)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
