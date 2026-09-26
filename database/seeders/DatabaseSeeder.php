<?php

namespace Database\Seeders;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeCompensationProfile;
use App\Models\EmployeeLocation;
use App\Models\EmployeePayrollAdjustment;
use App\Models\FinancialPeriod;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Location;
use App\Models\LocationPaymentMethod;
use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PayrollPeriod;
use App\Models\Product;
use App\Models\ReportSchedule;
use App\Models\RestaurantArea;
use App\Models\RestaurantTable;
use App\Models\SpecialCakeOrder;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\AttendanceRecord;
use App\Models\EmployeeShiftAssignment;
use App\Models\BusinessProfile;
use App\Models\Module;
use App\Models\ModuleBundle;
use App\Support\BusinessProfileRegistry;
use App\Support\ModuleRegistry;
use App\Services\PayrollService;
use App\Services\PayrollSettlementService;
use App\Services\ModuleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        $this->seedLocations();
        $this->seedRolesAndPermissions();
        $this->seedEmployeesAndUsers();
        $this->seedCategories();
        $this->seedProducts();
        $this->seedCurrencies();
        $this->seedPaymentMethods();
        $this->seedSystemSettings();
        $this->seedUnifiedFeatureDefaults();
        $this->seedCustomers();
        $this->seedFinancialPeriods();
        $this->seedCashSessions();
        $this->seedOrders();
        $this->seedSpecialCakeOrders();
        $this->seedStockCounts();
        $this->seedStockRequests();
        $this->seedReportSchedules();
        $this->seedActivityLogs();
        $this->seedNotifications();
        $this->seedBusinessProfilesAndModules();
        $this->seedUnifiedPermissionMatrix();
        $this->seedRestaurantAndKitchenDemo();
        $this->seedCatalogSupplyAndMenuDemo();
        $this->seedAttendanceAndPayrollDemo();
        $this->seedOperationalCoverageDemo();
        $this->seedMissingWorkflowDemo();
        $this->validateUnifiedDemo();

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Lightweight entry point for architecture tests.  It replaces the old
     * ModuleRegistrySeeder while keeping DatabaseSeeder as the only seeder file.
     */
    public function seedArchitectureRegistryOnly(): void
    {
        $this->seedBusinessProfilesAndModules();
    }

    // ─────────────────────────────────────────────────────────────
    // LOCATIONS
    // ─────────────────────────────────────────────────────────────
    private function seedLocations(): void
    {
        $locations = [
            ['name' => 'فرع دهب —  النصر',  'code' => 'B01', 'type' => 'branch',  'phone' => '02-2951234', 'address' => ' غزة شارع الإرسال',        'is_active' => true],
            ['name' => 'فرع دهب — النصيرات',    'code' => 'B02', 'type' => 'branch',  'phone' => '02-2962345', 'address' => 'ديرالبلح شارع الجمهورية',         'is_active' => true],
            ['name' => 'فرع دهب — دير البلح ',  'code' => 'B03', 'type' => 'branch',  'phone' => '02-2743456', 'address' => ' خانيونس شارع المدبسة',          'is_active' => true],
            ['name' => 'فرع دهب — خانيونس',    'code' => 'B04', 'type' => 'branch',  'phone' => '02-2224567', 'address' => 'غزة وسط المدينة',            'is_active' => true],
            ['name' => 'فرع دهب — مصر',     'code' => 'B05', 'type' => 'branch',  'phone' => '09-2385678', 'address' => 'مصر المركز',                  'is_active' => true],
            ['name' => 'مصنع دهب —  الشعبية', 'code' => 'FAC', 'type' => 'factory', 'phone' => '02-2956789', 'address' => ' الشعبية المنطقة الصناعية',    'is_active' => true],
        ];

        foreach ($locations as $l) {
            Location::firstOrCreate(['code' => $l['code']], $l);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // ROLES & PERMISSIONS
    // ─────────────────────────────────────────────────────────────
    private function seedRolesAndPermissions(): void
    {
        $permissions = [

    // Locations
    'locations.manage',

    // Customers
    'customers.view',
    'customers.view_all',
    'customers.create',
    'customers.update',
    'customers.delete',

    // Employees
    'employees.view',
    'employees.view_all',
    'employees.create',
    'employees.update',
    'employees.delete',
    'employees.manage',

    // Users & Roles
    'users.manage',
    'roles.manage',

    // Products
    'products.view',
    'products.create',
    'products.update',

    // Inventory
    'inventory.view',
    'inventory.adjust',
    'inventory.count',

    // Stock Requests
    'stock_requests.create',
    'stock_requests.review',

    // Stock Transfers
    'stock_transfers.dispatch',
    'stock_transfers.receive',

    // Orders
    'orders.view',
    'orders.create',
    'orders.cancel',

    // Cake Orders
    'cake_orders.create',
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
    'cake_orders.receive',

    // Payments
    'payments.record',
    'payments.verify',
    'payments.correct',
    'payments.refund',

    // Invoices
    'invoices.view',
    'invoices.create',
    'invoices.print',
    'invoices.cancel',

    // Financial
    'financial.dashboard.view',
    'financial.global.view',
    'financial.branch.view',
    'financial.periods.view',
    'financial.periods.open',
    'financial.periods.close',
    'financial.periods.override_close',
    'financial.adjustments.create',
    'financial.adjustments.approve',
    'financial.sales.view',
    'financial.collections.view',
    'financial.outstanding.view',
    'financial.refunds.view',
    'financial.reports.view',
    'financial.reports.export',

    // Reports
    'reports.view',
    'reports.export',

    // Settings
    'settings.manage',
];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        $branchManager = Role::firstOrCreate(['name' => 'Branch Manager', 'guard_name' => 'web']);
        $branchManager->syncPermissions([
            'products.view', 'inventory.view', 'inventory.adjust', 'inventory.count',
            'stock_requests.create',
            'orders.view', 'orders.create', 'orders.cancel',
            'cake_orders.create', 'cake_orders.view', 'cake_orders.receive',
            'payments.record', 'payments.verify',
            'invoices.view', 'invoices.create', 'invoices.print', 'invoices.cancel',
            'financial.branch.view', 'financial.dashboard.view', 'financial.periods.view',
            'reports.view', 'reports.export',
            'employees.manage',
        ]);

        $cashier = Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web']);
        $cashier->syncPermissions([
            'products.view', 'inventory.view',
            'orders.view', 'orders.create',
            'cake_orders.create', 'cake_orders.view',
            'payments.record',
            'invoices.view', 'invoices.create', 'invoices.print',
        ]);

        $factoryManager = Role::firstOrCreate(['name' => 'Factory Manager', 'guard_name' => 'web']);
        $factoryManager->syncPermissions([
            'products.view', 'inventory.view', 'inventory.adjust', 'inventory.count',
            'stock_requests.review', 'stock_transfers.dispatch',
            'cake_orders.view', 'cake_orders.review', 'cake_orders.accept',
            'cake_orders.reject', 'cake_orders.request_modification',
            'cake_orders.schedule', 'cake_orders.prepare',
            'cake_orders.decorate', 'cake_orders.quality_check', 'cake_orders.dispatch',
            'invoices.view', 'reports.view',
        ]);

        $accountant = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            'financial.dashboard.view', 'financial.global.view', 'financial.branch.view',
            'financial.periods.view', 'financial.periods.open', 'financial.periods.close',
            'financial.adjustments.create',
            'financial.sales.view', 'financial.collections.view',
            'financial.outstanding.view', 'financial.refunds.view',
            'financial.reports.view', 'financial.reports.export',
            'invoices.view', 'invoices.print',
            'reports.view', 'reports.export',
            'payments.verify',
        ]);

        $inventoryManager = Role::firstOrCreate(['name' => 'Inventory Manager', 'guard_name' => 'web']);
        $inventoryManager->syncPermissions([
            'products.view', 'inventory.view', 'inventory.adjust', 'inventory.count',
            'stock_requests.create', 'stock_requests.review',
            'stock_transfers.dispatch', 'stock_transfers.receive',
            'reports.view',
        ]);

        foreach (['General Manager', 'Branch Employee', 'Production Employee', 'Cake Designer', 'Quality Control', 'Dispatcher'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // EMPLOYEES & USERS
    // ─────────────────────────────────────────────────────────────
    private function seedEmployeesAndUsers(): void
    {
        $factory  = Location::where('code', 'FAC')->first();
        $branchB1 = Location::where('code', 'B01')->first();
        $branchB2 = Location::where('code', 'B02')->first();
        $branchB3 = Location::where('code', 'B03')->first();

        $staff = [
            // Admin
            [
                'emp_number' => 'EMP-001',
                'full_name'  => 'مدير النظام',
                'phone'      => '0599000001',
                'email'      => 'admin@dahabsweets.com',
                'job_title'  => 'مدير النظام',
                'location'   => $factory,
                'username'   => 'admin',
                'password'   => 'Admin@2024!',
                'role'       => 'Admin',
            ],
            // Factory Manager
            [
                'emp_number' => 'EMP-002',
                'full_name'  => 'يوسف المصري',
                'phone'      => '0599000002',
                'email'      => 'factory.mgr@dahabsweets.com',
                'job_title'  => 'مدير المصنع',
                'location'   => $factory,
                'username'   => 'factory_mgr',
                'password'   => 'Factory@2024!',
                'role'       => 'Factory Manager',
            ],
            // Branch Manager – Ramallah
            [
                'emp_number' => 'EMP-003',
                'full_name'  => 'سمير الرشيد',
                'phone'      => '0599000003',
                'email'      => 'branch.b01@dahabsweets.com',
                'job_title'  => 'مدير فرع',
                'location'   => $branchB1,
                'username'   => 'branch_b01',
                'password'   => 'Branch@2024!',
                'role'       => 'Branch Manager',
            ],
            // Branch Manager – Bireh
            [
                'emp_number' => 'EMP-004',
                'full_name'  => 'هنا العمر',
                'phone'      => '0599000004',
                'email'      => 'branch.b02@dahabsweets.com',
                'job_title'  => 'مدير فرع',
                'location'   => $branchB2,
                'username'   => 'branch_b02',
                'password'   => 'Branch@2024!',
                'role'       => 'Branch Manager',
            ],
            // Cashier – Ramallah
            [
                'emp_number' => 'EMP-005',
                'full_name'  => 'رنا حمدان',
                'phone'      => '0599000005',
                'email'      => 'cashier.b01@dahabsweets.com',
                'job_title'  => 'كاشير',
                'location'   => $branchB1,
                'username'   => 'cashier_b01',
                'password'   => 'Cashier@2024!',
                'role'       => 'Cashier',
            ],
            // Cashier – Bireh
            [
                'emp_number' => 'EMP-006',
                'full_name'  => 'تامر سلامة',
                'phone'      => '0599000006',
                'email'      => 'cashier.b02@dahabsweets.com',
                'job_title'  => 'كاشير',
                'location'   => $branchB2,
                'username'   => 'cashier_b02',
                'password'   => 'Cashier@2024!',
                'role'       => 'Cashier',
            ],
            // Accountant
            [
                'emp_number' => 'EMP-007',
                'full_name'  => 'ليلى أبو علي',
                'phone'      => '0599000007',
                'email'      => 'accountant@dahabsweets.com',
                'job_title'  => 'محاسبة',
                'location'   => $factory,
                'username'   => 'accountant',
                'password'   => 'Accountant@2024!',
                'role'       => 'Accountant',
            ],
            // Inventory Manager
            [
                'emp_number' => 'EMP-008',
                'full_name'  => 'عمر الجمال',
                'phone'      => '0599000008',
                'email'      => 'inventory@dahabsweets.com',
                'job_title'  => 'مدير مخزون',
                'location'   => $factory,
                'username'   => 'inventory_mgr',
                'password'   => 'Inventory@2024!',
                'role'       => 'Inventory Manager',
            ],
            // Cake Designer
            [
                'emp_number' => 'EMP-009',
                'full_name'  => 'دينا خليل',
                'phone'      => '0599000009',
                'email'      => 'designer@dahabsweets.com',
                'job_title'  => 'مصممة كيك',
                'location'   => $factory,
                'username'   => 'cake_designer',
                'password'   => 'Designer@2024!',
                'role'       => 'Cake Designer',
            ],
            // Branch Manager – Bethlehem
            [
                'emp_number' => 'EMP-010',
                'full_name'  => 'باسم حنا',
                'phone'      => '0599000010',
                'email'      => 'branch.b03@dahabsweets.com',
                'job_title'  => 'مدير فرع',
                'location'   => $branchB3,
                'username'   => 'branch_b03',
                'password'   => 'Branch@2024!',
                'role'       => 'Branch Manager',
            ],
        ];

        foreach ($staff as $staffIndex => $s) {
            $employmentStart = now()->subMonths(8 + ($staffIndex * 2))->startOfMonth()->toDateString();
            $employee = Employee::query()->updateOrCreate(
                ['employee_number' => $s['emp_number']],
                [
                    'full_name'         => $s['full_name'],
                    'phone'             => $s['phone'],
                    'email'             => $s['email'],
                    'job_title'         => $s['job_title'],
                    'hire_date'         => $employmentStart,
                    'employment_status' => 'active',
                ]
            );

            if ($s['location']) {
                EmployeeLocation::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'location_id' => $s['location']->id],
                    ['is_primary' => true, 'started_at' => $employmentStart, 'ended_at' => null]
                );
            }

            $user = User::query()->updateOrCreate(
                ['username' => $s['username']],
                [
                    'employee_id'          => $employee->id,
                    'email'                => $s['email'],
                    'password'             => Hash::make($s['password']),
                    'is_active'            => true,
                    'must_change_password' => false,
                ]
            );

            $user->syncRoles([$s['role']]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // CATEGORIES
    // ─────────────────────────────────────────────────────────────
    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Cakes',           'name_ar' => 'كيك',           'slug' => 'cakes',           'sort_order' => 1, 'icon_key' => 'cake',       'icon_color' => '#D97706'],
            ['name' => 'Gateaux',         'name_ar' => 'غاتوه',         'slug' => 'gateaux',         'sort_order' => 2, 'icon_key' => 'cookie',     'icon_color' => '#B45309'],
            ['name' => 'Oriental Sweets', 'name_ar' => 'حلويات شرقية', 'slug' => 'oriental-sweets', 'sort_order' => 3, 'icon_key' => 'baklava',    'icon_color' => '#CA8A04'],
            ['name' => 'Western Sweets',  'name_ar' => 'حلويات غربية', 'slug' => 'western-sweets',  'sort_order' => 4, 'icon_key' => 'croissant',  'icon_color' => '#DB2777'],
            ['name' => 'Chocolate',       'name_ar' => 'شوكولاتة',     'slug' => 'chocolate',       'sort_order' => 5, 'icon_key' => 'chocolate',  'icon_color' => '#7C2D12'],
            ['name' => 'Drinks',          'name_ar' => 'مشروبات',      'slug' => 'drinks',          'sort_order' => 6, 'icon_key' => 'coffee',     'icon_color' => '#0F766E'],
            ['name' => 'Milkshakes',      'name_ar' => 'ميلك شيك',     'slug' => 'milkshakes',      'sort_order' => 7, 'icon_key' => 'milkshake',  'icon_color' => '#9333EA'],
            ['name' => 'Gifts',           'name_ar' => 'هدايا',        'slug' => 'gifts',           'sort_order' => 8, 'icon_key' => 'gift',       'icon_color' => '#2563EB'],
        ];

        foreach ($categories as $c) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $c['slug']],
                array_merge(array_diff_key($c, ['icon_key' => true, 'icon_color' => true]), ['is_active' => true])
            );
            $this->upsertExisting('categories', ['id' => $category->id], [
                'icon_key' => $c['icon_key'], 'icon_color' => $c['icon_color'],
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PRODUCTS
    // ─────────────────────────────────────────────────────────────
    private function seedProducts(): void
    {
        $categories = Category::query()->whereIn('slug', [
            'cakes','gateaux','oriental-sweets','western-sweets','chocolate','drinks','milkshakes','gifts',
        ])->get()->keyBy('slug');

        $catalog = [
            'cakes' => [
                ['CK-001','Chocolate Cake','كيك شوكولاتة','piece',85], ['CK-002','Red Velvet Cake','كيك ريد فيلفت','piece',95],
                ['CK-003','Classic Cheesecake','تشيز كيك كلاسيك','piece',80], ['CK-004','Lemon Cake','كيك الليمون','piece',75],
                ['CK-005','Caramel Cake','كيك كراميل','piece',90], ['CK-006','Pistachio Cake','كيك فستق','piece',110],
                ['CK-007','Lotus Cake','كيك لوتس','piece',100], ['CK-008','Seasonal Fruit Cake','كيك فواكه موسمية','piece',105],
            ],
            'gateaux' => [
                ['GT-001','Opera Gateaux','غاتوه أوبرا','piece',12], ['GT-002','Mixed Gateaux Tray','صينية غاتوه مشكلة','tray',120],
                ['GT-003','Chocolate Eclair','إكلير شوكولاتة','piece',8], ['GT-004','Vanilla Eclair','إكلير فانيلا','piece',8],
                ['GT-005','Mini Gateaux Box','علبة ميني غاتوه','box',55], ['GT-006','Black Forest Slice','قطعة بلاك فورست','piece',14],
            ],
            'oriental-sweets' => [
                ['OR-001','Baklava Mix','بقلاوة مشكلة','kg',60], ['OR-002','Cheese Kunafa','كنافة جبنة','kg',45],
                ['OR-003','Date Maamoul','معمول تمر','kg',55], ['OR-004','Awamat','عوامة','kg',30],
                ['OR-005','Cream Warbat','وربات بالقشطة','kg',58], ['OR-006','Pistachio Burma','برمة فستق','kg',82],
                ['OR-007','Osh El Bulbul','عش البلبل','kg',78], ['OR-008','Basbousa','بسبوسة','tray',38],
                ['OR-009','Almond Harissa','هريسة لوز','tray',42], ['OR-010','Walnut Qatayef','قطايف جوز','kg',48],
                ['OR-011','Cream Qatayef','قطايف قشطة','kg',45], ['OR-012','Ghraybeh','غريبة','kg',52],
                ['OR-013','Barazek','برازق','kg',50], ['OR-014','Halawet El Jibn','حلاوة الجبن','kg',65],
                ['OR-015','Pistachio Maamoul','معمول فستق','kg',72],
            ],
            'western-sweets' => [
                ['WS-001','Butter Croissant','كرواسان زبدة','piece',6], ['WS-002','Cinnamon Roll','رول قرفة','piece',7],
                ['WS-003','Fudge Brownie','براوني فادج','piece',9], ['WS-004','Chocolate Donut','دونات شوكولاتة','piece',7],
                ['WS-005','Strawberry Donut','دونات فراولة','piece',7], ['WS-006','Tiramisu Cup','كوب تيراميسو','cup',16],
                ['WS-007','Cheesecake Slice','قطعة تشيز كيك','piece',15], ['WS-008','Chocolate Mousse','موس شوكولاتة','cup',14],
                ['WS-009','Vanilla Panna Cotta','بانا كوتا فانيلا','cup',14], ['WS-010','Apple Tart','تارت تفاح','piece',12],
                ['WS-011','Fruit Tart','تارت فواكه','piece',14], ['WS-012','Macaron Box','علبة ماكرون','box',48],
                ['WS-013','Chocolate Chip Cookies','كوكيز شوكولاتة','box',30], ['WS-014','Molten Chocolate Cake','مولتن كيك','piece',18],
                ['WS-015','Belgian Waffle','وافل بلجيكي','piece',20],
            ],
            'chocolate' => [
                ['CH-001','Dahab Chocolate Box','علبة شوكولاتة دهب','box',45], ['CH-002','Belgian Dark Truffle','ترافل بلجيكي داكن','box',65],
                ['CH-003','Pistachio Chocolate Bar','لوح شوكولاتة بالفستق','piece',18], ['CH-004','Chocolate Dates Box','علبة تمر بالشوكولاتة','box',58],
            ],
            'drinks' => [
                ['DR-001','Arabic Coffee','قهوة عربية','cup',5], ['DR-002','Fresh Orange Juice','عصير برتقال طازج','cup',8],
                ['DR-003','Iced Coffee','قهوة مثلجة','cup',12], ['DR-004','Mint Lemonade','ليمون ونعناع','cup',10],
                ['DR-005','Hot Chocolate','شوكولاتة ساخنة','cup',11],
            ],
            'milkshakes' => [
                ['MS-001','Vanilla Milkshake','ميلك شيك فانيلا','cup',14], ['MS-002','Chocolate Milkshake','ميلك شيك شوكولاتة','cup',15],
                ['MS-003','Strawberry Milkshake','ميلك شيك فراولة','cup',15], ['MS-004','Oreo Milkshake','ميلك شيك أوريو','cup',17],
                ['MS-005','Lotus Milkshake','ميلك شيك لوتس','cup',17], ['MS-006','Nutella Milkshake','ميلك شيك نوتيلا','cup',18],
                ['MS-007','Pistachio Milkshake','ميلك شيك فستق','cup',20], ['MS-008','Salted Caramel Milkshake','ميلك شيك كراميل مملح','cup',17],
                ['MS-009','Mango Milkshake','ميلك شيك مانجا','cup',16], ['MS-010','Mixed Berry Milkshake','ميلك شيك توت مشكل','cup',18],
                ['MS-011','Date Milkshake','ميلك شيك تمر','cup',17], ['MS-012','Dahab Special Milkshake','ميلك شيك دهب المميز','cup',22],
            ],
            'gifts' => [
                ['GF-001','Small Gift Basket','سلة هدايا صغيرة','piece',95], ['GF-002','Large Gift Basket','سلة هدايا كبيرة','piece',180],
                ['GF-003','Oriental Sweets Gift Box','بوكس حلويات شرقية','box',85], ['GF-004','Chocolate Gift Tower','برج شوكولاتة للهدايا','piece',150],
            ],
        ];

        $locations = Location::query()->orderBy('id')->get();
        $sequence = 1;
        foreach ($catalog as $categorySlug => $items) {
            $category = $categories->get($categorySlug);
            if (! $category) throw new RuntimeException("التصنيف غير موجود: {$categorySlug}");

            foreach ($items as [$sku, $name, $nameAr, $unit, $price]) {
                /*
                 * Keep a generated local SVG as an offline fallback, but use a
                 * real remote food photo for the demo catalog. Product/customer
                 * menu rendering supports both remote URLs and local storage.
                 */
                $imagePath = $this->ensureDemoProductImage(
                    $sku,
                    $name,
                    $nameAr,
                    $categorySlug
                );

                $demoImageUrl = $this->demoProductRemoteImage(
                    $categorySlug
                );

                // sku/barcode are intentionally guarded in Product. Assign
                // every required column directly so MySQL never receives an
                // INSERT without the non-null barcode field.
                $product = Product::query()->firstOrNew(['sku' => $sku]);
                $product->sku = $sku;
                $product->barcode = sprintf('629110%07d', $sequence);
                $product->category_id = $category->id;
                $product->name = $name;
                $product->name_ar = $nameAr;
                $product->unit = $unit;
                $product->base_selling_price = $price;
                $product->is_active = true;
                $product->save();
                $this->upsertExisting('products', ['id' => $product->id], [
                    'image' => $demoImageUrl,
                    'image_path' => $imagePath,
                    'image_url' => $demoImageUrl,
                    'description' => $name,
                    'description_ar' => 'صنف طازج من حلويات دهب: '.$nameAr,
                ]);

                foreach ($locations as $locationIndex => $location) {
                    LocationProduct::query()->updateOrCreate(
                        ['location_id' => $location->id, 'product_id' => $product->id],
                        ['is_available' => true, 'minimum_stock_level' => 5 + ($sequence % 4)]
                    );
                    Inventory::query()->updateOrCreate(
                        ['location_id' => $location->id, 'product_id' => $product->id],
                        ['quantity' => 18 + (($sequence * 7 + $locationIndex * 3) % 43)]
                    );
                }
                $sequence++;
            }
        }
    }

    private function demoProductRemoteImage(string $category): string
    {
        $images = [
            'cakes' =>
                'https://unsplash.com/photos/UuamQBi4_xI/download?force=true&w=1200',
            'gateaux' =>
                'https://unsplash.com/photos/1nQvGoWjkKo/download?force=true&w=1200',
            'oriental-sweets' =>
                'https://unsplash.com/photos/UU0VNFnTAZc/download?force=true&w=1200',
            'western-sweets' =>
                'https://unsplash.com/photos/V2dFgExH2YM/download?force=true&w=1200',
            'chocolate' =>
                'https://images.unsplash.com/photo-1597184694636-2986838febd3?auto=format&fit=crop&w=1200&q=80',
            'drinks' =>
                'https://unsplash.com/photos/Ic8RmXGyfNc/download?force=true&w=1200',
            'milkshakes' =>
                'https://unsplash.com/photos/p49qtDW866I/download?force=true&w=1200',
            'gifts' =>
                'https://unsplash.com/photos/drHdN8359Pc/download?force=true&w=1200',
        ];

        return $images[$category]
            ?? $images['cakes'];
    }

    private function ensureDemoProductImage(string $sku, string $name, string $nameAr, string $category): string
    {
        $directory = public_path('images/demo-products');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('تعذر إنشاء مجلد صور المنتجات التجريبية.');
        }

        $palettes = [
            'cakes' => ['#5B214E','#F0B94D'], 'gateaux' => ['#6D3E2B','#F6D7A7'],
            'oriental-sweets' => ['#8A4F08','#F2C14E'], 'western-sweets' => ['#713E5A','#F6B8C8'],
            'chocolate' => ['#3B241D','#D9A441'], 'drinks' => ['#145A5A','#8DD3C7'],
            'milkshakes' => ['#7B3F8C','#F3B7E3'], 'gifts' => ['#173A5E','#D9A441'],
        ];
        [$background, $accent] = $palettes[$category] ?? ['#173A5E','#D9A441'];
        $safeSku = preg_replace('/[^A-Za-z0-9_-]/', '-', $sku);
        $relativePath = 'images/demo-products/'.$safeSku.'.svg';
        $file = public_path($relativePath);
        $english = htmlspecialchars($name, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $arabic = htmlspecialchars($nameAr, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="900" viewBox="0 0 1200 900">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="{$background}"/><stop offset="1" stop-color="#111827"/></linearGradient></defs>
  <rect width="1200" height="900" rx="54" fill="url(#g)"/>
  <circle cx="600" cy="355" r="210" fill="{$accent}" opacity=".16"/><circle cx="600" cy="355" r="150" fill="none" stroke="{$accent}" stroke-width="18"/>
  <text x="600" y="320" text-anchor="middle" font-size="86" font-weight="700" fill="{$accent}">دهب</text>
  <text x="600" y="410" text-anchor="middle" font-size="54" font-weight="700" fill="#fff" direction="rtl">{$arabic}</text>
  <text x="600" y="680" text-anchor="middle" font-size="42" fill="#fff">{$english}</text>
  <text x="600" y="750" text-anchor="middle" font-size="30" fill="{$accent}">{$safeSku}</text>
</svg>
SVG;
        if (file_put_contents($file, $svg) === false) {
            throw new RuntimeException("تعذر إنشاء صورة المنتج {$sku}");
        }

        return $relativePath;
    }

    // ─────────────────────────────────────────────────────────────
    // PAYMENT METHODS
    // ─────────────────────────────────────────────────────────────
    private function seedPaymentMethods(): void
    {
        $methods = [
            ['name' => 'Cash',              'name_ar' => 'نقداً',              'code' => 'CASH',              'type' => 'cash',              'requires_verification' => false, 'requires_reference' => false, 'sort_order' => 1],
            ['name' => 'Bank Transfer',     'name_ar' => 'تحويل بنكي',        'code' => 'BANK',              'type' => 'bank_transfer',     'requires_verification' => true,  'requires_reference' => true,  'sort_order' => 2],
            ['name' => 'PayBox',            'name_ar' => 'باي بوكس',          'code' => 'PAYBOX',            'type' => 'electronic_wallet', 'requires_verification' => true,  'requires_reference' => true,  'sort_order' => 3],
            ['name' => 'Card POS',          'name_ar' => 'بطاقة ائتمانية',    'code' => 'CARD',              'type' => 'card_pos',          'requires_verification' => false, 'requires_reference' => false, 'sort_order' => 4],
            ['name' => 'Pal Pay',           'name_ar' => 'بال باي',           'code' => 'pal_pay',           'type' => 'electronic_wallet', 'requires_verification' => true,  'requires_reference' => true,  'sort_order' => 5],
            ['name' => 'Bank of Palestine', 'name_ar' => 'بنك فلسطين',        'code' => 'bank_of_palestine', 'type' => 'bank_transfer',     'requires_verification' => true,  'requires_reference' => true,  'sort_order' => 6],
            ['name' => 'Jawwal Pay',        'name_ar' => 'جوال باي',          'code' => 'jawwal_pay',        'type' => 'electronic_wallet', 'requires_verification' => true,  'requires_reference' => true,  'sort_order' => 7],
        ];

        $locations = Location::all();
        foreach ($methods as $m) {
            $method = PaymentMethod::firstOrCreate(['code' => $m['code']], array_merge($m, ['is_active' => true]));
            foreach ($locations as $location) {
                LocationPaymentMethod::firstOrCreate(
                    ['location_id' => $location->id, 'payment_method_id' => $method->id],
                    ['is_active' => true]
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // SYSTEM SETTINGS
    // ─────────────────────────────────────────────────────────────
    private function seedSystemSettings(): void
    {
        $settings = [
            ['key' => 'system_name',                               'value' => 'حلويات دهب',                        'type' => 'string',  'group' => 'general',       'label' => 'اسم النظام'],
            ['key' => 'currency_code',                             'value' => 'ILS',                               'type' => 'string',  'group' => 'financial',     'label' => 'رمز العملة'],
            ['key' => 'currency_symbol',                           'value' => '₪',                                 'type' => 'string',  'group' => 'financial',     'label' => 'رمز العملة المعروض'],
            ['key' => 'currency_decimal_places',                   'value' => '2',                                 'type' => 'integer', 'group' => 'financial',     'label' => 'خانات عشرية'],
            ['key' => 'timezone',                                  'value' => 'Asia/Jerusalem',                    'type' => 'string',  'group' => 'general',       'label' => 'المنطقة الزمنية'],
            ['key' => 'deposit_policy_enabled',                    'value' => '1',                                 'type' => 'boolean', 'group' => 'cake_orders',   'label' => 'تفعيل سياسة العربون'],
            ['key' => 'deposit_type',                              'value' => 'percentage',                        'type' => 'string',  'group' => 'cake_orders',   'label' => 'نوع العربون'],
            ['key' => 'minimum_deposit_percentage',                'value' => '30',                                'type' => 'decimal', 'group' => 'cake_orders',   'label' => 'نسبة العربون الأدنى'],
            ['key' => 'minimum_deposit_amount',                    'value' => '50',                                'type' => 'decimal', 'group' => 'cake_orders',   'label' => 'مبلغ العربون الأدنى'],
            ['key' => 'factory_can_accept_unpaid_cake_orders',     'value' => '0',                                 'type' => 'boolean', 'group' => 'cake_orders',   'label' => 'قبول طلبات بدون عربون'],
            ['key' => 'require_active_cash_session_for_cash_payment', 'value' => '0',                             'type' => 'boolean', 'group' => 'cashier',       'label' => 'اشتراط جلسة كاشير للدفع النقدي'],
            ['key' => 'block_financial_period_close_with_open_cash_sessions', 'value' => '1',                     'type' => 'boolean', 'group' => 'financial',     'label' => 'منع إغلاق الفترة عند وجود جلسة كاشير مفتوحة'],
            ['key' => 'order_number_prefix',                       'value' => 'ORD',                               'type' => 'string',  'group' => 'orders',        'label' => 'بادئة رقم الطلب'],
            ['key' => 'cake_order_prefix',                         'value' => 'CKO',                               'type' => 'string',  'group' => 'cake_orders',   'label' => 'بادئة رقم طلب الكيك'],
            ['key' => 'stock_request_prefix',                      'value' => 'SR',                                'type' => 'string',  'group' => 'inventory',     'label' => 'بادئة رقم طلب المخزون'],
            ['key' => 'invoice_prefix',                            'value' => 'DH',                                'type' => 'string',  'group' => 'invoices',      'label' => 'بادئة رقم الفاتورة'],
            ['key' => 'invoice_footer_ar',                         'value' => 'شكراً لاختياركم حلويات دهب',       'type' => 'string',  'group' => 'invoices',      'label' => 'تذييل الفاتورة (عربي)'],
            ['key' => 'invoice_footer_en',                         'value' => 'Thank you for choosing Dahab Sweets', 'type' => 'string', 'group' => 'invoices',     'label' => 'تذييل الفاتورة (إنجليزي)'],
            ['key' => 'notification_sound_enabled',                'value' => '1',                                 'type' => 'boolean', 'group' => 'notifications', 'label' => 'تفعيل صوت الإشعارات'],
        ];

        foreach ($settings as $s) {
            SystemSetting::firstOrCreate(['key' => $s['key']], $s);
        }
    }

    /**
     * One canonical source for defaults that previously lived in separate
     * branding, print, KDS, customer-display and onboarding seeders.
     * updateOrCreate makes this safe on both fresh and existing databases.
     */
    private function seedUnifiedFeatureDefaults(): void
    {
        $groups = [
            'branding' => [
                'system_name_en' => ['Dahab Sweets', 'string', 'اسم العلامة بالإنجليزية'],
                'brand_tagline_ar' => ['', 'string', 'الشعار النصي بالعربية'],
                'brand_tagline_en' => ['', 'string', 'الشعار النصي بالإنجليزية'],
                'brand_footer_text' => ['حلويات دهب - Dahab Sweets', 'string', 'نص الفوتر'],
                'special_cake_auto_approval' => ['1', 'boolean', 'الموافقة التلقائية على طلب الكيك الخاص'],
                'brand_logo' => ['', 'image', 'الشعار الرئيسي'],
                'brand_logo_small' => ['', 'image', 'الشعار المصغّر'],
                'brand_favicon' => ['', 'image', 'أيقونة المتصفح'],
                'brand_report_logo' => ['', 'image', 'شعار التقارير والفواتير'],
                'brand_stamp' => ['', 'image', 'الختم الرسمي'],
                'brand_signature' => ['', 'image', 'التوقيع المعتمد'],
                'brand_login_background' => ['', 'image', 'خلفية صفحة تسجيل الدخول'],
            ],
            'theme' => [
                'theme_primary' => ['#0A2948', 'color', 'اللون الرئيسي'],
                'theme_secondary' => ['#C98516', 'color', 'اللون الثانوي'],
                'theme_accent' => ['#C98516', 'color', 'لون التمييز'],
                'theme_background' => ['#F5F6F8', 'color', 'خلفية النظام'],
                'theme_surface' => ['#FFFFFF', 'color', 'خلفية البطاقات'],
                'theme_text' => ['#172435', 'color', 'لون النص الرئيسي'],
                'theme_text_muted' => ['#687482', 'color', 'لون النص الثانوي'],
                'theme_border' => ['#DDE2E7', 'color', 'لون الحدود'],
                'theme_sidebar_bg' => ['#0A2948', 'color', 'خلفية القائمة الجانبية'],
                'theme_sidebar_text' => ['#FFFFFF', 'color', 'نص القائمة الجانبية'],
                'theme_sidebar_active' => ['#C98516', 'color', 'العنصر النشط في القائمة'],
                'theme_header_bg' => ['#FFFFFF', 'color', 'خلفية الهيدر'],
                'theme_success' => ['#197438', 'color', 'لون النجاح'],
                'theme_warning' => ['#C98516', 'color', 'لون التحذير'],
                'theme_danger' => ['#E22929', 'color', 'لون الخطأ'],
                'theme_info' => ['#2F72C4', 'color', 'لون المعلومات'],
                'theme_font_family' => ['Cairo', 'select', 'الخط الافتراضي'],
                'theme_radius' => ['10', 'integer', 'استدارة الزوايا'],
            ],
            'customer_display' => [
                'customer_display_background_image' => ['', 'image', 'خلفية شاشة الطلبات'],
                'customer_display_background_color' => ['#090909', 'color', 'لون الخلفية الأساسي'],
                'customer_display_overlay_color' => ['#000000', 'color', 'لون طبقة التعتيم'],
                'customer_display_overlay_opacity' => ['72', 'integer', 'شفافية طبقة التعتيم'],
                'customer_display_header_bg' => ['#090909', 'color', 'لون شريط الرأس'],
                'customer_display_panel_bg' => ['#111111', 'color', 'لون لوحات الحالات'],
                'customer_display_card_bg' => ['#181818', 'color', 'لون بطاقة الطلب'],
                'customer_display_text_color' => ['#FFFFFF', 'color', 'لون النص الأساسي'],
                'customer_display_muted_color' => ['#A3A3A3', 'color', 'لون النص الثانوي'],
                'customer_display_preparing_color' => ['#F0B429', 'color', 'لون قيد التحضير'],
                'customer_display_ready_color' => ['#24C36B', 'color', 'لون جاهز للاستلام'],
                'customer_display_accent_color' => ['#D7A51D', 'color', 'اللون المميز'],
                'customer_display_border_color' => ['#2A2A2A', 'color', 'لون الحدود'],
                'customer_display_panel_opacity' => ['92', 'integer', 'شفافية اللوحات'],
                'customer_display_glass_blur' => ['8', 'integer', 'ضبابية خلفية اللوحات'],
                'customer_display_radius' => ['22', 'integer', 'استدارة البطاقات'],
                'customer_display_logo_size' => ['54', 'integer', 'حجم الشعار'],
                'customer_display_order_number_size' => ['70', 'integer', 'حجم رقم الطلب'],
                'customer_display_show_service_type' => ['1', 'boolean', 'عرض نوع الخدمة'],
                'customer_display_show_table' => ['1', 'boolean', 'عرض الطاولة'],
                'customer_display_show_clock' => ['1', 'boolean', 'عرض الساعة والتاريخ'],
            ],
            'kitchen' => [
                'kds_poll_seconds' => ['3', 'integer', 'فترة تحديث شاشة المطبخ'],
                'kds_warning_minutes' => ['10', 'integer', 'تنبيه تأخير المطبخ'],
                'kds_critical_minutes' => ['20', 'integer', 'التأخير الحرج للمطبخ'],
                'kds_sound_enabled' => ['1', 'boolean', 'صوت الطلب الجديد'],
                'kds_sound_volume' => ['70', 'integer', 'مستوى صوت KDS'],
                'kitchen_require_served_before_complete' => ['1', 'boolean', 'منع إكمال الطلب قبل التسليم'],
            ],
            'print_branding' => [
                'print_template' => ['modern', 'string', 'نمط المستند'],
                'print_primary_color' => ['#d6a925', 'string', 'لون الطباعة الرئيسي'],
                'print_secondary_color' => ['#111827', 'string', 'لون الطباعة الثانوي'],
                'print_text_color' => ['#1f2937', 'string', 'لون النص'],
                'print_logo_position' => ['right', 'string', 'موضع الشعار'],
                'print_logo_size' => ['90', 'integer', 'حجم الشعار'],
                'print_paper_size' => ['A4', 'string', 'حجم الورق'],
                'print_show_logo' => ['1', 'boolean', 'إظهار الشعار'],
                'print_show_business_info' => ['1', 'boolean', 'إظهار بيانات المنشأة'],
                'print_show_document_number' => ['1', 'boolean', 'إظهار رقم المستند'],
                'print_show_signatures' => ['1', 'boolean', 'إظهار التواقيع'],
                'print_show_stamp' => ['0', 'boolean', 'إظهار الختم'],
                'print_show_footer' => ['1', 'boolean', 'إظهار التذييل'],
                'print_footer_text' => ['', 'string', 'نص التذييل'],
                'print_logo' => ['', 'string', 'شعار الطباعة'],
                'print_stamp' => ['', 'string', 'ختم الطباعة'],
            ],
        ];

        foreach ($groups as $group => $settings) {
            foreach ($settings as $key => [$value, $type, $label]) {
                SystemSetting::query()->updateOrCreate(
                    ['key' => $key],
                    compact('value', 'type', 'group', 'label') + [
                        'description' => 'قيمة افتراضية موحدة قابلة للتعديل من إعدادات النظام.',
                    ]
                );
            }
        }

        SystemSetting::query()
            ->where(
                'key',
                'special_cake_auto_approval'
            )
            ->update([
                'description' =>
                    'عند التفعيل ينتقل طلب الكيك الخاص الجديد مباشرة إلى قيد التنفيذ بموافقة تلقائية من النظام. عند الإلغاء يبدأ الطلب قيد المراجعة ويحتاج موافقة يدوية.',
            ]);

        SystemSetting::flushCache();
    }

    // ─────────────────────────────────────────────────────────────
    // CUSTOMERS
    // ─────────────────────────────────────────────────────────────
    private function seedCustomers(): void
    {
        $customers = [
            ['name' => 'محمد أحمد الخالد',  'phone' => '0599100001', 'notes' => 'عميل VIP — يفضل الكيك الشوكولاتة'],
            ['name' => 'فاطمة سالم',         'phone' => '0599100002', 'notes' => null],
            ['name' => 'خالد عمر النجار',    'phone' => '0599100003', 'notes' => 'حساس للجلوتين'],
            ['name' => 'نور الدين حسن',      'phone' => '0599100004', 'notes' => null],
            ['name' => 'سارة يوسف',          'phone' => '0599100005', 'notes' => 'تفضل التوصيل عند المساء'],
            ['name' => 'عمار زياد حجازي',   'phone' => '0599100006', 'notes' => null],
            ['name' => 'ريم أحمد مصطفى',    'phone' => '0599100007', 'notes' => 'عميلة منتظمة — خصم 5%'],
            ['name' => 'بلال كريم',          'phone' => '0599100008', 'notes' => null],
            ['name' => 'منى الشريف',         'phone' => '0599100009', 'notes' => null],
            ['name' => 'طارق حمدي',          'phone' => '0599100010', 'notes' => 'يطلب فاتورة رسمية دائماً'],
        ];

        foreach ($customers as $c) {
            Customer::firstOrCreate(['phone' => $c['phone']], $c);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // FINANCIAL PERIODS
    // ─────────────────────────────────────────────────────────────
    private function seedFinancialPeriods(): void
    {
        $adminUser = User::where('username', 'admin')->first();
        if (! $adminUser) return;

        // Payroll demo payments use now() as their payment date. Keep the
        // current month open so the settlement service can post the payment,
        // and seed the preceding three months as closed history.
        $periods = collect(range(3, 0))->map(function (int $monthsAgo): array {
            $start = now()->subMonthsNoOverflow($monthsAgo)->startOfMonth();

            return [
                'year' => $start->year,
                'month' => $start->month,
                'status' => $monthsAgo === 0 ? 'open' : 'closed',
            ];
        });

        foreach ($periods as $p) {
            $start = \Carbon\Carbon::create($p['year'], $p['month'], 1)->startOfMonth();
            $end   = $start->copy()->endOfMonth();
            $name  = $start->translatedFormat('F Y');

            FinancialPeriod::query()->updateOrCreate(
                ['year' => $p['year'], 'month' => $p['month']],
                [
                    'name'            => $name,
                    'start_date'      => $start->toDateString(),
                    'end_date'        => $end->toDateString(),
                    'status'          => $p['status'],
                    'opening_balance' => 0,
                    'closing_balance' => $p['status'] === 'closed'
                        ? 5000 + (($p['year'] * 12 + $p['month']) % 200) * 100
                        : null,
                    'opened_at'       => $start->copy()->addHours(8),
                    'opened_by'       => $adminUser->id,
                    'closed_at'       => $p['status'] === 'closed' ? $end->copy()->setHour(20) : null,
                    'closed_by'       => $p['status'] === 'closed' ? $adminUser->id : null,
                ]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // CASH SESSIONS
    // ─────────────────────────────────────────────────────────────
    private function seedCashSessions(): void
    {
        $cashierB1 = User::where('username', 'cashier_b01')->first();
        $cashierB2 = User::where('username', 'cashier_b02')->first();
        $branchB1  = Location::where('code', 'B01')->first();
        $branchB2  = Location::where('code', 'B02')->first();

        if (! $cashierB1 || ! $branchB1) return;

        $sessions = [
            // Closed sessions – B01
            [
                'employee_id'     => $cashierB1->employee_id,
                'location_id'     => $branchB1->id,
                'opening_balance' => 500.00,
                'opened_at'       => now()->subDays(3)->setTime(9, 0),
                'status'          => 'closed',
                'cash_received'   => 1850.00,
                'cash_refunds'    => 120.00,
                'actual_cash'     => 2220.00,
                'variance'        => -10.00,
                'closing_note'    => 'نقص بسيط في الصندوق — تمت المراجعة',
                'closed_at'       => now()->subDays(3)->setTime(20, 0),
                'closed_by'       => $cashierB1->id,
            ],
            [
                'employee_id'     => $cashierB1->employee_id,
                'location_id'     => $branchB1->id,
                'opening_balance' => 500.00,
                'opened_at'       => now()->subDays(2)->setTime(9, 0),
                'status'          => 'closed',
                'cash_received'   => 2100.00,
                'cash_refunds'    => 80.00,
                'actual_cash'     => 2520.00,
                'variance'        => 0.00,
                'closing_note'    => 'مطابق',
                'closed_at'       => now()->subDays(2)->setTime(20, 30),
                'closed_by'       => $cashierB1->id,
            ],
            // Open session today – B01
            [
                'employee_id'     => $cashierB1->employee_id,
                'location_id'     => $branchB1->id,
                'opening_balance' => 500.00,
                'opened_at'       => now()->setTime(9, 0),
                'status'          => 'open',
                'cash_received'   => 640.00,
                'cash_refunds'    => 0.00,
                'actual_cash'     => null,
                'variance'        => null,
                'closing_note'    => null,
                'closed_at'       => null,
                'closed_by'       => null,
            ],
            // Closed session – B02
            [
                'employee_id'     => $cashierB2 ? $cashierB2->employee_id : $cashierB1->employee_id,
                'location_id'     => $branchB2 ? $branchB2->id : $branchB1->id,
                'opening_balance' => 400.00,
                'opened_at'       => now()->subDays(1)->setTime(9, 0),
                'status'          => 'closed',
                'cash_received'   => 1350.00,
                'cash_refunds'    => 50.00,
                'actual_cash'     => 1700.00,
                'variance'        => 0.00,
                'closing_note'    => 'مطابق تام',
                'closed_at'       => now()->subDays(1)->setTime(20, 0),
                'closed_by'       => $cashierB2 ? $cashierB2->id : $cashierB1->id,
            ],
        ];

        foreach ($sessions as $s) {
            CashSession::query()->updateOrCreate(
                [
                    'employee_id' => $s['employee_id'],
                    'location_id' => $s['location_id'],
                    'opened_at' => $s['opened_at'],
                ],
                $s
            );
        }

        // Older versions of this unified seeder used create(), so running it
        // repeatedly could leave several open sessions for the same employee.
        // Keep the newest session open and close only the duplicate sessions.
        collect($sessions)
            ->pluck('employee_id')
            ->filter()
            ->unique()
            ->each(function (int $employeeId) use ($cashierB1): void {
                $openSessions = CashSession::query()
                    ->where('employee_id', $employeeId)
                    ->where('status', 'open')
                    ->orderByDesc('opened_at')
                    ->orderByDesc('id')
                    ->get();

                foreach ($openSessions->skip(1) as $duplicate) {
                    $expectedCash = (float) $duplicate->opening_balance
                        + (float) $duplicate->cash_received
                        - (float) $duplicate->cash_refunds;
                    $actualCash = $duplicate->actual_cash === null
                        ? $expectedCash
                        : (float) $duplicate->actual_cash;

                    DB::table('cash_sessions')
                        ->where('id', $duplicate->id)
                        ->update([
                            'status' => 'closed',
                            'actual_cash' => $actualCash,
                            'variance' => $actualCash - $expectedCash,
                            'closing_note' => $duplicate->closing_note
                                ?: 'أغلقت تلقائياً: جلسة تجريبية مفتوحة مكررة.',
                            'closed_at' => now(),
                            'closed_by' => $cashierB1->id,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    // ─────────────────────────────────────────────────────────────
    // CURRENCIES
    // ─────────────────────────────────────────────────────────────
    private function seedCurrencies(): void
    {
        if (! Schema::hasTable('currencies')) return;

        foreach ([
            ['code' => 'ILS', 'name' => 'Israeli New Shekel', 'name_ar' => 'شيكل إسرائيلي جديد', 'symbol' => '₪', 'is_base' => true],
            ['code' => 'USD', 'name' => 'US Dollar', 'name_ar' => 'دولار أمريكي', 'symbol' => '$', 'is_base' => false],
            ['code' => 'EUR', 'name' => 'Euro', 'name_ar' => 'يورو', 'symbol' => '€', 'is_base' => false],
            ['code' => 'EGP', 'name' => 'Egyptian Pound', 'name_ar' => 'جنيه مصري', 'symbol' => 'E£', 'is_base' => false],
        ] as $currency) {
            $this->upsertExisting('currencies', ['code' => $currency['code']], $currency + [
                'decimal_places' => 2,
                'is_active' => true,
            ]);
        }

        // There must be exactly one deterministic base currency.
        DB::table('currencies')->where('code', '!=', 'ILS')->update(['is_base' => false]);
        DB::table('currencies')->where('code', 'ILS')->update(['is_base' => true]);
    }

    // ─────────────────────────────────────────────────────────────
    // ORDERS
    // ─────────────────────────────────────────────────────────────
    private function seedOrders(): void
    {
        $cashierB1 = User::where('username', 'cashier_b01')->first();
        $branchB1  = Location::where('code', 'B01')->first();
        $branchB2  = Location::where('code', 'B02')->first();
        $cashierB2 = User::where('username', 'cashier_b02')->first();

        if (! $cashierB1 || ! $branchB1) return;

        $customers = Customer::all();
        $cash      = PaymentMethod::where('code', 'CASH')->first();
        $card      = PaymentMethod::where('code', 'CARD')->first();
        $products  = Product::all()->keyBy('sku');

        $ordersData = [
            [
                'order_number'       => 'ORD-2026-0001',
                'location_id'        => $branchB1->id,
                'customer_id'        => $customers->get(0)?->id,
                'created_by'         => $cashierB1->id,
                'status'             => 'completed',
                'payment_status'     => 'paid',
                'payment_arrangement'=> 'pay_now',
                'subtotal'           => 180.00,
                'discount_amount'    => 0.00,
                'tax_amount'         => 0.00,
                'total_amount'       => 180.00,
                'confirmed_at'       => now()->subDays(5),
                'completed_at'       => now()->subDays(5),
                'items' => [
                    ['sku' => 'CK-001', 'qty' => 1, 'price' => 85.00],
                    ['sku' => 'CK-002', 'qty' => 1, 'price' => 95.00],
                ],
                'payment' => ['method' => $cash, 'amount' => 180.00, 'status' => 'confirmed'],
            ],
            [
                'order_number'       => 'ORD-2026-0002',
                'location_id'        => $branchB1->id,
                'customer_id'        => $customers->get(1)?->id,
                'created_by'         => $cashierB1->id,
                'status'             => 'completed',
                'payment_status'     => 'paid',
                'payment_arrangement'=> 'pay_now',
                'subtotal'           => 120.00,
                'discount_amount'    => 10.00,
                'tax_amount'         => 0.00,
                'total_amount'       => 110.00,
                'confirmed_at'       => now()->subDays(4),
                'completed_at'       => now()->subDays(4),
                'items' => [
                    ['sku' => 'OR-001', 'qty' => 2, 'price' => 60.00],
                ],
                'payment' => ['method' => $card, 'amount' => 110.00, 'status' => 'confirmed'],
            ],
            [
                'order_number'       => 'ORD-2026-0003',
                'location_id'        => $branchB1->id,
                'customer_id'        => $customers->get(2)?->id,
                'created_by'         => $cashierB1->id,
                'status'             => 'completed',
                'payment_status'     => 'paid',
                'payment_arrangement'=> 'pay_now',
                'subtotal'           => 68.00,
                'discount_amount'    => 0.00,
                'tax_amount'         => 0.00,
                'total_amount'       => 68.00,
                'confirmed_at'       => now()->subDays(3),
                'completed_at'       => now()->subDays(3),
                'items' => [
                    ['sku' => 'GT-003', 'qty' => 4, 'price' => 8.00],
                    ['sku' => 'WS-003', 'qty' => 4, 'price' => 9.00],
                ],
                'payment' => ['method' => $cash, 'amount' => 68.00, 'status' => 'confirmed'],
            ],
            [
                'order_number'       => 'ORD-2026-0004',
                'location_id'        => $branchB1->id,
                'customer_id'        => $customers->get(3)?->id,
                'created_by'         => $cashierB1->id,
                'status'             => 'completed',
                'payment_status'     => 'paid',
                'payment_arrangement'=> 'pay_now',
                'subtotal'           => 45.00,
                'discount_amount'    => 0.00,
                'tax_amount'         => 0.00,
                'total_amount'       => 45.00,
                'confirmed_at'       => now()->subDays(2),
                'completed_at'       => now()->subDays(2),
                'items' => [
                    ['sku' => 'CH-001', 'qty' => 1, 'price' => 45.00],
                ],
                'payment' => ['method' => $cash, 'amount' => 45.00, 'status' => 'confirmed'],
            ],
            [
                'order_number'       => 'ORD-2026-0005',
                'location_id'        => $branchB1->id,
                'customer_id'        => $customers->get(4)?->id,
                'created_by'         => $cashierB1->id,
                'status'             => 'confirmed',
                'payment_status'     => 'payment_pending',
                'payment_arrangement'=> 'pay_on_pickup',
                'subtotal'           => 180.00,
                'discount_amount'    => 0.00,
                'tax_amount'         => 0.00,
                'total_amount'       => 180.00,
                'confirmed_at'       => now()->subHours(2),
                'completed_at'       => null,
                'items' => [
                    ['sku' => 'GF-002', 'qty' => 1, 'price' => 180.00],
                ],
                'payment' => null,
            ],
            // B02 orders
            [
                'order_number'       => 'ORD-2026-0006',
                'location_id'        => $branchB2 ? $branchB2->id : $branchB1->id,
                'customer_id'        => $customers->get(5)?->id,
                'created_by'         => $cashierB2 ? $cashierB2->id : $cashierB1->id,
                'status'             => 'completed',
                'payment_status'     => 'paid',
                'payment_arrangement'=> 'pay_now',
                'subtotal'           => 245.00,
                'discount_amount'    => 0.00,
                'tax_amount'         => 0.00,
                'total_amount'       => 245.00,
                'confirmed_at'       => now()->subDays(1),
                'completed_at'       => now()->subDays(1),
                'items' => [
                    ['sku' => 'GT-002', 'qty' => 1, 'price' => 120.00],
                    ['sku' => 'OR-001', 'qty' => 1, 'price' => 60.00],
                    ['sku' => 'CH-002', 'qty' => 1, 'price' => 65.00],
                ],
                'payment' => ['method' => $card, 'amount' => 245.00, 'status' => 'confirmed'],
            ],
            // Draft order
            [
                'order_number'       => 'ORD-2026-0007',
                'location_id'        => $branchB1->id,
                'customer_id'        => $customers->get(6)?->id,
                'created_by'         => $cashierB1->id,
                'status'             => 'draft',
                'payment_status'     => 'payment_pending',
                'payment_arrangement'=> 'pay_now',
                'subtotal'           => 90.00,
                'discount_amount'    => 0.00,
                'tax_amount'         => 0.00,
                'total_amount'       => 90.00,
                'confirmed_at'       => null,
                'completed_at'       => null,
                'items' => [
                    ['sku' => 'CK-005', 'qty' => 1, 'price' => 90.00],
                ],
                'payment' => null,
            ],
        ];

        // Extra completed sales make reports, invoices, payments and KDS scenarios meaningful.
        foreach (range(8, 12) as $number) {
            $sku = ['CK-001', 'CK-002', 'OR-001', 'GT-003', 'WS-003'][$number - 8];
            $price = [85, 95, 60, 8, 9][$number - 8];
            $quantity = $number >= 11 ? 3 : 2;
            $secondaryPrice = $sku === 'CK-001' ? 95 : 85;
            $total = ($price * $quantity) + $secondaryPrice;
            $ordersData[] = [
                'order_number' => sprintf('ORD-2026-%04d', $number),
                'location_id' => $number % 2 === 0 && $branchB2 ? $branchB2->id : $branchB1->id,
                'customer_id' => $customers->get(($number - 1) % max(1, $customers->count()))?->id,
                'created_by' => $number % 2 === 0 && $cashierB2 ? $cashierB2->id : $cashierB1->id,
                'status' => 'completed', 'payment_status' => 'paid', 'payment_arrangement' => 'pay_now',
                'subtotal' => $total, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => $total,
                'confirmed_at' => now()->subHours($number), 'completed_at' => now()->subHours($number),
                'items' => [
                    ['sku' => $sku, 'qty' => $quantity, 'price' => $price],
                    ['sku' => $sku === 'CK-001' ? 'CK-002' : 'CK-001', 'qty' => 1, 'price' => $secondaryPrice],
                ],
                'payment' => ['method' => $number % 2 === 0 ? $cash : $card, 'amount' => $total, 'status' => 'confirmed'],
            ];
        }

        foreach ($ordersData as $od) {
            $items   = $od['items'];
            $payment = $od['payment'];
            unset($od['items'], $od['payment']);

            $order = Order::firstOrCreate(['order_number' => $od['order_number']], $od);

            $invoiceTotal = 0;
            foreach ($items as $item) {
                $product = $products->get($item['sku']);
                if (! $product) continue;
                $lineTotal = $item['qty'] * $item['price'];
                $invoiceTotal += $lineTotal;

                OrderItem::firstOrCreate(
                    ['order_id' => $order->id, 'product_id' => $product->id],
                    [
                        'product_name'    => $product->name_ar,
                        'unit_price'      => $item['price'],
                        'quantity'        => $item['qty'],
                        'discount_amount' => 0,
                        'line_total'      => $lineTotal,
                    ]
                );
            }

            if ($payment && $payment['method']) {
                Payment::firstOrCreate(
                    ['order_id' => $order->id, 'order_type' => 'order'],
                    [
                        'order_type'       => 'order',
                        'payment_method_id'=> $payment['method']->id,
                        'location_id'      => $order->location_id,
                        'amount'           => $payment['amount'],
                        'status'           => $payment['status'],
                        'received_by'      => $order->created_by,
                        'paid_at'          => $order->confirmed_at,
                    ]
                );

                // Create invoice for paid/completed orders
                if (($order->status->value ?? $order->status) === 'completed') {
                    $inv = Invoice::firstOrCreate(
                        ['order_id' => $order->id],
                        [
                            'invoice_number'   => 'DH-' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
                            'invoice_type'     => 'regular_order',
                            'order_type'       => 'order',
                            'location_id'      => $order->location_id,
                            'customer_id'      => $order->customer_id,
                            'status'           => 'active',
                            'subtotal'         => $order->subtotal,
                            'discount_amount'  => $order->discount_amount,
                            'tax_amount'       => $order->tax_amount,
                            'total_amount'     => $order->total_amount,
                            'paid_amount'      => $payment['amount'],
                            'remaining_amount' => 0,
                            'issued_by'        => $order->created_by,
                            'issued_at'        => $order->completed_at,
                        ]
                    );

                    foreach ($order->items as $oi) {
                        InvoiceItem::firstOrCreate(
                            ['invoice_id' => $inv->id, 'product_id' => $oi->product_id],
                            [
                                'description'     => $oi->product_name,
                                'quantity'        => $oi->quantity,
                                'unit_price'      => $oi->unit_price,
                                'discount_amount' => 0,
                                'line_total'      => $oi->line_total,
                            ]
                        );
                    }
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // SPECIAL CAKE ORDERS
    // ─────────────────────────────────────────────────────────────
    private function seedSpecialCakeOrders(): void
    {
        $cashierB1 = User::where('username', 'cashier_b01')->first();
        $designer  = User::where('username', 'cake_designer')->first();
        $branchB1  = Location::where('code', 'B01')->first();
        $branchB2  = Location::where('code', 'B02')->first();
        $factory   = Location::where('code', 'FAC')->first();
        $customers = Customer::all();
        $cash      = PaymentMethod::where('code', 'CASH')->first();
        $bank      = PaymentMethod::where('code', 'BANK')->first();

        if (! $cashierB1 || ! $branchB1 || ! $factory) return;

        $cakeOrders = [
            [
                'order_number'        => 'CKO-2026-0001',
                'customer_id'         => $customers->get(0)?->id,
                'origin_branch_id'    => $branchB1->id,
                'factory_location_id' => $factory->id,
                'created_by'          => $cashierB1->id,
                'assigned_to'         => $designer?->id,
                'status'              => 'completed',
                'payment_status'      => 'paid',
                'payment_arrangement' => 'deposit',
                'required_date'       => now()->subDays(5)->toDateString(),
                'required_time'       => '15:00',
                'cake_type'           => 'layer_cake',
                'cake_size'           => '3_layer',
                'cake_weight'         => 3.0,
                'persons_count'       => 20,
                'flavor'              => 'شوكولاتة بلجيكية',
                'filling'             => 'كريمة موس الشوكولاتة',
                'shape'               => 'دائري',
                'color'               => 'أسود وذهبي',
                'cake_text'           => 'كل عام وأنتم بخير — عيد زواج سعيد',
                'theme'               => 'رومانسي — ذهبي فاخر',
                'special_instructions'=> 'لا تضع سكر في طبقة الكريمة',
                'total_price'         => 350.00,
                'scheduled_at'        => now()->subDays(7),
                'completed_at'        => now()->subDays(5),
            ],
            [
                'order_number'        => 'CKO-2026-0002',
                'customer_id'         => $customers->get(1)?->id,
                'origin_branch_id'    => $branchB1->id,
                'factory_location_id' => $factory->id,
                'created_by'          => $cashierB1->id,
                'assigned_to'         => $designer?->id,
                'status'              => 'in_progress',
                'payment_status'      => 'partially_paid',
                'payment_arrangement' => 'deposit',
                'required_date'       => now()->addDays(3)->toDateString(),
                'required_time'       => '12:00',
                'cake_type'           => 'cheesecake',
                'cake_size'           => '2_layer',
                'cake_weight'         => 2.0,
                'persons_count'       => 15,
                'flavor'              => 'فانيليا مع توت أزرق',
                'filling'             => 'كريمة جبن فيلادلفيا',
                'shape'               => 'مستطيل',
                'color'               => 'أبيض وبنفسجي',
                'cake_text'           => 'عيد ميلاد سعيد نور',
                'theme'               => 'أميرة — باستيل',
                'special_instructions'=> null,
                'total_price'         => 280.00,
                'scheduled_at'        => now()->subDay(),
                'completed_at'        => null,
            ],
            [
                'order_number'        => 'CKO-2026-0003',
                'customer_id'         => $customers->get(2)?->id,
                'origin_branch_id'    => $branchB2 ? $branchB2->id : $branchB1->id,
                'factory_location_id' => $factory->id,
                'created_by'          => $cashierB1->id,
                'assigned_to'         => null,
                'status'              => 'pending',
                'payment_status'      => 'partially_paid',
                'payment_arrangement' => 'deposit',
                'required_date'       => now()->addDays(7)->toDateString(),
                'required_time'       => '17:00',
                'cake_type'           => 'layer_cake',
                'cake_size'           => '4_layer',
                'cake_weight'         => 4.5,
                'persons_count'       => 30,
                'flavor'              => 'ريد فيلفت',
                'filling'             => 'كريمة الجبن',
                'shape'               => 'دائري',
                'color'               => 'أحمر وأبيض',
                'cake_text'           => 'تخرجت بامتياز — مبروك سلمى',
                'theme'               => 'تخرج — ذهبي وأحمر',
                'special_instructions'=> 'أضف شهادة تخرج صغيرة من السكر فوق الكيك',
                'total_price'         => 420.00,
                'scheduled_at'        => null,
                'completed_at'        => null,
            ],
            [
                'order_number'        => 'CKO-2026-0004',
                'customer_id'         => $customers->get(4)?->id,
                'origin_branch_id'    => $branchB1->id,
                'factory_location_id' => $factory->id,
                'created_by'          => $cashierB1->id,
                'assigned_to'         => $designer?->id,
                'status'              => 'ready',
                'payment_status'      => 'paid',
                'payment_arrangement' => 'pay_now',
                'required_date'       => now()->addDay()->toDateString(),
                'required_time'       => '10:00',
                'cake_type'           => 'birthday_cake',
                'cake_size'           => '2_layer',
                'cake_weight'         => 2.5,
                'persons_count'       => 12,
                'flavor'              => 'كراميل مالح',
                'filling'             => 'كريمة الكراميل',
                'shape'               => 'دائري',
                'color'               => 'ذهبي وكريمي',
                'cake_text'           => 'عيد ميلاد سعيد بابا',
                'theme'               => 'عربي كلاسيكي',
                'special_instructions'=> null,
                'total_price'         => 240.00,
                'scheduled_at'        => now()->subDays(2),
                'completed_at'        => null,
            ],
        ];

        foreach ($cakeOrders as $co) {
            $cake = SpecialCakeOrder::firstOrCreate(['order_number' => $co['order_number']], $co);

            // Add deposit payment for partially_paid / paid orders
            if (in_array($co['payment_status'], ['partially_paid', 'paid'])) {
                $deposit = $co['payment_status'] === 'paid' ? $co['total_price'] : round($co['total_price'] * 0.30, 2);
                Payment::firstOrCreate(
                    ['order_id' => $cake->id, 'order_type' => 'special_cake_order'],
                    [
                        'order_type'        => 'special_cake_order',
                        'payment_method_id' => $cash?->id,
                        'location_id'       => $co['origin_branch_id'],
                        'amount'            => $deposit,
                        'status'            => 'confirmed',
                        'received_by'       => $co['created_by'],
                        'paid_at'           => now()->subDays(rand(1, 5)),
                    ]
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // STOCK COUNTS
    // ─────────────────────────────────────────────────────────────
    private function seedStockCounts(): void
    {
        $inventoryMgr = User::where('username', 'inventory_mgr')->first();
        $admin        = User::where('username', 'admin')->first();
        $branchB1     = Location::where('code', 'B01')->first();
        $factory      = Location::where('code', 'FAC')->first();
        $products     = Product::take(5)->get();

        if (! $inventoryMgr || ! $branchB1 || $products->isEmpty()) return;

        // Approved count – last month
        $approved = StockCount::firstOrCreate(
            ['location_id' => $branchB1->id, 'created_by' => $inventoryMgr->id],
            [
                'status'      => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now()->subDays(14),
                'notes'       => 'جرد شهري منتظم',
            ]
        );

        foreach ($products as $product) {
            $system  = rand(15, 25);
            $actual  = $system + rand(-3, 3);
            StockCountItem::firstOrCreate(
                ['stock_count_id' => $approved->id, 'product_id' => $product->id],
                [
                    'system_quantity' => $system,
                    'actual_quantity' => $actual,
                    'variance'        => $actual - $system,
                ]
            );
        }

        // In-progress count – factory
        $inProgress = StockCount::firstOrCreate(
            ['location_id' => $factory->id, 'created_by' => $inventoryMgr->id],
            [
                'status'      => 'in_progress',
                'approved_by' => null,
                'notes'       => 'جرد يومي مصنع',
            ]
        );

        foreach ($products->take(3) as $product) {
            $system = rand(30, 80);
            StockCountItem::firstOrCreate(
                ['stock_count_id' => $inProgress->id, 'product_id' => $product->id],
                [
                    'system_quantity' => $system,
                    'actual_quantity' => $system,
                    'variance'        => 0,
                ]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // STOCK REQUESTS + TRANSFERS
    // ─────────────────────────────────────────────────────────────
    private function seedStockRequests(): void
    {
        $branchMgr = User::where('username', 'branch_b01')->first();
        $facMgr    = User::where('username', 'factory_mgr')->first();
        $branchB1  = Location::where('code', 'B01')->first();
        $factory   = Location::where('code', 'FAC')->first();
        $products  = Product::all()->keyBy('sku');

        if (! $branchMgr || ! $branchB1 || ! $factory) return;

        // Received request
        $sr1 = StockRequest::firstOrCreate(
            ['request_number' => 'SR-2026-0001'],
            [
                'branch_location_id'  => $branchB1->id,
                'factory_location_id' => $factory->id,
                'status'              => 'received',
                'notes'               => 'طلب مخزون أسبوعي',
                'created_by'          => $branchMgr->id,
                'reviewed_by'         => $facMgr?->id,
                'reviewed_at'         => now()->subDays(5),
            ]
        );

        $requestedProducts = [
            ['sku' => 'CK-001', 'requested' => 10, 'approved' => 10],
            ['sku' => 'OR-001', 'requested' => 5,  'approved' => 5],
            ['sku' => 'CH-001', 'requested' => 8,  'approved' => 6],
        ];

        foreach ($requestedProducts as $rp) {
            $product = $products->get($rp['sku']);
            if (! $product) continue;
            StockRequestItem::firstOrCreate(
                ['stock_request_id' => $sr1->id, 'product_id' => $product->id],
                ['requested_quantity' => $rp['requested'], 'approved_quantity' => $rp['approved']]
            );
        }

        // Create the transfer for SR-1
        $transfer1 = StockTransfer::firstOrCreate(
            ['transfer_number' => 'TRF-2026-0001'],
            [
                'stock_request_id' => $sr1->id,
                'from_location_id' => $factory->id,
                'to_location_id'   => $branchB1->id,
                'status'           => 'received',
                'dispatch_notes'   => 'تم الشحن بالكامل',
                'receiving_notes'  => 'تم الاستلام — كل شيء سليم',
                'dispatched_by'    => $facMgr?->id,
                'dispatched_at'    => now()->subDays(5),
                'received_by'      => $branchMgr->id,
                'received_at'      => now()->subDays(4),
            ]
        );

        foreach ($requestedProducts as $rp) {
            $product = $products->get($rp['sku']);
            if (! $product) continue;
            StockTransferItem::firstOrCreate(
                ['stock_transfer_id' => $transfer1->id, 'product_id' => $product->id],
                [
                    'sent_quantity'     => $rp['approved'],
                    'received_quantity' => $rp['approved'],
                    'damaged_quantity'  => 0,
                ]
            );
        }

        // Pending request
        $sr2 = StockRequest::firstOrCreate(
            ['request_number' => 'SR-2026-0002'],
            [
                'branch_location_id'  => $branchB1->id,
                'factory_location_id' => $factory->id,
                'status'              => 'pending_factory_review',
                'notes'               => 'نفاد مخزون كيك الليمون والبراوني',
                'created_by'          => $branchMgr->id,
                'reviewed_by'         => null,
                'reviewed_at'         => null,
            ]
        );

        foreach ([['sku' => 'CK-004', 'requested' => 12], ['sku' => 'WS-003', 'requested' => 20]] as $rp) {
            $product = $products->get($rp['sku']);
            if (! $product) continue;
            StockRequestItem::firstOrCreate(
                ['stock_request_id' => $sr2->id, 'product_id' => $product->id],
                ['requested_quantity' => $rp['requested'], 'approved_quantity' => null]
            );
        }

        // Partially accepted request
        $sr3 = StockRequest::firstOrCreate(
            ['request_number' => 'SR-2026-0003'],
            [
                'branch_location_id'  => $branchB1->id,
                'factory_location_id' => $factory->id,
                'status'              => 'dispatched',
                'notes'               => 'طلب طارئ — هدايا ورمضانيات',
                'created_by'          => $branchMgr->id,
                'reviewed_by'         => $facMgr?->id,
                'reviewed_at'         => now()->subDays(1),
            ]
        );

        foreach ([['sku' => 'GF-001', 'requested' => 15, 'approved' => 10], ['sku' => 'GF-002', 'requested' => 8, 'approved' => 5]] as $rp) {
            $product = $products->get($rp['sku']);
            if (! $product) continue;
            StockRequestItem::firstOrCreate(
                ['stock_request_id' => $sr3->id, 'product_id' => $product->id],
                ['requested_quantity' => $rp['requested'], 'approved_quantity' => $rp['approved']]
            );
        }

        $transfer3 = StockTransfer::firstOrCreate(
            ['transfer_number' => 'TRF-2026-0003'],
            [
                'stock_request_id' => $sr3->id,
                'from_location_id' => $factory->id,
                'to_location_id'   => $branchB1->id,
                'status'           => 'dispatched',
                'dispatch_notes'   => 'في الطريق مع سائق التوصيل',
                'receiving_notes'  => null,
                'dispatched_by'    => $facMgr?->id,
                'dispatched_at'    => now()->subHours(3),
                'received_by'      => null,
                'received_at'      => null,
            ]
        );

        foreach ([['sku' => 'GF-001', 'sent' => 10], ['sku' => 'GF-002', 'sent' => 5]] as $rp) {
            $product = $products->get($rp['sku']);
            if (! $product) continue;
            StockTransferItem::firstOrCreate(
                ['stock_transfer_id' => $transfer3->id, 'product_id' => $product->id],
                ['sent_quantity' => $rp['sent'], 'received_quantity' => 0, 'damaged_quantity' => 0]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // ACTIVITY LOGS
    // ─────────────────────────────────────────────────────────────
    private function seedActivityLogs(): void
    {
        if (DB::table('activity_logs')->exists()) return;

        $admin      = User::where('username', 'admin')->first();
        $cashierB1  = User::where('username', 'cashier_b01')->first();
        $cashierB2  = User::where('username', 'cashier_b02')->first();
        $branchMgr  = User::where('username', 'branch_b01')->first();
        $facMgr     = User::where('username', 'factory_mgr')->first();
        $invMgr     = User::where('username', 'inventory_mgr')->first();
        $accountant = User::where('username', 'accountant')->first();

        if (! $admin) return;

        $logs = [
            // Auth events
            ['user_id' => $admin->id,      'action' => 'login',           'module' => 'auth',      'record_type' => 'User',          'record_id' => $admin->id,      'metadata' => json_encode(['ip' => '192.168.1.1']),   'created_at' => now()->subDays(5)->subHours(1)],
            ['user_id' => $cashierB1?->id, 'action' => 'login',           'module' => 'auth',      'record_type' => 'User',          'record_id' => $cashierB1?->id, 'metadata' => json_encode(['ip' => '192.168.1.10']),  'created_at' => now()->subDays(3)->setTime(8, 55)],
            ['user_id' => $cashierB2?->id, 'action' => 'login',           'module' => 'auth',      'record_type' => 'User',          'record_id' => $cashierB2?->id, 'metadata' => json_encode(['ip' => '192.168.1.20']),  'created_at' => now()->subDays(1)->setTime(9, 2)],
            // Order events
            ['user_id' => $cashierB1?->id, 'action' => 'create',          'module' => 'orders',    'record_type' => 'Order',         'record_id' => 1,               'metadata' => json_encode(['order_number' => 'ORD-2026-0001', 'total' => 180.00]), 'created_at' => now()->subDays(5)],
            ['user_id' => $cashierB1?->id, 'action' => 'status_changed',  'module' => 'orders',    'record_type' => 'Order',         'record_id' => 1,               'old_values' => json_encode(['status' => 'confirmed']), 'new_values' => json_encode(['status' => 'completed']), 'created_at' => now()->subDays(5)->addHours(1)],
            ['user_id' => $cashierB1?->id, 'action' => 'create',          'module' => 'orders',    'record_type' => 'Order',         'record_id' => 2,               'metadata' => json_encode(['order_number' => 'ORD-2026-0002', 'total' => 110.00]), 'created_at' => now()->subDays(4)],
            ['user_id' => $cashierB2?->id, 'action' => 'create',          'module' => 'orders',    'record_type' => 'Order',         'record_id' => 6,               'metadata' => json_encode(['order_number' => 'ORD-2026-0006', 'total' => 245.00]), 'created_at' => now()->subDays(1)],
            // Payment events
            ['user_id' => $cashierB1?->id, 'action' => 'create',          'module' => 'payments',  'record_type' => 'Payment',       'record_id' => 1,               'metadata' => json_encode(['amount' => 180.00, 'method' => 'CASH']),   'created_at' => now()->subDays(5)->addMinutes(5)],
            ['user_id' => $accountant?->id,'action' => 'verify',          'module' => 'payments',  'record_type' => 'Payment',       'record_id' => 2,               'metadata' => json_encode(['amount' => 110.00, 'method' => 'CARD']),   'created_at' => now()->subDays(4)->addHours(2)],
            // Invoice events
            ['user_id' => $cashierB1?->id, 'action' => 'create',          'module' => 'invoices',  'record_type' => 'Invoice',       'record_id' => 1,               'metadata' => json_encode(['invoice_number' => 'DH-00001', 'total' => 180.00]), 'created_at' => now()->subDays(5)->addMinutes(2)],
            ['user_id' => $cashierB1?->id, 'action' => 'print',           'module' => 'invoices',  'record_type' => 'Invoice',       'record_id' => 1,               'metadata' => json_encode(['format' => 'pdf']),  'created_at' => now()->subDays(5)->addMinutes(4)],
            // Cake order events
            ['user_id' => $cashierB1?->id, 'action' => 'create',          'module' => 'cake_orders','record_type' => 'SpecialCakeOrder','record_id' => 1,             'metadata' => json_encode(['order_number' => 'CKO-2026-0001', 'total' => 350.00]), 'created_at' => now()->subDays(7)],
            ['user_id' => $facMgr?->id,    'action' => 'status_changed',  'module' => 'cake_orders','record_type' => 'SpecialCakeOrder','record_id' => 1,             'old_values' => json_encode(['status' => 'pending']), 'new_values' => json_encode(['status' => 'in_progress']), 'created_at' => now()->subDays(7)->addHours(2)],
            ['user_id' => $facMgr?->id,    'action' => 'status_changed',  'module' => 'cake_orders','record_type' => 'SpecialCakeOrder','record_id' => 1,             'old_values' => json_encode(['status' => 'in_progress']), 'new_values' => json_encode(['status' => 'completed']), 'created_at' => now()->subDays(5)->addHours(3)],
            // Stock events
            ['user_id' => $branchMgr?->id, 'action' => 'create',          'module' => 'stock',     'record_type' => 'StockRequest',  'record_id' => 1,               'metadata' => json_encode(['request_number' => 'SR-2026-0001']),  'created_at' => now()->subDays(6)],
            ['user_id' => $facMgr?->id,    'action' => 'review',          'module' => 'stock',     'record_type' => 'StockRequest',  'record_id' => 1,               'new_values' => json_encode(['status' => 'approved']),           'created_at' => now()->subDays(5)->subHours(12)],
            ['user_id' => $facMgr?->id,    'action' => 'dispatch',        'module' => 'stock',     'record_type' => 'StockTransfer', 'record_id' => 1,               'metadata' => json_encode(['transfer_number' => 'TRF-2026-0001']),'created_at' => now()->subDays(5)],
            ['user_id' => $branchMgr?->id, 'action' => 'receive',         'module' => 'stock',     'record_type' => 'StockTransfer', 'record_id' => 1,               'new_values' => json_encode(['status' => 'received']),           'created_at' => now()->subDays(4)],
            ['user_id' => $invMgr?->id,    'action' => 'adjust',          'module' => 'inventory', 'record_type' => 'Inventory',     'record_id' => 3,               'old_values' => json_encode(['quantity' => 12]), 'new_values' => json_encode(['quantity' => 18]), 'metadata' => json_encode(['reason' => 'جرد شهري']), 'created_at' => now()->subDays(2)],
            ['user_id' => $invMgr?->id,    'action' => 'create',          'module' => 'stock',     'record_type' => 'StockCount',    'record_id' => 1,               'metadata' => json_encode(['status' => 'approved']),             'created_at' => now()->subDays(14)],
            // User management
            ['user_id' => $admin->id,      'action' => 'create',          'module' => 'users',     'record_type' => 'User',          'record_id' => $cashierB1?->id, 'metadata' => json_encode(['username' => 'cashier_b01', 'role' => 'Cashier']),    'created_at' => now()->subMonths(2)],
            ['user_id' => $admin->id,      'action' => 'update',          'module' => 'settings',  'record_type' => 'SystemSetting', 'record_id' => null,            'new_values' => json_encode(['notification_sound_enabled' => '1']),              'created_at' => now()->subDays(10)],
            // Report generation
            ['user_id' => $accountant?->id,'action' => 'export',          'module' => 'reports',   'record_type' => 'Report',        'record_id' => null,            'metadata' => json_encode(['type' => 'invoices', 'format' => 'pdf']),            'created_at' => now()->subDays(1)->setTime(10, 0)],
            ['user_id' => $admin->id,      'action' => 'export',          'module' => 'reports',   'record_type' => 'Report',        'record_id' => null,            'metadata' => json_encode(['type' => 'orders',   'format' => 'excel']),          'created_at' => now()->subHours(4)],
        ];

        foreach ($logs as $log) {
            if (empty($log['user_id'])) continue;
            DB::table('activity_logs')->insert(array_merge([
                'old_values' => null,
                'new_values' => null,
                'metadata'   => null,
                'ip_address' => null,
                'created_at' => now(),
            ], $log));
        }
    }

    // ─────────────────────────────────────────────────────────────
    // NOTIFICATIONS  (database channel — all types, mix of read/unread)
    // ─────────────────────────────────────────────────────────────
    private function seedNotifications(): void
    {
        if (DB::table('notifications')->exists()) return;

        $admin     = User::where('username', 'admin')->first();
        $branchMgr = User::where('username', 'branch_b01')->first();
        $facMgr    = User::where('username', 'factory_mgr')->first();
        $invMgr    = User::where('username', 'inventory_mgr')->first();
        $cashierB1 = User::where('username', 'cashier_b01')->first();
        $accountant= User::where('username', 'accountant')->first();

        $branchB1  = Location::where('code', 'B01')->first();
        $factory   = Location::where('code', 'FAC')->first();

        $orders    = Order::all()->keyBy('order_number');
        $cakeOrders= SpecialCakeOrder::all()->keyBy('order_number');

        if (! $admin) return;

        // Helper: build a notification row
        $row = function (User $user, string $type, array $data, bool $read, string $createdAt) {
            return [
                'id'              => \Illuminate\Support\Str::uuid()->toString(),
                'type'            => $type,
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id'   => $user->id,
                'data'            => json_encode($data),
                'read_at'         => $read ? $createdAt : null,
                'created_at'      => $createdAt,
                'updated_at'      => $createdAt,
            ];
        };

        $notifications = [];

        // ── 1. Order Created ─────────────────────────────────────
        $ord1 = $orders->get('ORD-2026-0001');
        if ($ord1) {
            $data = [
                'fingerprint'  => 'order_created_' . $ord1->id,
                'type'         => 'order_created',
                'title'        => 'طلب جديد',
                'message'      => "تم إنشاء طلب جديد رقم {$ord1->order_number} بقيمة ₪{$ord1->total_amount}",
                'order_id'     => $ord1->id,
                'order_number' => $ord1->order_number,
                'location_id'  => $ord1->location_id,
                'total_amount' => $ord1->total_amount,
                'url'          => '/orders/' . $ord1->id,
                'priority'     => 'medium',
            ];
            // Admin sees it — already read
            $notifications[] = $row($admin,     'App\\Notifications\\OrderCreatedNotification', $data, true,  now()->subDays(5)->toDateTimeString());
            // Branch manager — unread
            if ($branchMgr) $notifications[] = $row($branchMgr, 'App\\Notifications\\OrderCreatedNotification', $data, false, now()->subDays(5)->toDateTimeString());
        }

        $ord5 = $orders->get('ORD-2026-0005');
        if ($ord5) {
            $data = [
                'fingerprint'  => 'order_created_' . $ord5->id,
                'type'         => 'order_created',
                'title'        => 'طلب جديد',
                'message'      => "تم إنشاء طلب جديد رقم {$ord5->order_number} بقيمة ₪{$ord5->total_amount}",
                'order_id'     => $ord5->id,
                'order_number' => $ord5->order_number,
                'location_id'  => $ord5->location_id,
                'total_amount' => $ord5->total_amount,
                'url'          => '/orders/' . $ord5->id,
                'priority'     => 'medium',
            ];
            if ($admin)    $notifications[] = $row($admin,    'App\\Notifications\\OrderCreatedNotification', $data, false, now()->subHours(2)->toDateTimeString());
            if ($branchMgr)$notifications[] = $row($branchMgr,'App\\Notifications\\OrderCreatedNotification', $data, false, now()->subHours(2)->toDateTimeString());
        }

        // ── 2. Order Status Changed ──────────────────────────────
        if ($ord1) {
            $data = [
                'fingerprint'  => 'order_status_' . $ord1->id . '_completed',
                'type'         => 'order_status_changed',
                'title'        => 'تم تحديث حالة الطلب',
                'message'      => "الطلب رقم {$ord1->order_number} أصبح مكتملاً",
                'order_id'     => $ord1->id,
                'order_number' => $ord1->order_number,
                'old_status'   => 'confirmed',
                'new_status'   => 'completed',
                'url'          => '/orders/' . $ord1->id,
                'priority'     => 'low',
            ];
            if ($admin)    $notifications[] = $row($admin,    'App\\Notifications\\OrderStatusChangedNotification', $data, true,  now()->subDays(5)->addHour()->toDateTimeString());
            if ($cashierB1)$notifications[] = $row($cashierB1,'App\\Notifications\\OrderStatusChangedNotification', $data, false, now()->subDays(5)->addHour()->toDateTimeString());
        }

        // ── 3. Payment Received ──────────────────────────────────
        $payments = Payment::with('paymentMethod')->take(3)->get();
        foreach ($payments as $payment) {
            $data = [
                'fingerprint'    => 'payment_received_' . $payment->id,
                'type'           => 'payment_received',
                'title'          => 'دفعة جديدة',
                'message'        => "تم استلام دفعة بقيمة ₪" . number_format($payment->amount, 2) . " عبر " . ($payment->paymentMethod?->name_ar ?? 'غير محدد'),
                'payment_id'     => $payment->id,
                'amount'         => $payment->amount,
                'payment_method' => $payment->paymentMethod?->name_ar,
                'url'            => '/payments',
                'priority'       => 'medium',
            ];
            $isRead = $payment->id <= 2; // first two are read
            if ($admin)     $notifications[] = $row($admin,     'App\\Notifications\\PaymentReceivedNotification', $data, $isRead, ($payment->paid_at ?? now()->subDays(3))->toDateTimeString());
            if ($accountant)$notifications[] = $row($accountant,'App\\Notifications\\PaymentReceivedNotification', $data, $isRead, ($payment->paid_at ?? now()->subDays(3))->toDateTimeString());
        }

        // ── 4. Low Stock Detected ────────────────────────────────
        // Force a couple of low-stock situations for testing
        $lowStockItems = Inventory::with(['product', 'location'])
            ->where('quantity', '>', 0)
            ->take(3)
            ->get();

        foreach ($lowStockItems as $inv) {
            $productName  = $inv->product?->name_ar ?? "منتج #{$inv->product_id}";
            $locationName = $inv->location?->name    ?? "فرع #{$inv->location_id}";
            $fakeQty      = 2; // simulate critically low
            $minLevel     = 5;

            $data = [
                'fingerprint'      => "low_stock_{$inv->location_id}_{$inv->product_id}",
                'type'             => 'low_stock_detected',
                'title'            => 'تحذير: مخزون منخفض',
                'message'          => "مخزون [{$productName}] في [{$locationName}] وصل إلى {$fakeQty} (الحد الأدنى: {$minLevel})",
                'inventory_id'     => $inv->id,
                'location_id'      => $inv->location_id,
                'product_id'       => $inv->product_id,
                'current_quantity' => $fakeQty,
                'minimum_level'    => $minLevel,
                'url'              => '/inventory',
                'priority'         => 'high',
            ];
            // Admins + inventory manager get these — leave unread so badge shows
            if ($admin) $notifications[] = $row($admin, 'App\\Notifications\\LowStockDetectedNotification', $data, false, now()->subHours(rand(1, 8))->toDateTimeString());
            if ($invMgr)$notifications[] = $row($invMgr,'App\\Notifications\\LowStockDetectedNotification', $data, false, now()->subHours(rand(1, 8))->toDateTimeString());
        }

        // ── 5. Special Cake Order Transitions ───────────────────
        $ck1 = $cakeOrders->get('CKO-2026-0001');
        if ($ck1) {
            // accepted by factory
            $data = [
                'fingerprint'  => 'cake_order_transitioned_' . $ck1->id . '_in_progress',
                'type'         => 'special_cake_order_transitioned',
                'title'        => 'تحديث طلب الكيك',
                'message'      => "طلب الكيك رقم {$ck1->order_number} انتقل إلى قيد التنفيذ",
                'order_id'     => $ck1->id,
                'order_number' => $ck1->order_number,
                'old_status'   => 'pending',
                'new_status'   => 'in_progress',
                'url'          => '/cake-orders/' . $ck1->id,
                'priority'     => 'medium',
            ];
            if ($admin)    $notifications[] = $row($admin,    'App\\Notifications\\SpecialCakeOrderTransitionedNotification', $data, true,  now()->subDays(7)->addHours(2)->toDateTimeString());
            if ($cashierB1)$notifications[] = $row($cashierB1,'App\\Notifications\\SpecialCakeOrderTransitionedNotification', $data, true,  now()->subDays(7)->addHours(2)->toDateTimeString());

            // completed
            $dataComplete = array_merge($data, [
                'fingerprint' => 'cake_order_transitioned_' . $ck1->id . '_completed',
                'message'     => "طلب الكيك رقم {$ck1->order_number} اكتمل وجاهز للتسليم",
                'old_status'  => 'in_progress',
                'new_status'  => 'completed',
            ]);
            if ($admin)    $notifications[] = $row($admin,    'App\\Notifications\\SpecialCakeOrderTransitionedNotification', $dataComplete, true,  now()->subDays(5)->addHours(3)->toDateTimeString());
            if ($cashierB1)$notifications[] = $row($cashierB1,'App\\Notifications\\SpecialCakeOrderTransitionedNotification', $dataComplete, false, now()->subDays(5)->addHours(3)->toDateTimeString());
        }

        $ck4 = $cakeOrders->get('CKO-2026-0004');
        if ($ck4) {
            $data = [
                'fingerprint'  => 'cake_order_transitioned_' . $ck4->id . '_ready',
                'type'         => 'special_cake_order_transitioned',
                'title'        => 'طلب الكيك جاهز',
                'message'      => "طلب الكيك رقم {$ck4->order_number} جاهز للاستلام من الفرع",
                'order_id'     => $ck4->id,
                'order_number' => $ck4->order_number,
                'old_status'   => 'in_progress',
                'new_status'   => 'ready',
                'url'          => '/cake-orders/' . $ck4->id,
                'priority'     => 'high',
            ];
            // Leave unread — will trigger the sound badge on login
            if ($admin)    $notifications[] = $row($admin,    'App\\Notifications\\SpecialCakeOrderTransitionedNotification', $data, false, now()->subHours(1)->toDateTimeString());
            if ($cashierB1)$notifications[] = $row($cashierB1,'App\\Notifications\\SpecialCakeOrderTransitionedNotification', $data, false, now()->subHours(1)->toDateTimeString());
            if ($branchMgr)$notifications[] = $row($branchMgr,'App\\Notifications\\SpecialCakeOrderTransitionedNotification', $data, false, now()->subHours(1)->toDateTimeString());
        }

        // ── Bulk insert ──────────────────────────────────────────
        foreach (array_chunk($notifications, 50) as $chunk) {
            DB::table('notifications')->insert($chunk);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // REPORT SCHEDULES
    // ─────────────────────────────────────────────────────────────
    private function seedReportSchedules(): void
    {
        $admin    = User::where('username', 'admin')->first();
        $branchB1 = Location::where('code', 'B01')->first();

        if (! $admin) return;

        $schedules = [
            [
                'name'        => 'تقرير المبيعات الأسبوعي',
                'report_type' => 'sales_summary',
                'frequency'   => 'weekly',
                'day_of_week' => 1, // Monday
                'hour'        => 8,
                'recipients'  => json_encode(['admin@dahabsweets.com', 'accountant@dahabsweets.com']),
                'location_id' => null,
                'date_range'  => 'last_week',
                'is_active'   => true,
                'created_by'  => $admin->id,
            ],
            [
                'name'        => 'تقرير المخزون اليومي',
                'report_type' => 'inventory_levels',
                'frequency'   => 'daily',
                'day_of_week' => null,
                'hour'        => 7,
                'recipients'  => json_encode(['inventory@dahabsweets.com', 'factory.mgr@dahabsweets.com']),
                'location_id' => null,
                'date_range'  => 'today',
                'is_active'   => true,
                'created_by'  => $admin->id,
            ],
            [
                'name'        => 'تقرير الفرع الشهري — رام الله',
                'report_type' => 'branch_performance',
                'frequency'   => 'monthly',
                'day_of_week' => null,
                'hour'        => 9,
                'recipients'  => json_encode(['branch.b01@dahabsweets.com', 'admin@dahabsweets.com']),
                'location_id' => $branchB1?->id,
                'date_range'  => 'last_month',
                'is_active'   => true,
                'created_by'  => $admin->id,
            ],
        ];

        foreach ($schedules as $s) {
            ReportSchedule::firstOrCreate(['name' => $s['name']], $s);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // UNIFIED MODULES + CLIENT PROFILES (no child seeders)
    // ─────────────────────────────────────────────────────────────
    private function seedBusinessProfilesAndModules(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('business_profiles')) return;

        $modules = [];
        foreach (ModuleRegistry::modules() as $definition) {
            $module = Module::query()->firstOrNew(['code' => $definition['code']]);
            $isNew = ! $module->exists;
            $module->fill([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'type' => $definition['type'],
                'icon' => $definition['icon'],
                'route_prefix' => $definition['route_prefix'],
                'is_core' => $definition['is_core'],
                'is_system' => $definition['is_system'],
                'sort_order' => $definition['sort_order'],
                'configuration' => [
                    'implemented' => (bool) $definition['implemented'],
                    'registered_by' => 'DatabaseSeeder',
                ],
            ]);
            if ($isNew) {
                $module->is_active = $definition['code'] === 'chat'
                    ? (bool) SystemSetting::get('chat_enabled', true)
                    : (bool) ($definition['initial_active'] ?? $definition['implemented']);
            }
            $module->save();
            $modules[$module->code] = $module;
        }

        if (Schema::hasTable('module_dependencies')) {
            foreach (ModuleRegistry::dependencies() as $code => $requiredCodes) {
                if (! isset($modules[$code])) continue;
                $modules[$code]->dependencies()->sync(
                    collect($requiredCodes)->map(fn ($required) => $modules[$required]->id ?? null)->filter()->all()
                );
            }
        }

        foreach (BusinessProfileRegistry::profiles() as $definition) {
            $profile = BusinessProfile::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'icon' => $definition['icon'],
                    'is_active' => true,
                    'is_system' => true,
                    'sort_order' => $definition['sort_order'],
                    'configuration' => ['registered_by' => 'DatabaseSeeder'],
                ]
            );
            if (Schema::hasTable('business_profile_modules')) {
                $pivot = [];
                foreach (array_values(array_unique($definition['modules'])) as $index => $code) {
                    if (! isset($modules[$code])) continue;
                    $pivot[$modules[$code]->id] = [
                        'is_required' => (bool) $modules[$code]->is_system,
                        'is_default' => true,
                        'sort_order' => ($index + 1) * 10,
                    ];
                }
                $profile->modules()->sync($pivot);
            }
        }

        if (Schema::hasTable('module_bundles') && Schema::hasTable('module_bundle_modules')) {
            foreach (BusinessProfileRegistry::bundles() as $definition) {
                $profileId = $definition['profile']
                    ? BusinessProfile::query()->where('code', $definition['profile'])->value('id')
                    : null;
                $bundle = ModuleBundle::query()->updateOrCreate(
                    ['code' => $definition['code']],
                    [
                        'business_profile_id' => $profileId,
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'is_active' => true,
                        'sort_order' => $definition['sort_order'],
                        'configuration' => ['registered_by' => 'DatabaseSeeder'],
                    ]
                );
                $bundlePivot = [];
                foreach ($definition['modules'] as $index => $code) {
                    if (isset($modules[$code])) {
                        $bundlePivot[$modules[$code]->id] = ['sort_order' => ($index + 1) * 10];
                    }
                }
                $bundle->modules()->sync($bundlePivot);
            }
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => 'business_profile_code'],
            [
                'value' => 'bakery_sweets', 'type' => 'string', 'group' => 'business',
                'label' => 'نوع النشاط', 'description' => 'ملف النشاط الحالي للنظام.',
            ]
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'client_onboarding_completed'],
            [
                'value' => '0', 'type' => 'boolean', 'group' => 'onboarding',
                'label' => 'اكتمل إعداد العميل', 'description' => 'حالة معالج إعداد العميل.',
            ]
        );
        SystemSetting::flushCache();
        Cache::forget('business-profile:current:v1');
        app(ModuleService::class)->invalidate();
    }

    // ─────────────────────────────────────────────────────────────
    // ONE FINAL, DETERMINISTIC PERMISSION MATRIX
    // ─────────────────────────────────────────────────────────────
    private function seedUnifiedPermissionMatrix(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $routePermissions = collect(app('router')->getRoutes()->getRoutes())
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'can:'))
            ->map(fn (string $middleware) => trim(explode(',', substr($middleware, 4))[0]))
            ->filter()->unique()->values();

        $featurePermissions = collect([
            'dashboard.view','dashboard.procurement','dashboard.orders','dashboard.sales',
            'dashboard.inventory','dashboard.cash','dashboard.collections','dashboard.revenue_chart',
            'dashboard.products_chart','dashboard.stock_requests','dashboard.cake_orders',
            'dashboard.cake_pipeline','dashboard.branch_comparison','dashboard.profit',
            'orders.view','orders.create','orders.update','orders.delete',
            'orders.confirm','orders.cancel','orders.complete',
            'suppliers.view','suppliers.create','suppliers.update','suppliers.delete',
            'purchase_orders.view','purchase_orders.create','purchase_orders.update',
            'purchase_orders.approve','purchase_orders.cancel',
            'goods_receipts.view','goods_receipts.create','goods_receipts.approve',
            'purchase_returns.view','purchase_returns.create','purchase_returns.approve','purchase_returns.cancel',
            'supplier_invoices.view','supplier_invoices.create','supplier_invoices.cancel',
            'supplier_payments.view','supplier_payments.create',
            'procurement.exchange_rates.view','procurement.exchange_rates.manage','procurement.reports.view',
            'stock_requests.view','stock_requests.create','stock_requests.review',
            'stock_transfers.view','stock_transfers.dispatch','stock_transfers.receive',
            'inventory.expiry-alerts.view','inventory.expiry-alerts.receive',
            'inventory.expiry-alerts.receive_all','inventory.expiry-alerts.settings',
            'cash_sessions.manage','receiving_invoices.view',
            'chat.view','chat.send','chat.attachments','chat.view_all_branches','chat.manage',
            'chat.direct.start_all','chat.direct.start_location',
            'crm.view','crm.view_all','crm.manage','crm.interactions.manage',
            'loyalty.view','loyalty.manage','loyalty.adjust','loyalty.redeem',
            'delivery.view','delivery.view_all_locations','delivery.zones.manage',
            'delivery.create','delivery.assign','delivery.update_status',
            'costing.view','costing.manage','costing.backfill','costing.view_all_locations',
            'expense_categories.manage','expenses.view','expenses.create','expenses.update',
            'expenses.submit','expenses.approve','expenses.approve_own','expenses.post',
            'expenses.void','expenses.view_all_locations',
            'cake_orders.view','cake_orders.view_all','cake_orders.create','cake_orders.edit',
            'cake_orders.delete','cake_orders.manage','cake_orders.cancel','cake_orders.complete',
            'cake_orders.review','cake_orders.accept','cake_orders.reject',
            'cake_orders.request_modification','cake_orders.schedule','cake_orders.prepare',
            'cake_orders.decorate','cake_orders.quality_check','cake_orders.dispatch','cake_orders.receive',
            'showroom_sweets_requests.view','showroom_sweets_requests.view_all',
            'showroom_sweets_requests.create','showroom_sweets_requests.start',
            'showroom_sweets_requests.ready','showroom_sweets_requests.dispatch',
            'showroom_sweets_requests.receive','showroom_sweets_requests.reject',
            'showroom_sweets_requests.cancel','showroom_sweets_requests.delete',
            'restaurant.view','restaurant.view_all_locations','restaurant_pos.use',
            'restaurant_tables.view','restaurant_tables.manage',
            'restaurant_tables.open_session','restaurant_tables.close_session',
            'customer_display.view','notifications.manage','payment_methods.manage',
            'sales_channels.view','sales_channels.create','sales_channels.update',
            'sales_channels.delete','sales_channels.activate','sales_channels.reports',
            'system_currencies.view','system_currencies.manage',
            'attendance.view','attendance.manage','attendance.approve','attendance.shifts.manage',
            'attendance.leaves.view','attendance.leaves.manage','attendance.leaves.approve',
            'attendance.devices.view','attendance.devices.manage','payroll.attendance.sync',
            'payroll.view','payroll.manage','payroll.approve','payroll.pay',
            'payroll.adjustments.manage','payroll.advances.manage','employee_ledger.view',
            'payroll.documents.print','payroll.reports.view','payroll.payments.verify','payroll.payments.void',
            'restaurant.pos.view','restaurant.pos.create_order','restaurant.tables.view','restaurant.tables.manage',
            'kitchen.view','kitchen.manage','kitchen.tickets.update','kitchen.view_all_locations',
            'kitchen.stations.manage','kitchen.ticket.start','kitchen.ticket.ready',
            'kitchen.ticket.serve','kitchen.ticket.priority','kds.view',
            'recipes.view','recipes.manage','recipes.approve','recipes.cost.view',
            'production.view','production.view_all_locations','production.create','production.release',
            'production.start','production.finish','production.cancel','production.cost.view',
            'quality_control.view','quality_control.decide','quality_control.inspect',
        ]);

        $allNames = $routePermissions->merge($featurePermissions)->unique()->values();
        foreach ($allNames as $name) Permission::findOrCreate($name, 'web');

        $all = Permission::query()->where('guard_name', 'web')->get();
        Role::findOrCreate('Admin', 'web')->syncPermissions($all);
        Role::findOrCreate('super-admin', 'web')->syncPermissions($all);

        $grantByPrefixes = function (string $roleName, array $prefixes, array $extra = [], array $exclude = []): void {
            $permissions = Permission::query()->where('guard_name', 'web')->get()
                ->filter(function (Permission $permission) use ($prefixes, $extra, $exclude): bool {
                    if (in_array($permission->name, $exclude, true)) return false;
                    if (in_array($permission->name, $extra, true)) return true;
                    return collect($prefixes)->contains(
                        fn (string $prefix) => str_starts_with($permission->name, $prefix)
                    );
                });
            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        };

        $grantByPrefixes('General Manager', [
            'dashboard.','locations.','employees.','products.','categories.','customers.',
            'orders.','inventory.','stock_','reports.','financial.','invoices.','payments.',
            'suppliers.','supplier_','purchase_','goods_','procurement.','recipes.','production.','quality_control.',
            'payroll.','employee_ledger.','attendance.','restaurant.','kitchen.','kds.',
            'chat.','crm.','loyalty.','delivery.','costing.','expenses.','expense_categories.',
            'sales_channels.','customer_display.','cash_sessions.',
        ]);
        $grantByPrefixes('Branch Manager', [
            'dashboard.','employees.','products.','categories.','customers.','orders.',
            'inventory.','stock_','reports.','invoices.','payments.','restaurant.','kitchen.','kds.',
            'attendance.','chat.','crm.','loyalty.','delivery.','customer_display.','cash_sessions.',
        ], [], ['orders.confirm','orders.cancel','customers.view_all','employees.view_all']);
        $grantByPrefixes('Cashier', ['orders.','customers.','payments.','invoices.','restaurant.pos.'], [
            'products.view','categories.view','payment_methods.view',
        ], ['orders.confirm','orders.cancel','orders.delete','customers.view_all']);
        $grantByPrefixes('Inventory Manager', [
            'inventory.','stock_','products.','categories.','suppliers.','supplier_','purchase_','goods_','receiving_',
        ], ['production.view']);
        $grantByPrefixes('Accountant', [
            'financial.','invoices.','payments.','payment_methods.','reports.','suppliers.',
            'purchase_','supplier_','payroll.','employee_ledger.','recipes.cost.','production.cost.',
        ], ['attendance.view']);
        $grantByPrefixes('Factory Manager', [
            'production.','recipes.','quality_control.','inventory.','stock_','products.',
            'showroom_','cake_','attendance.',
        ]);
        $grantByPrefixes('Cake Designer', ['cake_','special_cake_'], ['products.view']);
        $grantByPrefixes('Waiter', ['restaurant.','orders.'], ['products.view','customers.view'], [
            'orders.confirm','orders.cancel','orders.delete','orders.update',
        ]);
        $grantByPrefixes('Kitchen Staff', ['kitchen.','kds.'], ['orders.view','products.view']);
        $grantByPrefixes('Production Employee', ['production.'], ['recipes.view','inventory.view'], [
            'production.cancel','production.cost.view','production.view_all_locations',
        ]);
        $grantByPrefixes('Quality Control', ['quality_control.'], ['production.view']);
        $grantByPrefixes('Branch Employee', ['orders.','customers.','restaurant.'], [
            'products.view','inventory.view',
        ], ['orders.confirm','orders.cancel','orders.delete','customers.view_all']);
        $grantByPrefixes('Dispatcher', ['delivery.','stock_transfers.'], [
            'orders.view','locations.view',
        ]);
        $grantByPrefixes('Delivery Driver', ['delivery.'], ['orders.view']);

        $this->ensureDemoUser('DEMO-WA-001', 'نادل تجريبي', 'demo.waiter', 'Waiter', 'B01');
        $this->ensureDemoUser('DEMO-KI-001', 'موظف مطبخ تجريبي', 'demo.kitchen', 'Kitchen Staff', 'B01');
        $this->ensureDemoUser('DEMO-PR-001', 'موظف إنتاج تجريبي', 'demo.production', 'Production Employee', 'FAC');
        $this->ensureDemoUser('DEMO-QC-001', 'موظف جودة تجريبي', 'demo.quality', 'Quality Control', 'FAC');
        $this->ensureDemoUser('DEMO-BM-004', 'مدير فرع خانيونس', 'demo.manager.b04', 'Branch Manager', 'B04');
        $this->ensureDemoUser('DEMO-BM-005', 'مدير فرع مصر', 'demo.manager.b05', 'Branch Manager', 'B05');
        $this->ensureDemoUser('DEMO-CA-003', 'كاشير فرع دير البلح', 'demo.cashier.b03', 'Cashier', 'B03');
        $this->ensureDemoUser('DEMO-CA-004', 'كاشير فرع خانيونس', 'demo.cashier.b04', 'Cashier', 'B04');
        $this->ensureDemoUser('DEMO-CA-005', 'كاشير فرع مصر', 'demo.cashier.b05', 'Cashier', 'B05');
        $this->ensureDemoUser('DEMO-WA-002', 'نادل فرع النصيرات', 'demo.waiter.b02', 'Waiter', 'B02');
        $this->ensureDemoUser('DEMO-WA-003', 'نادل فرع دير البلح', 'demo.waiter.b03', 'Waiter', 'B03');
        $this->ensureDemoUser('DEMO-WA-004', 'نادل فرع خانيونس', 'demo.waiter.b04', 'Waiter', 'B04');
        $this->ensureDemoUser('DEMO-WA-005', 'نادل فرع مصر', 'demo.waiter.b05', 'Waiter', 'B05');
        $this->ensureDemoUser('DEMO-KI-002', 'طاهي فرع النصيرات', 'demo.kitchen.b02', 'Kitchen Staff', 'B02');
        $this->ensureDemoUser('DEMO-KI-003', 'طاهي فرع دير البلح', 'demo.kitchen.b03', 'Kitchen Staff', 'B03');
        $this->ensureDemoUser('DEMO-BA-001', 'محضّر مشروبات وميلك شيك', 'demo.barista', 'Branch Employee', 'B01');
        $this->ensureDemoUser('DEMO-PR-002', 'حلواني شرقي', 'demo.oriental.chef', 'Production Employee', 'FAC');
        $this->ensureDemoUser('DEMO-PR-003', 'حلواني غربي', 'demo.pastry.chef', 'Production Employee', 'FAC');
        $this->ensureDemoUser('DEMO-DI-001', 'منسق التوصيل', 'demo.dispatcher', 'Dispatcher', 'B01');
        $this->ensureDemoUser('DEMO-DD-001', 'سائق توصيل', 'demo.driver', 'Delivery Driver', 'B01');
        $this->ensureDemoUser('DEMO-AC-002', 'محاسب رواتب', 'demo.payroll.accountant', 'Accountant', 'FAC');
        $this->ensureDemoUser('DEMO-IV-002', 'أمين مستودع المصنع', 'demo.storekeeper', 'Inventory Manager', 'FAC');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensureDemoUser(string $number, string $name, string $username, string $role, string $locationCode): void
    {
        $location = Location::query()->where('code', $locationCode)->firstOrFail();
        $employee = Employee::query()->updateOrCreate(
            ['employee_number' => $number],
            [
                'full_name' => $name, 'email' => $username.'@dahab.test', 'job_title' => $role,
                'hire_date' => now()->subYear()->toDateString(), 'employment_status' => 'active',
            ]
        );
        EmployeeLocation::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'location_id' => $location->id],
            ['is_primary' => true, 'started_at' => now()->subYear()->toDateString(), 'ended_at' => null]
        );
        $user = User::query()->updateOrCreate(
            ['username' => $username],
            [
                'employee_id' => $employee->id, 'email' => $username.'@dahab.test',
                'password' => Hash::make('Demo@2026!'), 'is_active' => true,
                'must_change_password' => false, 'failed_login_attempts' => 0,
            ]
        );
        $user->syncRoles([Role::findOrCreate($role, 'web')]);
    }

    private function seedRestaurantAndKitchenDemo(): void
    {
        $adminId = User::query()->where('username', 'admin')->value('id');
        foreach (Location::query()->where('type', 'branch')->where('is_active', true)->get() as $branch) {
            $areaId = null;
            if (Schema::hasTable('restaurant_areas')) {
                $areaId = RestaurantArea::query()->updateOrCreate(
                    ['location_id' => $branch->id, 'name' => 'الصالة الرئيسية'],
                    ['code' => 'MAIN', 'sort_order' => 10, 'is_active' => true]
                )->id;
            }
            if (Schema::hasTable('restaurant_tables')) {
                foreach (range(1, 8) as $number) {
                    RestaurantTable::query()->updateOrCreate(
                        ['location_id' => $branch->id, 'code' => sprintf('T%02d', $number)],
                        [
                            'area_id' => $areaId, 'name' => 'طاولة '.$number,
                            'capacity' => $number % 3 === 0 ? 6 : 4,
                            'sort_order' => $number * 10, 'is_active' => true,
                        ]
                    );
                }
            }
            if (Schema::hasTable('kitchen_stations')) {
                DB::table('kitchen_stations')->updateOrInsert(
                    ['location_id' => $branch->id, 'code' => 'MAIN'],
                    [
                        'name' => 'المطبخ الرئيسي', 'description' => 'محطة التحضير الافتراضية.',
                        'target_minutes' => 15, 'is_default' => true, 'is_active' => true,
                        'sort_order' => 10, 'created_by' => $adminId,
                        'created_at' => now(), 'updated_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * A complete branch menu and purchasing catalog. All optional values are
     * filtered against the real schema by upsertExisting(), keeping this one
     * seeder compatible with both the MySQL and SQLite project variants.
     */
    private function seedCatalogSupplyAndMenuDemo(): void
    {
        $adminId = User::query()->where('username', 'admin')->value('id');
        $currencyId = Schema::hasTable('currencies')
            ? DB::table('currencies')->where('is_base', true)->value('id') ?? DB::table('currencies')->value('id')
            : null;

        $suppliers = [
            ['SUP-NUTS','شركة ذهب للمكسرات','مكسرات وفستق وحشوات','0599100101','nuts@dahab.test','غزة'],
            ['SUP-DAIRY','مزارع الألبان الطازجة','حليب وكريمة وأجبان','0599100102','dairy@dahab.test','خانيونس'],
            ['SUP-FLOUR','مطحنة السنابل','طحين وسكر وسميد','0599100103','flour@dahab.test','دير البلح'],
            ['SUP-CHOC','بيت الشوكولاتة','شوكولاتة وكاكاو','0599100104','chocolate@dahab.test','غزة'],
            ['SUP-FRUIT','ثمار الموسم','فواكه طازجة ومجمدة','0599100105','fruit@dahab.test','النصيرات'],
            ['SUP-PACK','التغليف الذهبي','علب وأكواب ومواد تغليف','0599100106','packaging@dahab.test','غزة'],
            ['SUP-DRINK','روّاد المشروبات','قهوة وعصائر ونكهات','0599100107','beverages@dahab.test','خانيونس'],
            ['SUP-EQUIP','تقنيات المطابخ','أدوات ومعدات وصيانة','0599100108','equipment@dahab.test','غزة'],
        ];

        foreach ($suppliers as $index => [$code, $name, $speciality, $phone, $email, $city]) {
            $this->upsertExisting('suppliers', ['supplier_code' => $code], [
                'name' => $name, 'company_name' => $name, 'contact_person' => 'مسؤول '.$name,
                'phone' => $phone, 'mobile' => $phone, 'email' => $email,
                'address' => $city.' - المنطقة التجارية', 'city' => $city, 'country' => 'فلسطين',
                'tax_number' => sprintf('TAX-DH-%04d', $index + 1), 'currency_id' => $currencyId,
                'payment_terms' => $index % 2 === 0 ? '30 days' : '15 days',
                'credit_limit' => 20000 + ($index * 5000), 'opening_balance' => 0,
                'status' => 'active', 'notes' => $speciality, 'created_by' => $adminId,
            ]);
            $supplierId = DB::table('suppliers')->where('supplier_code', $code)->value('id');
            if ($supplierId) {
                $this->upsertExisting('supplier_contacts', [
                    'supplier_id' => $supplierId, 'name' => 'مسؤول '.$name,
                ], [
                    'position' => 'مدير الحساب', 'phone' => $phone, 'whatsapp' => $phone,
                    'email' => $email, 'is_primary' => true, 'notes' => 'جهة الاتصال الرئيسية للمورد.',
                ]);
            }
        }

        if (Schema::hasTable('supplier_products')) {
            $supplierMap = DB::table('suppliers')->whereIn('supplier_code', array_column($suppliers, 0))
                ->get()->keyBy('supplier_code');
            $supplierByCategory = [
                'cakes' => ['SUP-DAIRY','SUP-FLOUR'], 'gateaux' => ['SUP-DAIRY','SUP-CHOC'],
                'oriental-sweets' => ['SUP-NUTS','SUP-FLOUR'], 'western-sweets' => ['SUP-FLOUR','SUP-DAIRY'],
                'chocolate' => ['SUP-CHOC','SUP-PACK'], 'drinks' => ['SUP-DRINK','SUP-FRUIT'],
                'milkshakes' => ['SUP-DAIRY','SUP-FRUIT'], 'gifts' => ['SUP-PACK','SUP-CHOC'],
            ];

            $products = Product::query()->with('category')->where('is_active', true)->orderBy('sku')->get();
            foreach ($products as $productIndex => $product) {
                if (Schema::hasColumn('supplier_products', 'is_preferred')) {
                    DB::table('supplier_products')->where('product_id', $product->id)->update(['is_preferred' => false]);
                }
                $categorySlug = $product->category?->slug;
                $supplierCodes = $supplierByCategory[$categorySlug] ?? ['SUP-FLOUR','SUP-PACK'];
                foreach ($supplierCodes as $supplierIndex => $supplierCode) {
                    $supplier = $supplierMap->get($supplierCode);
                    if (! $supplier) continue;
                    $sellingPrice = (float) ($product->base_selling_price ?? 10);
                    $purchasePrice = round(max(1, $sellingPrice * (0.40 + ($supplierIndex * 0.04))), 2);
                    $this->upsertExisting('supplier_products', [
                        'supplier_id' => $supplier->id, 'product_id' => $product->id,
                    ], [
                        'supplier_sku' => $supplierCode.'-'.$product->sku,
                        'supplier_product_name' => $product->name,
                        'purchase_price' => $purchasePrice, 'currency_id' => $currencyId,
                        'minimum_order_quantity' => in_array($product->unit, ['kg','tray'], true) ? 5 : 12,
                        'lead_time_days' => 1 + (($productIndex + $supplierIndex) % 5),
                        'is_preferred' => $supplierIndex === 0, 'is_active' => true,
                    ]);
                    $supplierProductId = DB::table('supplier_products')
                        ->where('supplier_id', $supplier->id)->where('product_id', $product->id)->value('id');
                    if ($supplierProductId) {
                        $effectiveAt = now()->subMonthsNoOverflow(2 - $supplierIndex)->startOfMonth();
                        $this->upsertExisting('supplier_product_price_histories', [
                            'supplier_product_id' => $supplierProductId, 'effective_at' => $effectiveAt,
                        ], [
                            'purchase_price' => $purchasePrice, 'currency_id' => $currencyId,
                            'exchange_rate' => 1, 'base_purchase_price' => $purchasePrice,
                            'reference_type' => 'demo_catalog', 'reference_id' => $product->id,
                            'created_by' => $adminId,
                        ]);
                    }
                }
            }
        }

        if (! Schema::hasTable('restaurant_menu_items')) return;

        $menuProducts = Product::query()->with('category')
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('slug', '!=', 'gifts'))
            ->orderBy('category_id')->orderBy('sku')->get();
        $branches = Location::query()->where('type', 'branch')->where('is_active', true)->orderBy('id')->get();

        foreach ($branches as $branch) {
            $stationId = Schema::hasTable('kitchen_stations')
                ? DB::table('kitchen_stations')->where('location_id', $branch->id)->where('is_default', true)->value('id')
                : null;
            foreach ($menuProducts as $sortIndex => $product) {
                $imagePath = $product->image
                    ?: 'images/demo-products/'.$product->sku.'.svg';
                $this->upsertExisting('restaurant_menu_items', [
                    'location_id' => $branch->id, 'product_id' => $product->id,
                ], [
                    'display_name' => $product->name,
                    'display_name_ar' => $product->name_ar,
                    'description' => 'يُحضّر طازجًا من حلويات دهب.',
                    'image' => $imagePath, 'image_path' => $imagePath,
                    'is_active' => true, 'show_in_pos' => true,
                    'show_in_qr' => true, 'show_in_delivery' => true,
                    'is_featured' => $sortIndex < 8,
                    'sort_order' => ($sortIndex + 1) * 10,
                    'created_by' => $adminId,
                ]);

                if ($stationId) {
                    $this->upsertExisting('kitchen_product_routes', [
                        'location_id' => $branch->id, 'product_id' => $product->id,
                    ], ['kitchen_station_id' => $stationId]);
                }
            }
        }
    }

    private function seedAttendanceAndPayrollDemo(): void
    {
        $tables = ['work_shifts','employee_shift_assignments','attendance_records',
            'employee_compensation_profiles','employee_payroll_adjustments','payroll_periods',
            'payroll_items','payroll_item_components','employee_ledger_entries'];
        if (collect($tables)->contains(fn ($table) => ! Schema::hasTable($table))) return;

        $actor = User::query()->where('username', 'admin')->firstOrFail();
        $currencyId = Schema::hasTable('currencies')
            ? DB::table('currencies')->where('is_base', true)->value('id') ?? DB::table('currencies')->value('id')
            : null;
        $employees = Employee::query()->where('employment_status', 'active')->orderBy('id')->get();

        foreach ($employees as $index => $employee) {
            EmployeeCompensationProfile::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'effective_from' => now()->subYear()->startOfMonth()->toDateString()],
                [
                    'salary_basis' => 'monthly', 'base_salary' => 2400 + (($index % 6) * 250),
                    'currency_id' => $currencyId, 'effective_to' => null, 'is_active' => true,
                    'notes' => 'ملف راتب تجريبي.', 'created_by' => $actor->id,
                ]
            );
            foreach ([['allowance','بدل مواصلات',180],['bonus','حافز أداء',120],['deduction','خصم تأخير',35]] as [$kind,$name,$amount]) {
                EmployeePayrollAdjustment::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'kind' => $kind, 'name' => $name],
                    [
                        'amount' => $amount, 'is_recurring' => $kind === 'allowance',
                        'effective_from' => now()->subMonths(2)->startOfMonth()->toDateString(),
                        'status' => 'active', 'notes' => 'بند راتب تجريبي.', 'created_by' => $actor->id,
                    ]
                );
            }
        }

        foreach (Location::query()->where('is_active', true)->get() as $location) {
            $shift = WorkShift::query()->updateOrCreate(
                ['code' => 'DEMO-SHIFT-'.$location->id],
                [
                    'name' => 'وردية صباحية - '.$location->name, 'location_id' => $location->id,
                    'start_time' => '08:00:00', 'end_time' => '16:00:00', 'break_minutes' => 30,
                    'grace_minutes' => 10, 'overtime_after_minutes' => 30,
                    'work_days' => [0,1,2,3,4,6], 'is_active' => true, 'created_by' => $actor->id,
                ]
            );
            $locationEmployees = $employees->filter(fn ($employee) => EmployeeLocation::query()
                ->where('employee_id', $employee->id)->where('location_id', $location->id)
                ->where('is_primary', true)->exists());
            foreach ($locationEmployees as $employee) {
                EmployeeShiftAssignment::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'work_shift_id' => $shift->id, 'effective_from' => now()->subYear()->toDateString()],
                    ['is_primary' => true, 'created_by' => $actor->id]
                );
                foreach (range(1, 20) as $daysAgo) {
                    $date = now()->subDays($daysAgo);
                    if ($date->isFriday()) continue;
                    $late = $daysAgo % 7 === 0 ? 18 : 0;
                    AttendanceRecord::query()->updateOrCreate(
                        ['employee_id' => $employee->id, 'work_date' => $date->toDateString()],
                        [
                            'work_shift_id' => $shift->id,
                            'scheduled_start_at' => $date->copy()->setTime(8, 0),
                            'scheduled_end_at' => $date->copy()->setTime(16, 0),
                            'check_in_at' => $date->copy()->setTime(8, $late),
                            'check_out_at' => $date->copy()->setTime(16, 0),
                            'status' => $late ? 'late' : 'present', 'worked_minutes' => 450 - $late,
                            'late_minutes' => $late, 'early_leave_minutes' => 0, 'overtime_minutes' => 0,
                            'source' => 'manual', 'approved_by' => $actor->id,
                            'approved_at' => now(), 'created_by' => $actor->id,
                        ]
                    );
                }
            }
        }

        $payroll = app(PayrollService::class);
        foreach ([
            [now()->subMonthNoOverflow()->startOfMonth(), 'previous'],
            [now()->startOfMonth(), 'current'],
        ] as [$start, $type]) {
            $period = PayrollPeriod::query()->firstOrCreate(
                ['code' => 'DEMO-PAY-'.$start->format('Ym')],
                [
                    'name' => 'رواتب '.$start->translatedFormat('F Y'),
                    'start_date' => $start->toDateString(), 'end_date' => $start->copy()->endOfMonth()->toDateString(),
                    'status' => 'draft', 'currency_id' => $currencyId,
                    'notes' => $type === 'previous' ? 'دورة معتمدة تجريبية.' : 'دورة محسوبة تحت المراجعة.',
                    'created_by' => $actor->id, 'approved_by' => null, 'approved_at' => null,
                ]
            );

            // Do not reset an approved period to draft or recalculate it on
            // every db:seed run. Recalculation creates duplicate components,
            // ledger entries and payments.
            if ($period->wasRecentlyCreated || $period->items()->doesntExist()) {
                $period = $payroll->calculatePeriod($period, $actor);
            }

            if ($type === 'previous') {
                $periodStatus = $period->status instanceof \BackedEnum
                    ? $period->status->value
                    : (string) $period->status;

                if ($periodStatus === 'draft') {
                    $period = $payroll->calculatePeriod($period, $actor);
                    $periodStatus = $period->status instanceof \BackedEnum
                        ? $period->status->value
                        : (string) $period->status;
                }

                if ($periodStatus === 'calculated') {
                    $period = $payroll->approvePeriod($period, $actor);
                }

                $paymentMethod = PaymentMethod::query()
                    ->where('is_active', true)
                    ->where('requires_verification', false)
                    ->first();
                if ($paymentMethod && Schema::hasTable('payroll_payments')) {
                    $settlement = app(PayrollSettlementService::class);
                    foreach ($period->items as $item) {
                        $amount = min((float) $item->payable_amount, (float) $item->net_salary);
                        if ($amount <= 0) continue;
                        $reference = 'DEMO-SALARY-'.$item->id;

                        $paymentAlreadyExists = Schema::hasColumn('payroll_payments', 'reference')
                            ? DB::table('payroll_payments')->where('reference', $reference)->exists()
                            : (
                                Schema::hasColumn('payroll_payments', 'payroll_item_id')
                                    && DB::table('payroll_payments')
                                        ->where('payroll_item_id', $item->id)
                                        ->where('status', '!=', 'voided')
                                        ->exists()
                            );

                        if ($paymentAlreadyExists) continue;

                        $settlement->createPayment($item, [
                            'amount' => $amount,
                            'payment_method_id' => $paymentMethod->id,
                            'paid_at' => now()->setTime(12, 0),
                            'reference' => $reference,
                            'notes' => 'دفعة راتب تجريبية مرحّلة إلى كشف الموظف.',
                        ], null, $actor);
                    }
                }
            }
        }
    }

    /**
     * Fill cross-module demo scenarios that are needed for a real end-to-end trial.
     * Every record has a stable business key, so this method is safe to run repeatedly.
     */
    private function seedOperationalCoverageDemo(): void
    {
        $now = now();
        $adminId = User::query()->where('username', 'admin')->value('id');
        $locationId = Location::query()->where('code', 'B01')->value('id') ?? Location::query()->value('id');
        $factoryId = Location::query()->where('code', 'FAC')->value('id') ?? $locationId;
        $currencyId = Schema::hasTable('currencies')
            ? DB::table('currencies')->where('is_base', true)->value('id') ?? DB::table('currencies')->value('id')
            : null;
        $paymentMethodId = PaymentMethod::query()->where('is_active', true)->value('id');
        $products = Product::query()->orderBy('id')->take(10)->get();

        if (! $adminId || ! $locationId || $products->count() < 3) return;

        // Sales channels.
        foreach ([
            ['name' => 'المبيعات المباشرة', 'slug' => 'direct-demo', 'type' => 'direct', 'commission_value' => 0],
            ['name' => 'طلبات التوصيل', 'slug' => 'delivery-demo', 'type' => 'aggregator', 'commission_value' => 12],
            ['name' => 'متجر دهب الإلكتروني', 'slug' => 'web-demo', 'type' => 'online', 'commission_value' => 5],
        ] as $index => $channel) {
            $this->upsertExisting('sales_channels', ['slug' => $channel['slug']], $channel + [
                'discount_type' => 'percentage', 'discount_value' => 0,
                'discount_funded_by_channel' => 0, 'commission_type' => 'percentage',
                'commission_base' => 'net_sales', 'delivery_fee_recipient' => 'restaurant',
                'settlement_cycle' => 'monthly', 'settlement_days' => 7,
                'is_active' => true, 'sort_order' => ($index + 1) * 10,
                'description' => 'قناة بيع تجريبية للفحص الشامل.',
            ]);
        }

        // Traceable opening stock movements.
        foreach ($products->take(5) as $index => $product) {
            $this->upsertExisting('stock_movements', [
                'reference_type' => 'demo_opening', 'reference_id' => $product->id,
            ], [
                'location_id' => $locationId, 'product_id' => $product->id,
                'movement_type' => 'in', 'reason' => 'opening_stock', 'quantity' => 25 + $index,
                'balance_before' => 0, 'balance_after' => 25 + $index,
                'created_by' => $adminId, 'note' => 'رصيد افتتاحي تجريبي قابل للتتبع.',
            ]);
        }

        // Suppliers, catalog links, purchase orders, receipt, batches and supplier invoice.
        if (Schema::hasTable('suppliers') && $currencyId) {
            foreach (range(1, 3) as $index) {
                $this->upsertExisting('suppliers', ['supplier_code' => sprintf('DEMO-SUP-%02d', $index)], [
                    'name' => ['مورد المواد الخام', 'مورد التغليف', 'مورد الألبان'][$index - 1],
                    'company_name' => 'شركة توريد تجريبية '.$index, 'contact_person' => 'مسؤول المورد '.$index,
                    'phone' => '05990000'.$index, 'email' => 'supplier'.$index.'@dahab.test',
                    'city' => 'غزة', 'country' => 'فلسطين', 'currency_id' => $currencyId,
                    'payment_terms' => '30 days', 'credit_limit' => 25000, 'opening_balance' => 0,
                    'status' => 'active', 'notes' => 'مورد تجريبي متكامل.', 'created_by' => $adminId,
                ]);
            }
            $suppliers = DB::table('suppliers')->whereIn('supplier_code', ['DEMO-SUP-01','DEMO-SUP-02','DEMO-SUP-03'])->get();
            foreach ($products->take(6) as $index => $product) {
                $supplier = $suppliers[$index % max(1, $suppliers->count())];
                $this->upsertExisting('supplier_products', [
                    'supplier_id' => $supplier->id, 'product_id' => $product->id,
                ], [
                    'supplier_sku' => 'SUP-SKU-'.$product->id, 'supplier_product_name' => $product->name,
                    'purchase_price' => 4 + $index, 'currency_id' => $currencyId,
                    'minimum_order_quantity' => 1, 'lead_time_days' => 3 + $index,
                    // The complete catalog above already assigns exactly one
                    // preferred supplier per product. These workflow-only
                    // suppliers remain valid alternatives.
                    'is_preferred' => false, 'is_active' => true,
                ]);
            }

            foreach (range(1, 2) as $poIndex) {
                $supplier = $suppliers[$poIndex - 1];
                $poNumber = sprintf('DEMO-PO-%04d', $poIndex);
                $this->upsertExisting('purchase_orders', ['purchase_order_number' => $poNumber], [
                    'supplier_id' => $supplier->id, 'location_id' => $factoryId,
                    'currency_id' => $currencyId, 'exchange_rate' => 1,
                    'order_date' => $now->copy()->subDays(10 - $poIndex)->toDateString(),
                    'expected_delivery_date' => $now->copy()->addDays($poIndex)->toDateString(),
                    'status' => $poIndex === 1 ? 'received' : 'approved',
                    'subtotal' => 300, 'discount_amount' => 0, 'tax_amount' => 0,
                    'shipping_cost' => 0, 'grand_total' => 300, 'base_grand_total' => 300,
                    'notes' => 'أمر شراء تجريبي.', 'created_by' => $adminId,
                    'approved_by' => $adminId, 'approved_at' => $now->copy()->subDays(7),
                ]);
                $poId = DB::table('purchase_orders')->where('purchase_order_number', $poNumber)->value('id');
                foreach ($products->slice(($poIndex - 1) * 2, 2) as $itemIndex => $product) {
                    $quantity = 20 + $itemIndex * 5;
                    $unitPrice = 6 + $itemIndex;
                    $this->upsertExisting('purchase_order_items', [
                        'purchase_order_id' => $poId, 'product_id' => $product->id,
                    ], [
                        'description' => $product->name, 'ordered_quantity' => $quantity,
                        'received_quantity' => $poIndex === 1 ? $quantity : 0,
                        'unit_price' => $unitPrice, 'discount_amount' => 0, 'tax_amount' => 0,
                        'line_total' => $quantity * $unitPrice, 'base_unit_cost' => $unitPrice,
                        'base_line_total' => $quantity * $unitPrice,
                    ]);
                }
            }

            $firstPo = DB::table('purchase_orders')->where('purchase_order_number', 'DEMO-PO-0001')->first();
            if ($firstPo) {
                $this->upsertExisting('goods_receipts', ['receipt_number' => 'DEMO-GR-0001'], [
                    'purchase_order_id' => $firstPo->id, 'supplier_id' => $firstPo->supplier_id,
                    'location_id' => $factoryId, 'currency_id' => $currencyId, 'exchange_rate' => 1,
                    'status' => 'posted', 'received_by' => $adminId, 'received_at' => $now->copy()->subDays(3),
                    'posted_by' => $adminId, 'posted_at' => $now->copy()->subDays(3),
                    'notes' => 'استلام مشتريات تجريبي مكتمل.',
                ]);
                $receiptId = DB::table('goods_receipts')->where('receipt_number', 'DEMO-GR-0001')->value('id');
                foreach (DB::table('purchase_order_items')->where('purchase_order_id', $firstPo->id)->get() as $index => $poItem) {
                    $batch = sprintf('DEMO-BATCH-%02d', $index + 1);
                    $this->upsertExisting('goods_receipt_items', [
                        'goods_receipt_id' => $receiptId, 'purchase_order_item_id' => $poItem->id,
                    ], [
                        'product_id' => $poItem->product_id, 'ordered_quantity' => $poItem->ordered_quantity,
                        'received_quantity' => $poItem->ordered_quantity, 'accepted_quantity' => $poItem->ordered_quantity,
                        'rejected_quantity' => 0, 'unit_cost' => $poItem->unit_price,
                        'base_unit_cost' => $poItem->unit_price, 'line_total' => $poItem->line_total,
                        'base_line_total' => $poItem->line_total, 'batch_number' => $batch,
                        'manufacturing_date' => $now->copy()->subMonth()->toDateString(),
                        'expiry_date' => $now->copy()->addMonths(4 + $index)->toDateString(),
                    ]);
                    $receiptItemId = DB::table('goods_receipt_items')
                        ->where('goods_receipt_id', $receiptId)->where('purchase_order_item_id', $poItem->id)->value('id');
                    $this->upsertExisting('inventory_batches', ['batch_number' => $batch], [
                        'goods_receipt_item_id' => $receiptItemId, 'product_id' => $poItem->product_id,
                        'location_id' => $factoryId, 'manufacturing_date' => $now->copy()->subMonth()->toDateString(),
                        'expiry_date' => $now->copy()->addMonths(4 + $index)->toDateString(),
                        'received_quantity' => $poItem->ordered_quantity, 'available_quantity' => $poItem->ordered_quantity,
                        'unit_cost' => $poItem->unit_price, 'base_unit_cost' => $poItem->unit_price,
                        'currency_id' => $currencyId,
                    ]);
                }

                // Reconcile the denormalized received quantity with the real
                // total accepted by posted receipts. This also repairs demo
                // rows created by older seeder versions.
                foreach (DB::table('purchase_order_items')->where('purchase_order_id', $firstPo->id)->get() as $poItem) {
                    $postedAcceptedQuantity = (float) DB::table('goods_receipt_items as gri')
                        ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
                        ->where('gri.purchase_order_item_id', $poItem->id)
                        ->where('gr.status', 'posted')
                        ->sum('gri.accepted_quantity');

                    DB::table('purchase_order_items')
                        ->where('id', $poItem->id)
                        ->update([
                            'received_quantity' => $postedAcceptedQuantity,
                            'updated_at' => now(),
                        ]);
                }

                // Third batch deliberately expires soon for expiry-dashboard testing.
                $thirdProduct = $products->get(2);
                $this->upsertExisting('inventory_batches', ['batch_number' => 'DEMO-BATCH-EXP'], [
                    'product_id' => $thirdProduct->id, 'location_id' => $factoryId,
                    'manufacturing_date' => $now->copy()->subMonths(2)->toDateString(),
                    'expiry_date' => $now->copy()->addDays(5)->toDateString(),
                    'received_quantity' => 12, 'available_quantity' => 8,
                    'unit_cost' => 7, 'base_unit_cost' => 7, 'currency_id' => $currencyId,
                ]);
                $this->upsertExisting('supplier_invoices', ['invoice_number' => 'DEMO-SINV-0001'], [
                    'supplier_id' => $firstPo->supplier_id, 'purchase_order_id' => $firstPo->id,
                    'goods_receipt_id' => $receiptId, 'location_id' => $factoryId,
                    'currency_id' => $currencyId, 'exchange_rate' => 1,
                    'invoice_date' => $now->copy()->subDays(3)->toDateString(),
                    'due_date' => $now->copy()->addDays(27)->toDateString(),
                    'subtotal' => 300, 'discount_amount' => 0, 'tax_amount' => 0,
                    'grand_total' => 300, 'base_grand_total' => 300,
                    'paid_amount' => 100, 'credited_amount' => 0, 'remaining_amount' => 200,
                    'status' => 'partially_paid', 'notes' => 'فاتورة مورد تجريبية.', 'created_by' => $adminId,
                ]);
                $supplierInvoiceId = DB::table('supplier_invoices')->where('invoice_number', 'DEMO-SINV-0001')->value('id');
                foreach (DB::table('purchase_order_items')->where('purchase_order_id', $firstPo->id)->get() as $item) {
                    $this->upsertExisting('supplier_invoice_items', [
                        'supplier_invoice_id' => $supplierInvoiceId, 'product_id' => $item->product_id,
                    ], [
                        'description' => $item->description, 'quantity' => $item->ordered_quantity,
                        'unit_price' => $item->unit_price, 'discount_amount' => 0, 'tax_amount' => 0,
                        'line_total' => $item->line_total,
                    ]);
                }
            }
        }

        // Approved recipes and production orders with ingredient snapshots.
        foreach (range(1, 2) as $recipeIndex) {
            $output = $products->get($recipeIndex - 1);
            $code = sprintf('DEMO-RCP-%02d', $recipeIndex);
            $this->upsertExisting('recipes', ['code' => $code], [
                'product_id' => $output->id, 'name' => 'وصفة تجريبية - '.$output->name,
                'version' => 1, 'status' => 'approved', 'yield_quantity' => 10,
                'labor_cost_per_batch' => 25, 'overhead_percent' => 8,
                'is_active' => true, 'notes' => 'وصفة معتمدة لاختبار دورة الإنتاج.',
                'created_by' => $adminId, 'updated_by' => $adminId,
                'approved_by' => $adminId, 'approved_at' => $now->copy()->subDays(5),
                'activated_by' => $adminId, 'activated_at' => $now->copy()->subDays(5),
            ]);
            $recipeId = DB::table('recipes')->where('code', $code)->value('id');
            foreach ($products->slice(2 + (($recipeIndex - 1) * 3), 3) as $ingredientIndex => $ingredient) {
                $this->upsertExisting('recipe_items', [
                    'recipe_id' => $recipeId, 'ingredient_product_id' => $ingredient->id,
                ], [
                    'quantity' => 1.5 + $ingredientIndex, 'waste_percent' => 2 + $ingredientIndex,
                    'expected_waste_percent' => 2 + $ingredientIndex,
                    'estimated_unit_cost' => 5 + $ingredientIndex, 'is_optional' => false,
                    'sort_order' => ($ingredientIndex + 1) * 10,
                ]);
            }
            $productionNumber = sprintf('DEMO-PROD-%04d', $recipeIndex);
            $this->upsertExisting('production_orders', ['production_number' => $productionNumber], [
                'location_id' => $factoryId, 'recipe_id' => $recipeId, 'product_id' => $output->id,
                'recipe_version' => 1, 'status' => $recipeIndex === 1 ? 'completed' : 'in_progress',
                'recipe_yield_quantity' => 10, 'planned_output_quantity' => 20,
                'actual_output_quantity' => $recipeIndex === 1 ? 19 : null,
                'output_variance_quantity' => $recipeIndex === 1 ? -1 : null,
                'output_variance_percent' => $recipeIndex === 1 ? -5 : null,
                'estimated_material_cost' => 90, 'actual_material_cost' => $recipeIndex === 1 ? 94 : 0,
                'labor_cost' => 50, 'overhead_percent_snapshot' => 8, 'overhead_cost' => 11.52,
                'total_cost' => $recipeIndex === 1 ? 155.52 : 0, 'unit_cost' => $recipeIndex === 1 ? 8.185263 : 0,
                'cost_is_complete' => $recipeIndex === 1, 'planned_at' => $now->copy()->subDays(2),
                'released_at' => $now->copy()->subDays(2), 'started_at' => $now->copy()->subDay(),
                'completed_at' => $recipeIndex === 1 ? $now->copy()->subHours(8) : null,
                'created_by' => $adminId, 'released_by' => $adminId, 'started_by' => $adminId,
                'completed_by' => $recipeIndex === 1 ? $adminId : null,
                'notes' => 'أمر إنتاج تجريبي مترابط مع الوصفة.',
            ]);
            $productionId = DB::table('production_orders')->where('production_number', $productionNumber)->value('id');
            foreach (DB::table('recipe_items')->where('recipe_id', $recipeId)->get() as $recipeItem) {
                $planned = (float) $recipeItem->quantity * 2;
                $this->upsertExisting('production_order_items', [
                    'production_order_id' => $productionId, 'product_id' => $recipeItem->ingredient_product_id,
                ], [
                    'recipe_item_id' => $recipeItem->id, 'recipe_quantity' => $recipeItem->quantity,
                    'conversion_to_stock_unit' => 1,
                    'waste_percent_snapshot' => $recipeItem->waste_percent ?? 0,
                    // Both canonical and legacy schemas are supported; upsertExisting keeps only real columns.
                    'planned_quantity' => $planned, 'planned_stock_quantity' => $planned,
                    'reserved_quantity' => $recipeIndex === 1 ? $planned : 0,
                    'issued_quantity' => $recipeIndex === 1 ? $planned : 0,
                    'issued_stock_quantity' => $recipeIndex === 1 ? $planned : 0,
                    'actual_quantity' => $recipeIndex === 1 ? $planned * 0.98 : null,
                    'actual_stock_quantity' => $recipeIndex === 1 ? $planned * 0.98 : null,
                    'returned_quantity' => 0, 'waste_quantity' => $recipeIndex === 1 ? $planned * 0.02 : 0,
                    'unit_cost_snapshot' => 5, 'planned_unit_cost' => 5,
                    'planned_cost' => $planned * 5, 'planned_total_cost' => $planned * 5,
                    'issued_unit_cost' => $recipeIndex === 1 ? 5 : null,
                    'actual_unit_cost' => $recipeIndex === 1 ? 5 : 0,
                    'actual_cost' => $recipeIndex === 1 ? $planned * 5 : 0,
                    'actual_total_cost' => $recipeIndex === 1 ? $planned * 5 : null,
                ]);
            }
        }

        // KDS tickets from real seeded order items.
        if (Schema::hasTable('kitchen_tickets') && Schema::hasTable('kitchen_ticket_items')) {
            $orders = Order::query()->where('status', 'completed')->with('items')->take(3)->get();
            foreach ($orders as $index => $order) {
                $stationId = DB::table('kitchen_stations')->where('location_id', $order->location_id)->value('id');
                if (! $stationId || $order->items->isEmpty()) continue;
                $ticketNumber = sprintf('DEMO-KDS-%04d', $index + 1);
                $this->upsertExisting('kitchen_tickets', ['ticket_number' => $ticketNumber], [
                    'order_id' => $order->id, 'location_id' => $order->location_id,
                    'kitchen_station_id' => $stationId,
                    'status' => ['queued','preparing','ready'][$index], 'priority' => $index,
                    'notes' => 'تذكرة مطبخ تجريبية.', 'queued_at' => $now->copy()->subMinutes(20 - $index * 5),
                    'started_at' => $index > 0 ? $now->copy()->subMinutes(10) : null,
                    'ready_at' => $index === 2 ? $now->copy()->subMinutes(2) : null,
                    'dispatched_by' => $adminId, 'started_by' => $index > 0 ? $adminId : null,
                    'ready_by' => $index === 2 ? $adminId : null,
                ]);
                $ticketId = DB::table('kitchen_tickets')->where('ticket_number', $ticketNumber)->value('id');
                foreach ($order->items as $orderItem) {
                    $this->upsertExisting('kitchen_ticket_items', ['order_item_id' => $orderItem->id], [
                        'kitchen_ticket_id' => $ticketId, 'product_id' => $orderItem->product_id,
                        'product_name' => $orderItem->product_name, 'quantity' => $orderItem->quantity,
                        'status' => ['queued','preparing','ready'][$index], 'routing_source' => 'default_station',
                        'kitchen_notes' => 'تحضير حسب المواصفات التجريبية.',
                    ]);
                }
            }
        }

        // Restaurant sessions, leave requests and employee advances.
        if (Schema::hasTable('restaurant_table_sessions')) {
            foreach (RestaurantTable::query()->take(2)->get() as $index => $table) {
                $this->upsertExisting('restaurant_table_sessions', [
                    'restaurant_table_id' => $table->id, 'opened_at' => $now->copy()->subDays($index + 1)->startOfHour(),
                ], [
                    'location_id' => $table->location_id, 'opened_by' => $adminId, 'closed_by' => $adminId,
                    'status' => 'closed', 'guest_count' => 2 + $index,
                    'closed_at' => $now->copy()->subDays($index + 1)->addHours(2)->startOfHour(),
                    'notes' => 'جلسة طاولة تجريبية مغلقة.',
                ]);
            }
        }
        foreach ([['ANNUAL','إجازة سنوية',true,21],['SICK','إجازة مرضية',true,14],['UNPAID','إجازة بدون راتب',false,10]] as [$code,$name,$paid,$days]) {
            $this->upsertExisting('leave_types', ['code' => $code], [
                'name' => $name, 'is_paid' => $paid, 'annual_days' => $days, 'is_active' => true,
            ]);
        }
        $employees = Employee::query()->where('employment_status', 'active')->take(2)->get();
        foreach ($employees as $index => $employee) {
            $leaveTypeId = DB::table('leave_types')->where('code', $index === 0 ? 'ANNUAL' : 'SICK')->value('id');
            $this->upsertExisting('employee_leave_requests', [
                'employee_id' => $employee->id, 'start_date' => $now->copy()->addDays(10 + $index * 5)->toDateString(),
            ], [
                'leave_type_id' => $leaveTypeId,
                'end_date' => $now->copy()->addDays(11 + $index * 5)->toDateString(),
                'total_days' => 2, 'status' => $index === 0 ? 'approved' : 'pending',
                'reason' => $index === 0 ? 'راحة سنوية تجريبية.' : 'طلب مرضي تجريبي.',
                'decision_note' => $index === 0 ? 'تمت الموافقة للاختبار.' : null,
                'approved_by' => $index === 0 ? $adminId : null,
                'approved_at' => $index === 0 ? $now : null, 'created_by' => $adminId,
            ]);
            $this->upsertExisting('employee_advances', [
                'employee_id' => $employee->id, 'reference' => 'DEMO-ADV-'.($index + 1),
            ], [
                'amount' => 300 + $index * 100, 'recovered_amount' => $index === 0 ? 100 : 0,
                'outstanding_amount' => 200 + $index * 200, 'issued_at' => $now->copy()->subDays(20)->toDateString(),
                'status' => 'open', 'payment_method_id' => $paymentMethodId,
                'notes' => 'سلفة موظف تجريبية.', 'created_by' => $adminId,
            ]);
        }
    }

    /**
     * Completes the demo database with records for the optional workflows that
     * used to live in separate seeders.  Keep this method idempotent: every
     * record has a stable DEMO key and may safely be reseeded.
     */
    private function seedMissingWorkflowDemo(): void
    {
        $now = now();
        $adminId = User::query()->where('username', 'admin')->value('id') ?? User::query()->value('id');
        $users = User::query()->orderBy('id')->take(4)->get();
        $customers = Customer::query()->orderBy('id')->take(4)->get();
        $products = Product::query()->orderBy('id')->take(6)->get();
        $branchId = Location::query()->where('type', 'branch')->value('id') ?? Location::query()->value('id');
        $factoryId = Location::query()->where('type', 'factory')->value('id') ?? $branchId;
        $currencyId = DB::table('currencies')->where('is_base', true)->value('id') ?? DB::table('currencies')->value('id');
        $paymentMethodId = PaymentMethod::query()->where('is_active', true)->value('id') ?? PaymentMethod::query()->value('id');

        if (! $adminId || ! $branchId) return;

        // Product catalogue: brands, sizes, colours, attributes and variants.
        foreach ([['DAHAB','دهب','دهب'], ['LOCAL','Local Selection','منتجات محلية']] as $i => [$code,$name,$nameAr]) {
            $this->upsertExisting('brands', ['code' => $code], [
                'name' => $name, 'name_ar' => $nameAr, 'description' => 'علامة تجريبية للكتالوج.',
                'is_active' => true, 'sort_order' => $i + 1,
            ]);
        }
        foreach ([['S','Small','صغير'],['M','Medium','وسط'],['L','Large','كبير']] as $i => [$code,$name,$nameAr]) {
            $this->upsertExisting('sizes', ['code' => $code], ['name' => $name, 'name_ar' => $nameAr, 'is_active' => true, 'sort_order' => $i + 1]);
        }
        foreach ([['CHOCO','Chocolate','شوكولاتة','#5B3327'],['GOLD','Gold','ذهبي','#D89B00'],['WHITE','White','أبيض','#FFFFFF']] as $i => [$code,$name,$nameAr,$hex]) {
            $this->upsertExisting('colors', ['code' => $code], ['name' => $name, 'name_ar' => $nameAr, 'hex_code' => $hex, 'is_active' => true, 'sort_order' => $i + 1]);
        }
        $this->upsertExisting('product_attributes', ['code' => 'FLAVOR'], ['name' => 'Flavor', 'name_ar' => 'النكهة', 'is_active' => true, 'sort_order' => 1]);
        $attributeId = DB::table('product_attributes')->where('code', 'FLAVOR')->value('id');
        if ($attributeId) {
            foreach ([['VANILLA','Vanilla','فانيلا'],['CHOCOLATE','Chocolate','شوكولاتة']] as $i => [$code,$name,$nameAr]) {
                $this->upsertExisting('product_attribute_values', ['product_attribute_id' => $attributeId, 'code' => $code], [
                    'name' => $name, 'name_ar' => $nameAr, 'is_active' => true, 'sort_order' => $i + 1,
                ]);
            }
        }
        $brandId = DB::table('brands')->where('code', 'DAHAB')->value('id');
        $sizeIds = DB::table('sizes')->orderBy('sort_order')->pluck('id')->all();
        $colorIds = DB::table('colors')->orderBy('sort_order')->pluck('id')->all();
        foreach ($products->take(3) as $i => $product) {
            DB::table('products')->where('id', $product->id)->update(array_filter([
                'brand_id' => Schema::hasColumn('products', 'brand_id') ? $brandId : null,
                'product_type' => Schema::hasColumn('products', 'product_type') ? 'variant' : null,
            ], fn ($value) => $value !== null));
            $sku = sprintf('DEMO-VAR-%03d', $i + 1);
            $this->upsertExisting('product_variants', ['sku' => $sku], [
                'product_id' => $product->id, 'size_id' => $sizeIds[$i % max(1, count($sizeIds))] ?? null,
                'color_id' => $colorIds[$i % max(1, count($colorIds))] ?? null,
                'name' => $product->name.' - خيار '.($i + 1), 'name_ar' => $product->name,
                'selling_price' => (float) ($product->selling_price ?? 25) + ($i * 5),
                'is_default' => $i === 0, 'is_active' => true, 'sort_order' => $i + 1,
                'configuration' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
            ]);
            $variantId = DB::table('product_variants')->where('sku', $sku)->value('id');
            $valueId = DB::table('product_attribute_values')->where('product_attribute_id', $attributeId)->orderBy('id')->skip($i % 2)->value('id');
            if ($variantId && $valueId) $this->upsertExisting('product_variant_attribute_values', [
                'product_variant_id' => $variantId, 'product_attribute_value_id' => $valueId,
            ], []);
        }

        // CRM, customer accounts, loyalty and delivery.
        foreach ($customers as $i => $customer) {
            $this->upsertExisting('customer_locations', ['customer_id' => $customer->id, 'location_id' => $branchId], []);
            $this->upsertExisting('customer_addresses', ['customer_id' => $customer->id, 'label' => 'DEMO-'.($i + 1)], [
                'location_id' => $branchId, 'address_line1' => 'عنوان تجريبي رقم '.($i + 1),
                'city' => 'غزة', 'area' => 'الوسط', 'latitude' => 31.50 + ($i / 1000),
                'longitude' => 34.46 + ($i / 1000), 'is_default' => $i === 0, 'is_active' => true,
            ]);
        }
        foreach ([['VIP','عملاء مميزون','#D89B00'],['FOLLOW','متابعة','#2563EB']] as [$code,$name,$color]) {
            $this->upsertExisting('crm_tags', ['name' => $name], ['code' => $code, 'color' => $color, 'is_active' => true]);
        }
        foreach ($customers->take(2) as $i => $customer) {
            $tagId = DB::table('crm_tags')->orderBy('id')->skip($i)->value('id');
            if ($tagId) $this->upsertExisting('crm_customer_tag', ['customer_id' => $customer->id, 'crm_tag_id' => $tagId], ['assigned_by' => $adminId]);
            $this->upsertExisting('customer_interactions', ['customer_id' => $customer->id, 'type' => 'demo_follow_up'], [
                'channel' => 'phone', 'subject' => 'متابعة تجريبية', 'notes' => 'سجل تواصل متكامل لتجربة CRM.',
                'interaction_at' => $now->copy()->subDays($i + 1), 'created_by' => $adminId,
            ]);
        }
        $this->upsertExisting('loyalty_programs', ['name' => 'برنامج ولاء دهب'], [
            'points_per_currency_unit' => 1, 'redemption_value_per_point' => 0.1,
            'minimum_redeem_points' => 10, 'is_active' => true,
        ]);
        foreach ($customers->take(3) as $i => $customer) {
            $this->upsertExisting('loyalty_accounts', ['customer_id' => $customer->id], ['points_balance' => 100 + ($i * 25), 'lifetime_earned' => 150 + ($i * 25), 'lifetime_redeemed' => 50]);
            $accountId = DB::table('loyalty_accounts')->where('customer_id', $customer->id)->value('id');
            $this->upsertExisting('loyalty_transactions', ['idempotency_key' => 'DEMO-LOYALTY-'.($i + 1)], [
                'loyalty_account_id' => $accountId, 'customer_id' => $customer->id, 'type' => 'earn',
                'points' => 100 + ($i * 25), 'balance_after' => 100 + ($i * 25),
                'description' => 'نقاط افتتاحية تجريبية.', 'created_by' => $adminId,
            ]);
        }
        $this->upsertExisting('delivery_zones', ['location_id' => $branchId, 'name' => 'منطقة التوصيل التجريبية'], [
            'fee' => 10, 'minimum_order_amount' => 25, 'estimated_minutes' => 35, 'is_active' => true,
        ]);
        $zoneId = DB::table('delivery_zones')->where('location_id', $branchId)->where('name', 'منطقة التوصيل التجريبية')->value('id');
        foreach (Order::query()->where('location_id', $branchId)->take(2)->get() as $i => $order) {
            $status = $i === 0 ? 'delivered' : 'assigned';
            $this->upsertExisting('delivery_tasks', ['order_id' => $order->id], [
                'location_id' => $branchId, 'delivery_zone_id' => $zoneId, 'assigned_driver_id' => $users->get($i + 1)?->id,
                'status' => $status, 'address_snapshot' => json_encode(['address' => 'عنوان توصيل تجريبي'], JSON_UNESCAPED_UNICODE),
                'fee_snapshot' => 10, 'assigned_at' => $now->copy()->subHour(),
                'delivered_at' => $status === 'delivered' ? $now : null, 'created_by' => $adminId,
            ]);
            $taskId = DB::table('delivery_tasks')->where('order_id', $order->id)->value('id');
            if ($taskId) $this->upsertExisting('delivery_task_histories', ['delivery_task_id' => $taskId, 'to_status' => $status], [
                'from_status' => 'pending', 'note' => 'انتقال حالة تجريبي.', 'changed_by' => $adminId,
            ]);
        }

        // Customer payment allocation against a real invoice.
        $invoice = Invoice::query()->where('customer_id', '!=', null)->first();
        if ($invoice && $paymentMethodId) {
            $this->upsertExisting('customer_payments', ['reference_number' => 'DEMO-CUST-PAY-001'], [
                'customer_id' => $invoice->customer_id, 'location_id' => $invoice->location_id,
                'payment_method_id' => $paymentMethodId, 'amount' => min(50, (float) $invoice->total_amount),
                'status' => 'confirmed', 'allocation_mode' => 'manual', 'notes' => 'دفعة حساب عميل تجريبية.',
                'received_by' => $adminId, 'verified_by' => $adminId, 'verified_at' => $now, 'paid_at' => $now,
            ]);
            $customerPaymentId = DB::table('customer_payments')->where('reference_number', 'DEMO-CUST-PAY-001')->value('id');
            if ($customerPaymentId) $this->upsertExisting('customer_payment_allocations', [
                'customer_payment_id' => $customerPaymentId, 'invoice_id' => $invoice->id,
            ], ['amount' => min(50, (float) $invoice->total_amount)]);
        }

        // Internal chat channels, membership, messages, delivery receipts and
        // read state. A fresh database does not contain channels yet, so the
        // child records must not depend on pre-existing demo data.
        foreach (Location::query()->where('type', 'branch')->orderBy('id')->take(3)->get() as $channelIndex => $location) {
            $channelName = 'فريق '.$location->name;
            $this->upsertExisting('chat_channels', ['location_id' => $location->id, 'name' => $channelName], [
                'code' => 'DEMO-BRANCH-'.($channelIndex + 1),
                'slug' => 'demo-branch-'.$location->id,
                'name_ar' => $channelName,
                'title' => $channelName,
                'type' => 'branch',
                'channel_type' => 'branch',
                'is_direct' => false,
                'direct_key' => null,
                'description' => 'قناة تشغيل داخلية تجريبية خاصة بالفرع.',
                'is_active' => true,
                'created_by' => $adminId,
                'owner_id' => $adminId,
            ]);
        }

        foreach (DB::table('chat_channels')->take(3)->get() as $channelIndex => $channel) {
            foreach ($users->take(3) as $userIndex => $user) {
                $this->upsertExisting('chat_channel_members', ['channel_id' => $channel->id, 'user_id' => $user->id], ['joined_at' => $now->copy()->subDays(5)]);
            }
            $sender = $users->get($channelIndex % max(1, $users->count()));
            if (! $sender) continue;
            $messageText = 'رسالة تشغيل تجريبية للقناة '.($channelIndex + 1);
            $this->upsertExisting('chat_messages', ['channel_id' => $channel->id, 'user_id' => $sender->id, 'message' => $messageText], ['message_type' => 'text']);
            $messageId = DB::table('chat_messages')->where('channel_id', $channel->id)->where('message', $messageText)->value('id');
            foreach ($users->take(3) as $user) {
                if ($user->id === $sender->id) continue;
                $this->upsertExisting('chat_message_receipts', ['message_id' => $messageId, 'user_id' => $user->id], ['delivered_at' => $now, 'read_at' => $now]);
                $this->upsertExisting('chat_reads', ['channel_id' => $channel->id, 'user_id' => $user->id], ['last_read_message_id' => $messageId, 'read_at' => $now]);
            }
        }

        // Procurement return/payment and exchange-rate trail.
        if ($currencyId) $this->upsertExisting('exchange_rates', ['currency_id' => $currencyId, 'effective_date' => $now->toDateString()], ['rate_to_base' => 1, 'created_by' => $adminId]);
        $supplierInvoice = DB::table('supplier_invoices')->where('invoice_number', 'DEMO-SINV-0001')->first();
        $receipt = DB::table('goods_receipts')->where('receipt_number', 'DEMO-GR-0001')->first();
        if ($supplierInvoice && $receipt) {
            $this->upsertExisting('supplier_payments', ['payment_number' => 'DEMO-SPAY-0001'], [
                'supplier_id' => $supplierInvoice->supplier_id, 'supplier_invoice_id' => $supplierInvoice->id,
                'location_id' => $supplierInvoice->location_id, 'currency_id' => $currencyId, 'exchange_rate' => 1,
                'amount' => 100, 'base_amount' => 100, 'applied_amount' => 100,
                'payment_date' => $now->toDateString(), 'payment_method_id' => $paymentMethodId,
                'reference_number' => 'DEMO-SPAY-REF', 'status' => 'posted', 'notes' => 'دفعة مورد تجريبية.', 'created_by' => $adminId,
            ]);
            $this->upsertExisting('purchase_returns', ['return_number' => 'DEMO-PRET-0001'], [
                'supplier_id' => $supplierInvoice->supplier_id, 'goods_receipt_id' => $receipt->id,
                'supplier_invoice_id' => $supplierInvoice->id, 'location_id' => $supplierInvoice->location_id,
                'currency_id' => $currencyId, 'exchange_rate' => 1, 'status' => 'posted',
                'grand_total' => 6, 'base_grand_total' => 6, 'returned_by' => $adminId,
                'returned_at' => $now, 'posted_by' => $adminId, 'posted_at' => $now,
                'notes' => 'مرتجع شراء تجريبي.',
            ]);
            $returnId = DB::table('purchase_returns')->where('return_number', 'DEMO-PRET-0001')->value('id');
            $receiptItem = DB::table('goods_receipt_items')->where('goods_receipt_id', $receipt->id)->first();
            if ($returnId && $receiptItem) $this->upsertExisting('purchase_return_items', [
                'purchase_return_id' => $returnId, 'goods_receipt_item_id' => $receiptItem->id,
            ], [
                'product_id' => $receiptItem->product_id, 'return_quantity' => 1,
                'unit_cost' => 6, 'base_unit_cost' => 6, 'line_total' => 6, 'base_line_total' => 6,
                'reason' => 'damaged', 'notes' => 'عنصر مرتجع للاختبار.',
            ]);
        }

        // Expenses and workflow-supporting records.
        foreach ([['OPER-DEMO','تشغيلية تجريبية','operating'],['MAINT-DEMO','صيانة تجريبية','maintenance']] as $i => [$code,$name,$type]) {
            $this->upsertExisting('expense_categories', ['code' => $code], ['name' => $name, 'classification' => $type, 'is_active' => true, 'sort_order' => $i + 1]);
            $categoryId = DB::table('expense_categories')->where('code', $code)->value('id');
            $this->upsertExisting('expenses', ['reference_number' => 'DEMO-EXP-'.($i + 1)], [
                'expense_category_id' => $categoryId, 'location_id' => $branchId, 'amount' => 75 + ($i * 25),
                'currency_id' => $currencyId, 'exchange_rate' => 1, 'base_amount' => 75 + ($i * 25),
                'expense_date' => $now->copy()->subDays($i + 1)->toDateString(), 'description' => $name,
                'status' => $i === 0 ? 'posted' : 'approved', 'created_by' => $adminId,
                'submitted_by' => $adminId, 'approved_by' => $adminId, 'approved_at' => $now,
                'posted_by' => $i === 0 ? $adminId : null, 'posted_at' => $i === 0 ? $now : null,
            ]);
        }

        // Kitchen routing and quality inspection make the final production path testable.
        foreach (DB::table('kitchen_stations')->take(3)->get() as $i => $station) {
            $product = $products->get($i);
            $stationLocationId = $station->location_id ?? $branchId;
            if ($product) $this->upsertExisting('kitchen_product_routes', [
                'location_id' => $stationLocationId,
                'product_id' => $product->id,
            ], [
                'kitchen_station_id' => $station->id,
            ]);
            $categoryId = $product?->category_id;
            if ($categoryId) $this->upsertExisting('kitchen_category_routes', [
                'location_id' => $stationLocationId,
                'category_id' => $categoryId,
            ], [
                'kitchen_station_id' => $station->id,
            ]);
        }
        foreach (DB::table('production_orders')->take(2)->get() as $i => $productionOrder) {
            $this->upsertExisting('production_quality_inspections', ['production_order_id' => $productionOrder->id], [
                'status' => $i === 0 ? 'approved' : 'rejected', 'notes' => 'فحص جودة إنتاج تجريبي.',
                'rejection_reason' => $i === 0 ? null : 'نتيجة تجريبية لعرض مسار الرفض.',
                'inspected_by' => $adminId, 'inspected_at' => $now,
            ]);
        }

        // Branch-to-factory request with real product items.
        $requestProduct = $products->first();
        if ($requestProduct) {
            $this->upsertExisting('showroom_sweets_requests', ['request_number' => 'DEMO-SSR-0001'], [
                'requesting_location_id' => $branchId, 'factory_location_id' => $factoryId,
                'status' => 'in_progress', 'needed_by' => $now->copy()->addDays(2)->toDateString(),
                'notes' => 'طلب فرع تجريبي متكامل.', 'created_by' => $adminId,
                'handled_by' => $adminId, 'submitted_at' => $now->copy()->subDay(),
            ]);
            $requestId = DB::table('showroom_sweets_requests')->where('request_number', 'DEMO-SSR-0001')->value('id');
            if ($requestId) $this->upsertExisting('showroom_sweets_request_items', [
                'showroom_sweets_request_id' => $requestId, 'product_id' => $requestProduct->id,
            ], ['product_name_snapshot' => $requestProduct->name, 'quantity' => 12, 'requested_unit' => 'قطعة', 'notes' => 'صنف تجريبي.']);

            $this->upsertExisting('showroom_cake_requests', ['request_number' => 'DEMO-SCR-0001'], [
                'requesting_location_id' => $branchId, 'factory_location_id' => $factoryId,
                'status' => 'pending', 'needed_by' => $now->copy()->addDays(4)->toDateString(),
                'notes' => 'طلب كيك من الفرع للمصنع.', 'created_by' => $adminId, 'submitted_at' => $now,
            ]);
            $cakeRequestId = DB::table('showroom_cake_requests')->where('request_number', 'DEMO-SCR-0001')->value('id');
            if ($cakeRequestId) $this->upsertExisting('showroom_cake_request_items', [
                'showroom_cake_request_id' => $cakeRequestId, 'cake_type' => 'كيكة مناسبة تجريبية',
            ], ['cake_size' => 'وسط', 'flavor' => 'شوكولاتة', 'shape' => 'دائري', 'quantity' => 2, 'notes' => 'كتابة تجريبية.']);
        }

        foreach (SpecialCakeOrder::query()->take(2)->get() as $i => $cakeOrder) {
            $toStatus = is_object($cakeOrder->status) && isset($cakeOrder->status->value)
                ? $cakeOrder->status->value
                : (string) $cakeOrder->status;
            $this->upsertExisting('cake_order_status_histories', [
                'special_cake_order_id' => $cakeOrder->id, 'to_status' => $toStatus,
            ], ['from_status' => 'draft', 'changed_by' => $adminId, 'note' => 'سجل انتقال حالة تجريبي.', 'created_at' => $now->copy()->subHours($i + 1)]);
            $this->upsertExisting('cake_order_comments', [
                'special_cake_order_id' => $cakeOrder->id, 'user_id' => $adminId, 'comment' => 'ملاحظة تشغيل تجريبية للطلب.',
            ], ['is_internal' => true]);
        }
    }

    private function upsertExisting(string $table, array $unique, array $values): void
    {
        if (! Schema::hasTable($table)) return;

        $columns = array_flip(Schema::getColumnListing($table));
        $unique = array_intersect_key($unique, $columns);
        $values = array_intersect_key($values, $columns);
        if (isset($columns['created_at']) && ! array_key_exists('created_at', $values)) $values['created_at'] = now();
        if (isset($columns['updated_at'])) $values['updated_at'] = now();
        if ($unique !== []) DB::table($table)->updateOrInsert($unique, $values);
    }

    private function validateUnifiedDemo(): void
    {
        $required = [
            'locations','employees','users','roles','permissions','business_profiles','modules',
            'products','inventories','customers','orders','restaurant_tables','restaurant_menu_items','kitchen_stations',
            'stock_movements','inventory_batches','sales_channels','suppliers','supplier_contacts',
            'supplier_products','supplier_product_price_histories',
            'purchase_orders','purchase_order_items','goods_receipts','supplier_invoices',
            'purchase_returns','purchase_return_items','supplier_payments','exchange_rates',
            'recipes','recipe_items','production_orders','production_order_items',
            'production_quality_inspections','brands','sizes','colors','product_attributes','product_variants',
            'kitchen_tickets','kitchen_ticket_items','leave_types','employee_leave_requests','employee_advances',
            'kitchen_product_routes','showroom_sweets_requests','showroom_sweets_request_items',
            'showroom_cake_requests','showroom_cake_request_items','cake_order_status_histories','cake_order_comments',
            'customer_addresses','customer_interactions','customer_payments','customer_payment_allocations',
            'crm_tags','crm_customer_tag','loyalty_accounts','loyalty_transactions','delivery_zones','delivery_tasks',
            'chat_channels','chat_channel_members','chat_messages','chat_message_receipts','chat_reads','expense_categories','expenses',
            'work_shifts','attendance_records','employee_compensation_profiles',
            'payroll_periods','payroll_items','payroll_item_components','payroll_payments','employee_ledger_entries',
        ];
        $missing = collect($required)->filter(
            fn ($table) => Schema::hasTable($table) && DB::table($table)->count() === 0
        )->values()->all();
        if ($missing) throw new RuntimeException('بيانات إلزامية مفقودة: '.implode(', ', $missing));

        $minimumCounts = [
            'categories' => 8,
            'products' => 69,
            'suppliers' => 8,
            'supplier_contacts' => 8,
            'supplier_products' => 138,
            'supplier_product_price_histories' => 138,
            'employees' => 30,
            'users' => 30,
            'restaurant_tables' => 40,
            'restaurant_menu_items' => 300,
            'employee_compensation_profiles' => 30,
            'attendance_records' => 500,
            'payroll_items' => 60,
            'payroll_payments' => 30,
        ];
        foreach ($minimumCounts as $table => $minimum) {
            if (! Schema::hasTable($table)) continue;
            $actual = DB::table($table)->count();
            if ($actual < $minimum) {
                throw new RuntimeException("بيانات {$table} غير مكتملة: {$actual}/{$minimum}");
            }
        }

        $imageCount = count(glob(public_path('images/demo-products/*.svg')) ?: []);
        if ($imageCount < 69) {
            throw new RuntimeException("صور المنتجات غير مكتملة: {$imageCount}/69");
        }

        $allPermissions = Permission::query()->where('guard_name', 'web')->count();
        $adminPermissions = Role::findByName('Admin', 'web')->permissions()->count();
        if ($allPermissions !== $adminPermissions) {
            throw new RuntimeException("صلاحيات Admin ناقصة: {$adminPermissions}/{$allPermissions}");
        }
        $emptyRoles = Role::query()->where('guard_name', 'web')->whereDoesntHave('permissions')->pluck('name')->all();
        if ($emptyRoles) throw new RuntimeException('أدوار بدون صلاحيات: '.implode(', ', $emptyRoles));

        $this->command?->table(['الفحص','النتيجة'], [
            ['ملفات النشاط', BusinessProfile::query()->count()],
            ['الوحدات المفعلة', Module::query()->where('is_active', true)->count()],
            ['الصلاحيات', $allPermissions],
            ['التصنيفات', Category::query()->count()],
            ['المنتجات', Product::query()->count()],
            ['صور المنتجات', $imageCount],
            ['عناصر منيو الفروع', DB::table('restaurant_menu_items')->count()],
            ['الموردون', DB::table('suppliers')->count()],
            ['ربط الموردين بالأصناف', DB::table('supplier_products')->count()],
            ['الموظفون', Employee::query()->count()],
            ['سجلات الدوام', DB::table('attendance_records')->count()],
            ['دورات الرواتب', DB::table('payroll_periods')->count()],
            ['بنود الرواتب', DB::table('payroll_items')->count()],
            ['دفعات الرواتب', DB::table('payroll_payments')->count()],
            ['قيود الموظفين', DB::table('employee_ledger_entries')->count()],
        ]);
    }
}
