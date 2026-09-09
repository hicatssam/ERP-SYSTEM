<?php

namespace Database\Seeders;

use App\Models\KitchenStation;
use App\Models\Location;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class KitchenModuleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $permissions = [
            'kitchen.view',
            'kitchen.view_all_locations',
            'kitchen.stations.manage',
            'kitchen.ticket.start',
            'kitchen.ticket.ready',
            'kitchen.ticket.serve',
            'kitchen.ticket.priority',
            'kds.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        $this->grantIfRoleExists(
            'General Manager',
            [
                'kitchen.view',
                'kitchen.view_all_locations',
                'kds.view',
            ]
        );

        $this->grantIfRoleExists(
            'Branch Manager',
            [
                'kitchen.view',
                'kitchen.stations.manage',
                'kitchen.ticket.start',
                'kitchen.ticket.ready',
                'kitchen.ticket.serve',
                'kitchen.ticket.priority',
                'kds.view',
            ]
        );

        $this->grantIfRoleExists(
            'Branch Employee',
            [
                'kitchen.view',
            ]
        );

        $this->grantIfRoleExists(
            'Cashier',
            [
                'kitchen.view',
            ]
        );

        $this->grantIfRoleExists(
            'Waiter',
            [
                'kitchen.view',
                'kitchen.ticket.serve',
            ]
        );

        $kitchenStaff = Role::findOrCreate(
            'Kitchen Staff',
            'web'
        );

        $kitchenStaffPermissions = array_filter([
            Permission::findOrCreate('dashboard.view', 'web')->name,
            Permission::findOrCreate('orders.view', 'web')->name,
            Permission::findOrCreate('products.view', 'web')->name,
            'kitchen.view',
            'kitchen.ticket.start',
            'kitchen.ticket.ready',
            'kds.view',
        ]);

        $kitchenStaff->givePermissionTo(
            $kitchenStaffPermissions
        );

        foreach (
            [
                'Admin',
                'super-admin',
            ] as $adminRole
        ) {
            $role = Role::query()
                ->where('guard_name', 'web')
                ->where('name', $adminRole)
                ->first();

            if ($role) {
                $role->givePermissionTo(
                    $permissions
                );
            }
        }

        if (
            Schema::hasTable('kitchen_stations')
        ) {
            Location::query()
                ->branches()
                ->active()
                ->get([
                    'id',
                ])
                ->each(
                    function (Location $location): void {
                        if (
                            KitchenStation::query()
                                ->forLocation($location->id)
                                ->exists()
                        ) {
                            return;
                        }

                        KitchenStation::query()->create([
                            'location_id' => $location->id,
                            'name' => 'المطبخ الرئيسي',
                            'code' => 'MAIN',
                            'description' => 'المحطة الافتراضية للمطبخ.',
                            'target_minutes' => 15,
                            'is_default' => true,
                            'is_active' => true,
                            'sort_order' => 10,
                            'created_by' => null,
                        ]);
                    }
                );
        }

        $settings = [
            [
                'key' => 'kds_poll_seconds',
                'value' => '3',
                'type' => 'integer',
                'group' => 'kitchen',
                'label' => 'تحديث شاشة المطبخ كل',
                'description' => 'عدد الثواني بين كل تحديث تلقائي لشاشة KDS.',
            ],
            [
                'key' => 'kds_warning_minutes',
                'value' => '10',
                'type' => 'integer',
                'group' => 'kitchen',
                'label' => 'تنبيه التأخير بعد (دقيقة)',
                'description' => 'يظهر الطلب بحالة تحذير بعد تجاوز هذه المدة.',
            ],
            [
                'key' => 'kds_critical_minutes',
                'value' => '20',
                'type' => 'integer',
                'group' => 'kitchen',
                'label' => 'تأخير حرج بعد (دقيقة)',
                'description' => 'يظهر الطلب كمتأخر بشكل حرج بعد هذه المدة.',
            ],
            [
                'key' => 'kds_sound_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'kitchen',
                'label' => 'صوت الطلب الجديد في KDS',
                'description' => 'تشغيل تنبيه صوتي عند وصول تذكرة جديدة للمطبخ.',
            ],
            [
                'key' => 'kds_sound_volume',
                'value' => '70',
                'type' => 'integer',
                'group' => 'kitchen',
                'label' => 'مستوى صوت KDS',
                'description' => 'من 0 إلى 100.',
            ],
            [
                'key' => 'kitchen_require_served_before_complete',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'kitchen',
                'label' => 'منع إكمال الطلب قبل التسليم',
                'description' => 'إذا كان مفعلاً، لا يمكن إكمال طلب المطعم قبل إنهاء جميع تذاكر المطبخ وتسليمها.',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::query()->updateOrCreate(
                [
                    'key' => $setting['key'],
                ],
                $setting
            );
        }

        SystemSetting::flushCache();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    private function grantIfRoleExists(
        string $roleName,
        array $permissions
    ): void {
        $role = Role::query()
            ->where('guard_name', 'web')
            ->where('name', $roleName)
            ->first();

        if ($role) {
            $role->givePermissionTo(
                $permissions
            );
        }
    }
}
