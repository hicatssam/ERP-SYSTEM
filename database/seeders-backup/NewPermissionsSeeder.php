<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class NewPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'dashboard.view',
            'dashboard.orders',
            'dashboard.sales',
            'dashboard.collections',
            'dashboard.cash',
            'dashboard.profit',
            'dashboard.inventory',
            'dashboard.stock_requests',
            'dashboard.cake_orders',
            'dashboard.cake_pipeline',
            'dashboard.products_chart',
            'dashboard.revenue_chart',
            'dashboard.branch_comparison',

            // Customers
            'customers.view',
            'customers.view_all',
            'customers.create',
            'customers.update',
            'customers.delete',

            // Employees
            'employees.view',
            'employees.view_all',
            'employees.create',
            'employees.update',
            'employees.delete',
            'employees.manage',

            // Payment Methods
            'payment_methods.view',
            'payment_methods.manage',

            // Receiving Invoices
            'receiving_invoices.view',

            // Notifications
            'notifications.manage',

            // Reports
            'reports.payment_methods',
            'reports.branch_sales',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Admin: all permissions.
        $adminRole = Role::query()
            ->where('name', 'Admin')
            ->where('guard_name', 'web')
            ->first();

        if ($adminRole) {
            $adminRole->givePermissionTo(
                Permission::query()
                    ->where('guard_name', 'web')
                    ->pluck('name')
                    ->all()
            );
        }

        // General Manager: all customers/employees across all locations.
        $generalManager = Role::query()
            ->where('name', 'General Manager')
            ->where('guard_name', 'web')
            ->first();

        if ($generalManager) {
            $generalManager->givePermissionTo([
                'customers.view',
                'customers.view_all',
                'customers.create',
                'customers.update',
                'customers.delete',

                'employees.view',
                'employees.view_all',
                'employees.create',
                'employees.update',
                'employees.delete',
                'employees.manage',
            ]);
        }

        // Accountant: read-only consolidated customer accounts across branches.
        $accountant = Role::query()
            ->where('name', 'Accountant')
            ->where('guard_name', 'web')
            ->first();

        if ($accountant) {
            $accountant->givePermissionTo([
                'customers.view',
                'customers.view_all',
            ]);
        }

        // Branch Manager: only his own branch.
        $branchManager = Role::query()
            ->where('name', 'Branch Manager')
            ->where('guard_name', 'web')
            ->first();

        if ($branchManager) {
            $branchManager->givePermissionTo([
                'customers.view',
                'customers.create',
                'customers.update',

                'employees.view',
                'employees.create',
                'employees.update',
            ]);

            foreach ([
                'customers.view_all',
                'employees.view_all',
                'employees.manage',
            ] as $permission) {
                if ($branchManager->hasPermissionTo($permission)) {
                    $branchManager->revokePermissionTo($permission);
                }
            }
        }

        // Cashier and normal branch employee: customers of own branch only.
        foreach (['Cashier', 'Branch Employee'] as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if (! $role) {
                continue;
            }

            $role->givePermissionTo([
                'customers.view',
                'customers.create',
                'customers.update',
            ]);

            if ($role->hasPermissionTo('customers.view_all')) {
                $role->revokePermissionTo('customers.view_all');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}