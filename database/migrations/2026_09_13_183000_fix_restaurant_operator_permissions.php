<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('roles')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            return;
        }

        $matrix = [
            // Cashier needs the actual underscore-style restaurant permissions.
            // The legacy prefix "restaurant.pos." never matched restaurant_pos.use.
            'Cashier' => [
                'restaurant.view',
                'restaurant_pos.use',
                'orders.view',
                'orders.create',
                'orders.update',
                'orders.complete',
                'cash_sessions.manage',
            ],

            // Waiter creates table orders, watches the kitchen hand-off, marks a
            // ready ticket served and completes service. Financial confirmation,
            // cancellation and order editing remain excluded from this role.
            'Waiter' => [
                'restaurant.view',
                'restaurant_pos.use',
                'restaurant_tables.view',
                'restaurant_tables.open_session',
                'restaurant_tables.close_session',
                'orders.view',
                'orders.create',
                'orders.complete',
                'kitchen.view',
                'kitchen.ticket.serve',
            ],

            // Branch manager is the operational fallback for POS/table sessions.
            'Branch Manager' => [
                'restaurant.view',
                'restaurant_pos.use',
                'restaurant_tables.view',
                'restaurant_tables.manage',
                'restaurant_tables.open_session',
                'restaurant_tables.close_session',
                'cash_sessions.manage',
            ],
        ];

        foreach ($matrix as $roleName => $permissionNames) {
            $roleId = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $roleId) {
                continue;
            }

            foreach ($permissionNames as $permissionName) {
                $permissionId = DB::table('permissions')
                    ->where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->value('id');

                if (! $permissionId) {
                    $permissionId = DB::table('permissions')->insertGetId([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Permission changes are intentionally not revoked on rollback. Existing
        // installations may have granted the same permissions manually, and a
        // schema rollback must not silently remove legitimate role access.
    }
};
