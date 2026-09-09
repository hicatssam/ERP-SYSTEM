<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $permissions = [
            'chat.direct.start_all',
            'chat.direct.start_location',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | الإدارة العليا: تبدأ محادثة مع أي حساب فعال
        |--------------------------------------------------------------------------
        */

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'Admin',
                'super-admin',
                'Super Admin',
                'General Manager',
            ])
            ->get()
            ->each(
                fn (Role $role) =>
                    $role->givePermissionTo([
                        'chat.direct.start_all',
                    ])
            );

        /*
        |--------------------------------------------------------------------------
        | مدير الموقع: يبدأ محادثة فقط مع مستخدمي موقعه الرئيسي
        |--------------------------------------------------------------------------
        |
        | Branch Manager  -> موظفي فرعه.
        | Factory Manager -> موظفي المصنع.
        |
        | ويمكن لاحقًا منح نفس الصلاحية لأي دور مدير موقع آخر
        | من صفحة الأدوار والصلاحيات.
        |
        */

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'Branch Manager',
                'Factory Manager',
            ])
            ->get()
            ->each(
                fn (Role $role) =>
                    $role->givePermissionTo([
                        'chat.direct.start_location',
                    ])
            );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'chat.direct.start_all',
                'chat.direct.start_location',
            ])
            ->delete();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
};