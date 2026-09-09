<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CakeOrderPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'cake_orders.view',
            'cake_orders.view_all',
            'cake_orders.create',
            'cake_orders.edit',
            'cake_orders.delete',
            'cake_orders.review',
            'cake_orders.accept',
            'cake_orders.reject',
            'cake_orders.request_modification',
            'cake_orders.schedule',
            'cake_orders.prepare',
            'cake_orders.decorate',
            'cake_orders.quality_check',
            'cake_orders.dispatch',
            'cake_orders.receive',
            'cake_orders.complete',
            'cake_orders.cancel',
            'cake_orders.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->grant('Admin', $permissions);
        $this->grant('General Manager', $permissions);

        $this->grant('Branch Manager', [
            'cake_orders.view',
            'cake_orders.create',
            'cake_orders.edit',
            'cake_orders.receive',
            'cake_orders.complete',
            'cake_orders.cancel',
        ]);

        $this->grant('Branch Employee', [
            'cake_orders.view',
            'cake_orders.create',
            'cake_orders.receive',
            'cake_orders.complete',
        ]);

        $this->grant('Cashier', [
            'cake_orders.view',
            'cake_orders.create',
            'cake_orders.complete',
        ]);

        $this->grant('Factory Manager', [
            'cake_orders.view',
            'cake_orders.review',
            'cake_orders.accept',
            'cake_orders.reject',
            'cake_orders.request_modification',
            'cake_orders.schedule',
            'cake_orders.prepare',
            'cake_orders.decorate',
            'cake_orders.quality_check',
            'cake_orders.dispatch',
            'cake_orders.cancel',
        ]);

        $this->grant('Production Employee', [
            'cake_orders.view',
            'cake_orders.prepare',
        ]);

        $this->grant('Cake Designer', [
            'cake_orders.view',
            'cake_orders.decorate',
        ]);

        $this->grant('Quality Control', [
            'cake_orders.view',
            'cake_orders.quality_check',
        ]);

        $this->grant('Dispatcher', [
            'cake_orders.view',
            'cake_orders.dispatch',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * يضيف صلاحيات الكيك فقط ولا يلمس أي صلاحيات أخرى للدور.
     *
     * @param array<int, string> $permissions
     */
    private function grant(string $roleName, array $permissions): void
    {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permissions);
    }
}
