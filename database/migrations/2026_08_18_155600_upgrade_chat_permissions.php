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
            'chat.view',
            'chat.send',
            'chat.attachments',
            'chat.view_all_branches',
            'chat.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | مدير النظام
        |--------------------------------------------------------------------------
        */

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'Admin',
                'admin',
                'super-admin',
                'Super Admin',
            ])
            ->get()
            ->each(
                fn (Role $role) =>
                    $role->givePermissionTo(
                        $permissions
                    )
            );

        /*
        |--------------------------------------------------------------------------
        | المدير العام
        |--------------------------------------------------------------------------
        |
        | يرى كل قنوات الفروع ويرسل رسائل ومرفقات،
        | لكنه لا يحصل على chat.manage افتراضيًا.
        |
        */

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'General Manager',
                'general-manager',
                'general_manager',
            ])
            ->get()
            ->each(
                fn (Role $role) =>
                    $role->givePermissionTo([
                        'chat.view',
                        'chat.send',
                        'chat.attachments',
                        'chat.view_all_branches',
                    ])
            );

        /*
        |--------------------------------------------------------------------------
        | مدير الفرع
        |--------------------------------------------------------------------------
        |
        | يرى قناة فرعه الرئيسي فقط.
        |
        */

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'Branch Manager',
                'branch-manager',
                'branch_manager',
            ])
            ->get()
            ->each(
                fn (Role $role) =>
                    $role->givePermissionTo([
                        'chat.view',
                        'chat.send',
                        'chat.attachments',
                    ])
            );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        /*
         * الصلاحيات الأربع الأخرى أضيفت في Migration سابق.
         * لذلك عند rollback نحذف فقط الصلاحية الجديدة في هذه المرحلة.
         */
        Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'chat.attachments')
            ->delete();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
};