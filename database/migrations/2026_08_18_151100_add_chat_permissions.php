<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'chat.view',
            'chat.send',
            'chat.view_all_branches',
            'chat.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        /*
        |--------------------------------------------------------------------------
        | Default role mapping
        |--------------------------------------------------------------------------
        |
        | إذا كان اسم الدور موجودًا في النظام سيتم منحه الصلاحيات المناسبة.
        | إذا لم يكن موجودًا، لا يحدث أي خطأ ويمكن منحه يدويًا من صفحة الأدوار.
        |
        */

        $fullAccessRoles = [
            'Admin',
            'super-admin',
            'General Manager',
        ];

        Role::query()
            ->whereIn('name', $fullAccessRoles)
            ->get()
            ->each(function (Role $role): void {
                $role->givePermissionTo([
                    'chat.view',
                    'chat.send',
                    'chat.view_all_branches',
                    'chat.manage',
                ]);
            });

        Role::query()
            ->where('name', 'Branch Manager')
            ->get()
            ->each(function (Role $role): void {
                $role->givePermissionTo([
                    'chat.view',
                    'chat.send',
                ]);
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = Permission::query()
            ->whereIn('name', [
                'chat.view',
                'chat.send',
                'chat.view_all_branches',
                'chat.manage',
            ])
            ->get();

        foreach ($permissions as $permission) {
            $permission->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
