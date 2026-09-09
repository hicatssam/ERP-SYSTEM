<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DefaultRolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $requiredPermissions = [
            'dashboard.view',
            'dashboard.procurement',

            'orders.view',
            'orders.create',
            'orders.edit',
            'orders.confirm',
            'orders.cancel',

            'system_currencies.view',
            'system_currencies.manage',
            'cake_orders.view',
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

            'showroom_sweets_requests.view',
            'showroom_sweets_requests.view_all',
            'showroom_sweets_requests.create',
            'showroom_sweets_requests.start',
            'showroom_sweets_requests.ready',
            'showroom_sweets_requests.dispatch',
            'showroom_sweets_requests.receive',
            'showroom_sweets_requests.reject',
            'showroom_sweets_requests.cancel',
            'showroom_sweets_requests.delete',

            'customers.view',
            'customers.view_all',
            'customers.create',
            'customers.update',
            'customers.delete',

            'employees.view',
            'employees.view_all',
            'employees.create',
            'employees.update',
            'employees.delete',

            'users.manage',
            'roles.manage',

            'inventory.view',
            'inventory.count',
            'inventory.adjust',

            'stock_requests.view',
            'stock_requests.create',
            'stock_requests.review',

            'stock_transfers.view',
            'stock_transfers.dispatch',
            'stock_transfers.receive',

            'products.view',
            'products.create',
            'products.update',

            'invoices.view',
            'invoices.create',
            'invoices.print',
            'invoices.cancel',

            'payments.record',
            'payments.verify',
            'payments.correct',
            'payments.refund',

            'cash_sessions.manage',

            'financial.dashboard.view',
            'financial.branch.view',
            'financial.global.view',
            'financial.sales.view',
            'financial.collections.view',
            'financial.outstanding.view',
            'financial.refunds.view',
            'financial.adjustments.create',
            'financial.adjustments.approve',
            'financial.periods.view',
            'financial.periods.open',
            'financial.periods.close',
            'financial.periods.override_close',
            'financial.reports.view',
            'financial.reports.export',

            'payment_methods.view',
            'payment_methods.manage',

            'reports.view',
            'reports.export',
            'reports.branch_sales',
            'reports.payment_methods',

            'locations.manage',
            'notifications.manage',
            'settings.manage',

            'receiving_invoices.view',

            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'suppliers.delete',

            'purchase_orders.view',
            'purchase_orders.create',
            'purchase_orders.update',
            'purchase_orders.approve',
            'purchase_orders.cancel',

            'goods_receipts.view',
            'goods_receipts.create',
            'goods_receipts.approve',

            'supplier_invoices.view',
            'supplier_invoices.create',
            'supplier_invoices.cancel',

            'supplier_payments.view',
            'supplier_payments.create',

            'purchase_returns.view',
            'purchase_returns.create',
            'purchase_returns.approve',
            'purchase_returns.cancel',

            'procurement.exchange_rates.view',
            'procurement.exchange_rates.manage',
            'procurement.reports.view',

            'sales_channels.view',
            'sales_channels.create',
            'sales_channels.update',
            'sales_channels.delete',
            'sales_channels.activate',
            'sales_channels.reports',

            // Chat
            'chat.view',
            'chat.send',
            'chat.attachments',
            'chat.view_all_branches',
            'chat.manage',
            'chat.direct.start_all',
            'chat.direct.start_location',

            // Restaurant
            'restaurant.view',
            'restaurant.view_all_locations',
            'restaurant_pos.use',
            'restaurant_tables.view',
            'restaurant_tables.manage',
            'restaurant_tables.open_session',
            'restaurant_tables.close_session',

            // Kitchen + KDS
            'kitchen.view',
            'kitchen.view_all_locations',
            'kitchen.stations.manage',
            'kitchen.ticket.start',
            'kitchen.ticket.ready',
            'kitchen.ticket.serve',
            'kitchen.ticket.priority',
            'kds.view',

            'customer_display.view',

            // Sprint 08 — CRM + Loyalty + Delivery
            'crm.view',
            'crm.view_all',
            'crm.manage',
            'crm.interactions.manage',

            'loyalty.view',
            'loyalty.manage',
            'loyalty.adjust',
            'loyalty.redeem',

            'delivery.view',
            'delivery.view_all_locations',
            'delivery.zones.manage',
            'delivery.create',
            'delivery.assign',
            'delivery.update_status',

            // Recipes + Production + Quality
            'recipes.view',
            'recipes.manage',
            'recipes.approve',
            'recipes.cost.view',
            'production.view',
            'production.view_all_locations',
            'production.create',
            'production.release',
            'production.start',
            'production.finish',
            'production.cancel',
            'production.cost.view',
            'quality_control.view',
            'quality_control.decide',
        ];

        foreach ($requiredPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'General Manager' => [
                'dashboard.view',
                'dashboard.procurement',
                'orders.view',
                'cake_orders.view',
                'showroom_sweets_requests.view',
                'showroom_sweets_requests.view_all',
                'customers.view',
                'customers.view_all',
                'employees.view',
                'employees.view_all',
                'inventory.view',
                'stock_requests.view',
                'stock_transfers.view',
                'products.view',
                'invoices.view',
                'invoices.print',
                'financial.dashboard.view',
                'financial.branch.view',
                'financial.global.view',
                'financial.sales.view',
                'financial.collections.view',
                'financial.outstanding.view',
                'financial.refunds.view',
                'financial.periods.view',
                'financial.reports.view',
                'financial.reports.export',
                'reports.view',
                'reports.export',
                'reports.branch_sales',
                'reports.payment_methods',
                'payment_methods.view',
                'suppliers.view',
                'purchase_orders.view',
                'goods_receipts.view',
                'supplier_invoices.view',
                'supplier_payments.view',
                'purchase_returns.view',
                'receiving_invoices.view',
                'procurement.exchange_rates.view',
                'procurement.reports.view',
                'sales_channels.view',
                'sales_channels.reports',
                'customer_display.view',


                'system_currencies.view',
                'system_currencies.manage',
                // Chat: all branch channels
                'chat.view',
                'chat.send',
                'chat.attachments',
                'chat.view_all_branches',
                'chat.direct.start_all',

                // Restaurant: read across branches
                'restaurant.view',
                'restaurant.view_all_locations',
                'restaurant_tables.view',

                // Kitchen read across branches
                'kitchen.view',
                'kitchen.view_all_locations',
                'kds.view',

                // Production visibility
                'recipes.view',
                'recipes.cost.view',
                'production.view',
                'production.view_all_locations',
                'production.cost.view',
                'quality_control.view',

                
                // Sprint 08 — CRM
                'crm.view',
                'crm.view_all',
                'crm.manage',
                'crm.interactions.manage',

                // Sprint 08 — Loyalty
                'loyalty.view',
                'loyalty.manage',
                'loyalty.adjust',
                'loyalty.redeem',

                // Sprint 08 — Delivery
                'delivery.view',
                'delivery.view_all_locations',
                'delivery.zones.manage',
                'delivery.create',
                'delivery.assign',
                'delivery.update_status',
            ],

            'Branch Manager' => [
                'dashboard.view',
                'orders.view',
                'orders.create',
                'orders.edit',
                'orders.confirm',
                'orders.cancel',
                'cake_orders.view',
                'cake_orders.create',
                'cake_orders.edit',
                'cake_orders.receive',
                'showroom_sweets_requests.view',
                'showroom_sweets_requests.create',
                'showroom_sweets_requests.receive',
                'showroom_sweets_requests.cancel',
                'customers.view',
                'customers.create',
                'customers.update',
                'employees.view',
                'inventory.view',
                'stock_requests.view',
                'stock_requests.create',
                'stock_transfers.view',
                'stock_transfers.receive',
                'products.view',
                'invoices.view',
                'invoices.create',
                'invoices.print',
                'invoices.cancel',
                'payments.record',
                'payments.verify',
                'cash_sessions.manage',
                'financial.dashboard.view',
                'financial.branch.view',
                'financial.sales.view',
                'financial.collections.view',
                'financial.outstanding.view',
                'financial.refunds.view',
                'payment_methods.view',
                'reports.view',
                'reports.branch_sales',
                'reports.payment_methods',
                'customer_display.view',

                // Chat: own branch only
                'chat.view',
                'chat.send',
                'chat.attachments',
                'chat.direct.start_location',

                // Restaurant
                'restaurant.view',
                'restaurant_pos.use',
                'restaurant_tables.view',
                'restaurant_tables.manage',
                'restaurant_tables.open_session',
                'restaurant_tables.close_session',

                // Kitchen
                'kitchen.view',
                'kitchen.stations.manage',
                'kitchen.ticket.start',
                'kitchen.ticket.ready',
                'kitchen.ticket.serve',
                'kitchen.ticket.priority',
                'kds.view',
                // Sprint 08 — CRM
                'crm.view',
                'crm.manage',
                'crm.interactions.manage',

                // Sprint 08 — Loyalty
                'loyalty.view',
                'loyalty.redeem',

                // Sprint 08 — Delivery
                'delivery.view',
                'delivery.zones.manage',
                'delivery.create',
                'delivery.assign',
                'delivery.update_status',
            ],

            'Branch Employee' => [
                'dashboard.view',
                'orders.view',
                'orders.create',
                'orders.edit',
                'cake_orders.view',
                'cake_orders.create',
                'cake_orders.receive',
                'showroom_sweets_requests.view',
                'showroom_sweets_requests.create',
                'showroom_sweets_requests.receive',
                'customers.view',
                'customers.create',
                'customers.update',
                'inventory.view',
                'stock_requests.view',
                'stock_requests.create',
                'stock_transfers.view',
                'stock_transfers.receive',
                'products.view',
                'invoices.view',
                'invoices.create',
                'invoices.print',
                'payments.record',
                'payment_methods.view',

                // Restaurant
                'restaurant.view',
                'restaurant_pos.use',
                'restaurant_tables.view',
                'restaurant_tables.open_session',
                'restaurant_tables.close_session',
                'kitchen.view',
            ],

            'Cashier' => [
                'customer_display.view',
                'dashboard.view',
                'orders.view',
                'orders.create',
                'orders.edit',
                'orders.confirm',
                'cake_orders.view',
                'cake_orders.create',
                'customers.view',
                'customers.create',
                'customers.update',
                'products.view',
                'invoices.view',
                'invoices.create',
                'invoices.print',
                'payments.record',
                'payment_methods.view',
                'cash_sessions.manage',

                // Restaurant
                'restaurant.view',
                'restaurant_pos.use',
                'restaurant_tables.view',
                'restaurant_tables.open_session',
                'restaurant_tables.close_session',
                'kitchen.view',
                // Sprint 08 — CRM + Loyalty + Delivery
                'crm.view',
                'loyalty.view',
                'loyalty.redeem',
                'delivery.view',
                'delivery.create',
            ],

            'Waiter' => [
                'dashboard.view',
                'orders.view',
                'orders.create',
                'orders.edit',
                'customers.view',
                'products.view',
                'restaurant.view',
                'restaurant_pos.use',
                'restaurant_tables.view',
                'restaurant_tables.open_session',
                'restaurant_tables.close_session',
                'kitchen.view',
                'kitchen.ticket.serve',
            ],

            'Kitchen Staff' => [
                'dashboard.view',
                'orders.view',
                'products.view',
                'kitchen.view',
                'kitchen.ticket.start',
                'kitchen.ticket.ready',
                'kds.view',
                'customer_display.view',
            ],

            // Sprint 08 — Delivery operational role
            'Delivery Driver' => [
                'delivery.view',
                'delivery.update_status',
            ],

            'Factory Manager' => [
                'dashboard.view',
                'cake_orders.view',
                'cake_orders.review',
                'cake_orders.accept',
                'cake_orders.reject',
                'cake_orders.request_modification',
                'cake_orders.schedule',
                'showroom_sweets_requests.view',
                'showroom_sweets_requests.start',
                'showroom_sweets_requests.reject',
                'inventory.view',
                'stock_requests.view',
                'stock_requests.review',
                'stock_transfers.view',
                'products.view',
                'receiving_invoices.view',

                // Direct chat: factory users only
                'chat.direct.start_location',

                // Recipes + Production
                'recipes.view',
                'recipes.manage',
                'recipes.approve',
                'recipes.cost.view',
                'production.view',
                'production.create',
                'production.release',
                'production.start',
                'production.finish',
                'production.cancel',
                'production.cost.view',
                'quality_control.view',
            ],

            'Production Employee' => [
                'dashboard.view',
                'cake_orders.view',
                'cake_orders.prepare',
                'showroom_sweets_requests.view',
                'showroom_sweets_requests.ready',
                'inventory.view',
                'products.view',

                'recipes.view',
                'production.view',
                'production.create',
                'production.start',
                'production.finish',
            ],

            'Cake Designer' => [
                'dashboard.view',
                'cake_orders.view',
                'cake_orders.decorate',
            ],

            'Quality Control' => [
                'dashboard.view',
                'cake_orders.view',
                'cake_orders.quality_check',
                'production.view',
                'quality_control.view',
                'quality_control.decide',
            ],

            'Dispatcher' => [
                'dashboard.view',
                'cake_orders.view',
                'cake_orders.dispatch',
                'showroom_sweets_requests.view',
                'showroom_sweets_requests.dispatch',
            ],

            'Inventory Manager' => [
                'dashboard.view',
                'inventory.view',
                'inventory.count',
                'inventory.adjust',
                'stock_requests.view',
                'stock_requests.review',
                'stock_transfers.view',
                'stock_transfers.dispatch',
                'stock_transfers.receive',
                'products.view',
                'purchase_orders.view',
                'goods_receipts.view',
                'goods_receipts.create',
                'goods_receipts.approve',
                'supplier_invoices.view',
                'receiving_invoices.view',
                'production.view',
            ],

            'Accountant' => [
                   'dashboard.view',

    // الطلبات



                 'orders.view',
    'orders.confirm',
                'dashboard.procurement',
                'financial.dashboard.view',
                'financial.branch.view',
                'financial.global.view',
                'financial.sales.view',
                'financial.collections.view',
                'financial.outstanding.view',
                'financial.refunds.view',
                'financial.adjustments.create',
                'financial.adjustments.approve',
                'financial.periods.view',
                'financial.periods.open',
                'financial.periods.close',
                'financial.reports.view',
                'financial.reports.export',
                'invoices.view',
                'invoices.print',
                'invoices.cancel',
                'payments.record',
                'payments.verify',
                'payments.correct',
                'payments.refund',
                'payment_methods.view',
                'reports.view',
                'reports.export',
                'reports.branch_sales',
                'reports.payment_methods',
                'suppliers.view',
                'purchase_orders.view',
                'goods_receipts.view',
                'supplier_invoices.view',
                'supplier_invoices.cancel',
                'supplier_payments.view',
                'supplier_payments.create',
                'purchase_returns.view',
                'receiving_invoices.view',
                'procurement.exchange_rates.view',
                'procurement.exchange_rates.manage',
                'procurement.reports.view',
                'sales_channels.view',
                'sales_channels.reports',

                'recipes.view',
                'recipes.cost.view',
                'production.view',
                'production.cost.view',
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }

        $admin = Role::findOrCreate('Admin', 'web');
        $admin->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->get()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}