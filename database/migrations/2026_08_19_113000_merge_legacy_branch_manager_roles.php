<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        DB::transaction(function (): void {
            $canonicalRole = DB::table('roles')
                ->where('name', 'Branch Manager')
                ->where('guard_name', 'web')
                ->first();

            if (! $canonicalRole) {
                $canonicalRoleId = DB::table('roles')->insertGetId([
                    'name' => 'Branch Manager',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $canonicalRole = (object) [
                    'id' => $canonicalRoleId,
                    'name' => 'Branch Manager',
                    'guard_name' => 'web',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Legacy aliases
            |--------------------------------------------------------------------------
            |
            | Important:
            | We do NOT merge the legacy permission set into Branch Manager.
            | The canonical Branch Manager permissions are managed by
            | DefaultRolePermissionsSeeder.
            |
            | We only move user/model assignments, then remove legacy roles.
            |
            */

            $legacyNames = [
                'مدير الفرع',
                'branch-manager',
                'branch_manager',
            ];

            $legacyRoles = DB::table('roles')
                ->where('guard_name', 'web')
                ->whereIn('name', $legacyNames)
                ->where('id', '!=', $canonicalRole->id)
                ->get();

            foreach ($legacyRoles as $legacyRole) {
                $assignments = DB::table('model_has_roles')
                    ->where('role_id', $legacyRole->id)
                    ->get();

                foreach ($assignments as $assignment) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $canonicalRole->id,
                        'model_type' => $assignment->model_type,
                        'model_id' => $assignment->model_id,
                    ]);
                }

                DB::table('model_has_roles')
                    ->where('role_id', $legacyRole->id)
                    ->delete();

                DB::table('role_has_permissions')
                    ->where('role_id', $legacyRole->id)
                    ->delete();

                DB::table('roles')
                    ->where('id', $legacyRole->id)
                    ->delete();
            }
        });

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    public function down(): void
    {
        /*
         * Intentional no-op.
         *
         * This migration normalizes duplicate role identities and moves
         * real user assignments to the canonical role. Recreating the old
         * duplicate Arabic/alias role during rollback would re-introduce the
         * data problem we are fixing.
         */
    }
};