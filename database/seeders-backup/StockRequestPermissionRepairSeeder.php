<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class StockRequestPermissionRepairSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $permissions = [
            'stock_requests.view',
            'stock_requests.create',
            'stock_requests.review',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        /*
         * Admin: كامل الصلاحيات التشغيلية لهذه الوحدة.
         */
        foreach (
            ['Admin', 'super-admin']
            as $roleName
        ) {
            $role =
                Role::query()
                    ->where(
                        'guard_name',
                        'web'
                    )
                    ->where(
                        'name',
                        $roleName
                    )
                    ->first();

            if ($role) {
                $role->givePermissionTo(
                    $permissions
                );
            }
        }

        /*
         * مدير الفرع: يرى ويُنشئ طلبات فرعه.
         */
        $branchManager =
            Role::findByName(
                'Branch Manager',
                'web'
            );

        $branchManager->givePermissionTo([
            'stock_requests.view',
            'stock_requests.create',
        ]);

        /*
         * مدير المصنع: يرى الطلبات الموجهة لمصنعه ويراجعها.
         */
        $factoryManager =
            Role::findByName(
                'Factory Manager',
                'web'
            );

        $factoryManager->givePermissionTo([
            'stock_requests.view',
            'stock_requests.review',
        ]);

        /*
         * مسؤول المخزون: يرى ويراجع الطلبات حسب نطاق موقعه.
         */
        $inventoryManager =
            Role::findByName(
                'Inventory Manager',
                'web'
            );

        $inventoryManager->givePermissionTo([
            'stock_requests.view',
            'stock_requests.review',
        ]);

        /*
         * المدير العام يرى فقط؛ لا نعطيه تنفيذ عمليات تشغيلية.
         */
        $generalManager =
            Role::query()
                ->where(
                    'guard_name',
                    'web'
                )
                ->where(
                    'name',
                    'General Manager'
                )
                ->first();

        if ($generalManager) {
            $generalManager->givePermissionTo([
                'stock_requests.view',
            ]);
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}