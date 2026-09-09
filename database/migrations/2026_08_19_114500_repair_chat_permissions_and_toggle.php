<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Chat permissions
        |--------------------------------------------------------------------------
        */

        $chatPermissions = [
            'chat.view',
            'chat.send',
            'chat.attachments',
            'chat.view_all_branches',
            'chat.manage',
        ];

        foreach ($chatPermissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Default role grants
        |--------------------------------------------------------------------------
        */

        $admin = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'Admin',
                'super-admin',
                'Super Admin',
            ])
            ->get();

        foreach ($admin as $role) {
            $role->givePermissionTo(
                $chatPermissions
            );
        }

        $generalManagers = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'General Manager',
                'general-manager',
                'general_manager',
            ])
            ->get();

        foreach ($generalManagers as $role) {
            $role->givePermissionTo([
                'chat.view',
                'chat.send',
                'chat.attachments',
                'chat.view_all_branches',
            ]);
        }

        $branchManagers = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'Branch Manager',
                'branch-manager',
                'branch_manager',
            ])
            ->get();

        foreach ($branchManagers as $role) {
            $role->givePermissionTo([
                'chat.view',
                'chat.send',
                'chat.attachments',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | System setting: chat enabled
        |--------------------------------------------------------------------------
        |
        | مهم:
        | لا نستخدم updateOrInsert حتى لا نعيد تفعيل المحادثات مستقبلًا
        | إذا قام العميل بتعطيلها بنفسه.
        |
        */

        $exists = DB::table('system_settings')
            ->where('key', 'chat_enabled')
            ->exists();

        if (! $exists) {
            DB::table('system_settings')->insert([
                'key' => 'chat_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'modules',
                'label' => 'تفعيل نظام المحادثات',
                'description' => 'تشغيل أو إيقاف نظام المحادثات الداخلية بين الإدارة والفروع.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    public function down(): void
    {
        /*
         * نحذف فقط إعداد التفعيل.
         * لا نحذف صلاحيات الشات لأن الأدوار/المستخدمين قد تكون مرتبطة بها.
         */
        DB::table('system_settings')
            ->where('key', 'chat_enabled')
            ->delete();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
};