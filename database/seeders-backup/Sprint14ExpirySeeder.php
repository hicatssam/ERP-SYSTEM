<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class Sprint14ExpirySeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'inventory.expiry-alerts.view','inventory.expiry-alerts.receive','inventory.expiry-alerts.receive_all',
            'inventory.expiry-alerts.run','inventory.expiry-alerts.settings',
        ];

        foreach ($permissions as $name) Permission::firstOrCreate(['name'=>$name,'guard_name'=>'web']);
        if ($admin = Role::query()->where('name','Admin')->first()) $admin->givePermissionTo($permissions);

        $defaults = [
            'inventory_expiry_monitoring_enabled'=>['1','boolean','تفعيل مراقبة الصلاحية'],
            'inventory_expiry_alert_days'=>['60,30,7,0','string','مراحل تنبيه الصلاحية'],
            'inventory_expiry_notify_database'=>['1','boolean','إشعار داخل النظام'],
            'inventory_expiry_notify_email'=>['0','boolean','تنبيه البريد الإلكتروني'],
            'inventory_expiry_notify_whatsapp'=>['0','boolean','تنبيه WhatsApp'],
            'inventory_expiry_block_expired_stock'=>['1','boolean','منع المخزون المنتهي'],
        ];

        foreach ($defaults as $key => [$default,$type,$label]) {
            $current = SystemSetting::query()->where('key',$key)->value('value');
            SystemSetting::query()->updateOrCreate(['key'=>$key], [
                'value'=>$current ?? $default,'type'=>$type,'group'=>'inventory','label'=>$label,
                'description'=>'إعداد Sprint 14 لمراقبة صلاحية دفعات المخزون.',
            ]);
        }

        SystemSetting::flushCache();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
