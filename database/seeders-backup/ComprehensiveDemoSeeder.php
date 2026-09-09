<?php

namespace Database\Seeders;

use App\Enums\RecipeStatus;
use App\Models\Employee;
use App\Models\EmployeeLocation;
use App\Models\Location;
use App\Models\Module;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\RestaurantArea;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Builds a coherent, disposable demo database for end-to-end testing.
 * Run this only after migrate:fresh on a dedicated demo database.
 */
class ComprehensiveDemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Demo@2026!';

    public function run(): void
    {
        $this->command?->warn('إنشاء قاعدة تجريبية شاملة — لا تستخدم هذا Seeder على بيانات الإنتاج.');

        $this->call([
            DatabaseSeeder::class,
            ModuleRegistrySeeder::class,
            DefaultRolePermissionsSeeder::class,
            SystemCurrencySeeder::class,
            SystemCurrencyPermissionSeeder::class,
            NewPermissionsSeeder::class,
            CakeOrderPermissionsSeeder::class,
            ShowroomRequestsPermissionsSeeder::class,
            StockRequestPermissionRepairSeeder::class,
            SalesChannelPermissionSeeder::class,
            SalesChannelSeeder::class,
            SalesChannelModuleSeeder::class,
            ProcurementModuleSeeder::class,
            RestaurantPermissionsSeeder::class,
            RestaurantMenuPermissionSeeder::class,
            RestaurantMenuDemoSeeder::class,
            KitchenModuleSeeder::class,
            ProductionPermissionsSeeder::class,
            ProductionModuleSeeder::class,
            AttendancePermissionsSeeder::class,
            AttendanceDefaultsSeeder::class,
            PayrollPermissionsSeeder::class,
            PayrollSprint13PermissionsSeeder::class,
            CostingPermissionsSeeder::class,
            GrowthModuleSeeder::class,
            Sprint14ExpirySeeder::class,
            BrandingThemeSettingsSeeder::class,
            ClientOnboardingSettingsSeeder::class,
            CustomerDisplaySettingsSeeder::class,
            CustomerOrderDisplayPermissionSeeder::class,
            PrintBrandingDefaultsSeeder::class,
        ]);

        $this->enableImplementedModules();
        $users = $this->seedOperationalUsers();
        $this->seedRestaurantLayout();
        $this->seedProductionScenario($users['factory_manager'] ?? $users['admin']);
        $this->grantAdministratorsEveryPermission();
        $this->printAndValidateCoverage();

        $this->command?->newLine();
        $this->command?->info('اكتمل إنشاء بيانات العرض الشاملة.');
        $this->command?->line('كلمة مرور جميع مستخدمي demo: '.self::DEMO_PASSWORD);
    }

    private function enableImplementedModules(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        Module::query()->get()->each(function (Module $module): void {
            if ($module->isImplemented()) {
                $module->forceFill(['is_active' => true])->save();
            }
        });
    }

    /** @return array<string, User> */
    private function seedOperationalUsers(): array
    {
        $admin = User::query()->whereHas('roles', fn ($query) => $query->whereIn('name', ['Admin', 'super-admin']))->first()
            ?? User::query()->firstOrFail();

        $branch = Location::query()->where('type', 'branch')->where('is_active', true)->first()
            ?? Location::query()->where('is_active', true)->firstOrFail();
        $factory = Location::query()->where('type', 'factory')->where('is_active', true)->first() ?? $branch;

        $definitions = [
            'branch_manager' => ['DEMO-BM-001', 'مدير الفرع التجريبي', 'demo.branch.manager', 'demo.branch.manager@dahab.test', 'Branch Manager', $branch],
            'cashier' => ['DEMO-CA-001', 'كاشير تجريبي', 'demo.cashier', 'demo.cashier@dahab.test', 'Cashier', $branch],
            'waiter' => ['DEMO-WA-001', 'نادل تجريبي', 'demo.waiter', 'demo.waiter@dahab.test', 'Waiter', $branch],
            'kitchen' => ['DEMO-KI-001', 'موظف مطبخ تجريبي', 'demo.kitchen', 'demo.kitchen@dahab.test', 'Kitchen Staff', $branch],
            'inventory' => ['DEMO-IN-001', 'مسؤول مخزون تجريبي', 'demo.inventory', 'demo.inventory@dahab.test', 'Inventory Manager', $branch],
            'accountant' => ['DEMO-AC-001', 'محاسب تجريبي', 'demo.accountant', 'demo.accountant@dahab.test', 'Accountant', $branch],
            'factory_manager' => ['DEMO-FM-001', 'مدير مصنع تجريبي', 'demo.factory.manager', 'demo.factory.manager@dahab.test', 'Factory Manager', $factory],
            'production' => ['DEMO-PR-001', 'موظف إنتاج تجريبي', 'demo.production', 'demo.production@dahab.test', 'Production Employee', $factory],
            'quality' => ['DEMO-QC-001', 'موظف جودة تجريبي', 'demo.quality', 'demo.quality@dahab.test', 'Quality Control', $factory],
        ];

        $result = ['admin' => $admin];
        foreach ($definitions as $key => [$number, $name, $username, $email, $roleName, $location]) {
            $role = Role::findOrCreate($roleName, 'web');
            $employee = Employee::query()->updateOrCreate(
                ['employee_number' => $number],
                [
                    'full_name' => $name,
                    'email' => $email,
                    'job_title' => $roleName,
                    'hire_date' => now()->subYear()->toDateString(),
                    'employment_status' => 'active',
                ]
            );

            EmployeeLocation::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'location_id' => $location->id],
                ['is_primary' => true, 'started_at' => now()->subYear()->toDateString(), 'ended_at' => null]
            );

            $user = User::query()->updateOrCreate(
                ['username' => $username],
                [
                    'employee_id' => $employee->id,
                    'email' => $email,
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'is_active' => true,
                    'must_change_password' => false,
                    'failed_login_attempts' => 0,
                    'login_locked_until' => null,
                ]
            );
            $user->syncRoles([$role]);
            $result[$key] = $user;
        }

        return $result;
    }

    private function seedRestaurantLayout(): void
    {
        if (! Schema::hasTable('restaurant_tables')) {
            return;
        }

        $branches = Location::query()->where('is_active', true)->where('type', 'branch')->get();
        foreach ($branches as $branch) {
            $areaId = null;
            if (Schema::hasTable('restaurant_areas')) {
                $area = RestaurantArea::query()->updateOrCreate(
                    ['location_id' => $branch->id, 'name' => 'الصالة الرئيسية'],
                    ['code' => 'MAIN', 'sort_order' => 10, 'is_active' => true]
                );
                $areaId = $area->id;
            }

            foreach (range(1, 8) as $number) {
                RestaurantTable::query()->updateOrCreate(
                    ['location_id' => $branch->id, 'code' => sprintf('T%02d', $number)],
                    [
                        'area_id' => $areaId,
                        'name' => 'طاولة '.$number,
                        'capacity' => $number % 3 === 0 ? 6 : 4,
                        'sort_order' => $number * 10,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedProductionScenario(User $actor): void
    {
        if (! Schema::hasTable('recipes') || ! Schema::hasTable('recipe_items')) {
            return;
        }

        $finished = Product::query()->where('is_active', true)->orderBy('id')->first();
        $ingredients = Product::query()->where('is_active', true)->whereKeyNot($finished?->id)->limit(3)->get();
        if (! $finished || $ingredients->isEmpty()) {
            return;
        }

        $recipePayload = $this->onlyExistingColumns('recipes', [
            'code' => 'DEMO-RECIPE-001',
            'product_id' => $finished->id,
            'name' => 'وصفة تجريبية متكاملة - '.$finished->name,
            'version' => 1,
            'yield_quantity' => 10,
            'labor_cost_per_batch' => 25,
            'overhead_percent' => 8,
            'status' => RecipeStatus::Approved->value,
            'is_active' => true,
            'notes' => 'وصفة مخصصة لاختبارات النظام الشاملة.',
            'created_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'updated_by' => $actor->id,
            'activated_by' => $actor->id,
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recipe = Recipe::withTrashed()->where('code', 'DEMO-RECIPE-001')->first();
        if ($recipe) {
            if ($recipe->trashed()) {
                $recipe->restore();
            }
            DB::table('recipes')->where('id', $recipe->id)->update(Arr::except($recipePayload, ['created_at']));
            $recipe->refresh();
        } else {
            $recipeId = DB::table('recipes')->insertGetId($recipePayload);
            $recipe = Recipe::query()->findOrFail($recipeId);
        }

        foreach ($ingredients as $index => $ingredient) {
            $payload = $this->onlyExistingColumns('recipe_items', [
                'recipe_id' => $recipe->id,
                'ingredient_product_id' => $ingredient->id,
                'quantity' => 1 + ($index * 0.5),
                'expected_waste_percent' => 2,
                'waste_percent' => 2,
                'unit_snapshot' => $ingredient->unit ?? 'piece',
                'stage' => $index === 0 ? 'تحضير' : 'خلط',
                'is_optional' => false,
                'sort_order' => ($index + 1) * 10,
                'notes' => 'مكون تجريبي',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('recipe_items')->updateOrInsert(
                ['recipe_id' => $recipe->id, 'ingredient_product_id' => $ingredient->id],
                Arr::except($payload, ['recipe_id', 'ingredient_product_id', 'created_at'])
            );
        }

        if (Schema::hasTable('production_orders')) {
            $factory = Location::query()->where('type', 'factory')->where('is_active', true)->first()
                ?? Location::query()->where('is_active', true)->firstOrFail();
            $order = $this->onlyExistingColumns('production_orders', [
                'production_number' => 'DEMO-PO-0001',
                'location_id' => $factory->id,
                'recipe_id' => $recipe->id,
                'product_id' => $finished->id,
                'recipe_version' => 1,
                'status' => 'draft',
                'quality_required' => true,
                'recipe_yield_quantity' => 10,
                'planned_output_quantity' => 20,
                'estimated_material_cost' => 100,
                'planned_material_cost' => 100,
                'planned_labor_cost' => 25,
                'planned_overhead_cost' => 8,
                'planned_total_cost' => 133,
                'planned_unit_cost' => 6.65,
                'labor_cost' => 25,
                'overhead_percent_snapshot' => 8,
                'overhead_cost' => 8,
                'total_cost' => 133,
                'unit_cost' => 6.65,
                'cost_is_complete' => false,
                'planned_at' => now()->addDay(),
                'created_by' => $actor->id,
                'notes' => 'أمر إنتاج تجريبي لاختبار دورة الإنتاج.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('production_orders')->updateOrInsert(
                ['production_number' => 'DEMO-PO-0001'],
                Arr::except($order, ['production_number', 'created_at'])
            );
        }
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function onlyExistingColumns(string $table, array $values): array
    {
        $columns = array_flip(Schema::getColumnListing($table));

        return array_intersect_key($values, $columns);
    }

    private function grantAdministratorsEveryPermission(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = Permission::query()->where('guard_name', 'web')->get();
        Role::query()->whereIn('name', ['Admin', 'super-admin'])->get()
            ->each(fn (Role $role) => $role->syncPermissions($permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function printAndValidateCoverage(): void
    {
        $tables = [
            'locations', 'employees', 'users', 'roles', 'permissions',
            'categories', 'products', 'location_products', 'inventories',
            'customers', 'orders', 'order_items', 'invoices', 'payments',
            'payment_methods', 'suppliers', 'purchase_orders',
            'restaurant_tables', 'kitchen_stations', 'recipes',
            'production_orders', 'report_schedules', 'notifications',
        ];

        $rows = [];
        $missing = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $rows[] = [$table, 'لا يوجد جدول', 'SKIP'];
                continue;
            }

            $count = DB::table($table)->count();
            $required = in_array($table, [
                'locations', 'employees', 'users', 'roles', 'permissions',
                'categories', 'products', 'location_products', 'inventories',
                'customers', 'orders', 'order_items', 'payment_methods',
                'restaurant_tables', 'kitchen_stations', 'recipes', 'production_orders',
            ], true);

            $status = $count > 0 ? 'OK' : ($required ? 'MISSING' : 'EMPTY');
            $rows[] = [$table, $count, $status];
            if ($required && $count === 0) {
                $missing[] = $table;
            }
        }

        $this->command?->table(['الجدول', 'السجلات', 'الحالة'], $rows);

        if ($missing !== []) {
            throw new RuntimeException('بيانات تجريبية إلزامية مفقودة في: '.implode(', ', $missing));
        }
    }
}
