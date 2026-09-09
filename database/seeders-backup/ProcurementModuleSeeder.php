<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Location;
use App\Models\LocationPaymentMethod;
use App\Models\LocationProduct;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\SupplierPayment;
use App\Models\SupplierProduct;
use App\Models\SupplierProductPriceHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Complete, deterministic demo data for the procurement module.
 *
 * This seeder intentionally uses stable document numbers/codes and updateOrCreate
 * so it can be executed repeatedly without duplicating procurement documents.
 *
 * Prerequisites from the main DatabaseSeeder:
 * - at least one User (preferably admin/factory_mgr/accountant)
 * - FAC factory location and normal branches (B01...B05)
 * - payment methods (BANK/CASH are preferred)
 */
class ProcurementModuleSeeder extends Seeder
{
    private const YEAR = 2026;
    private const DEMO_AS_OF = '2026-08-15';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            $currencies = $this->seedCurrencies();
            $this->seedExchangeRates($currencies);

            $permissions = $this->seedPermissions();
            $this->grantDefaultRoles($permissions);

            $context = $this->resolveContext();
            $this->ensureProcurementPaymentMethods($context);

            $products = $this->seedProcurementProducts($context);
            $suppliers = $this->seedSuppliers($currencies, $context);
            $supplierProducts = $this->seedSupplierProducts($suppliers, $products, $currencies, $context);

            $purchaseOrders = $this->seedPurchaseOrders(
                $suppliers,
                $products,
                $currencies,
                $context,
            );

            $receipts = $this->seedGoodsReceipts($purchaseOrders, $context);
            $invoices = $this->seedSupplierInvoices(
                $purchaseOrders,
                $receipts,
                $currencies,
                $context,
            );

            $this->seedSupplierPayments($invoices, $currencies, $context);
            $returns = $this->seedPurchaseReturns($receipts, $invoices, $context);

            $this->synchroniseInvoiceBalances($invoices);
            $this->synchroniseProcurementInventory($products, $receipts, $returns, $context);
            $this->seedPurchasePriceHistory($supplierProducts, $purchaseOrders, $context);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('ProcurementModuleSeeder: procurement demo data seeded successfully.');
    }

    /** @return array<string, Currency> */
    private function seedCurrencies(): array
    {
        $rows = [
            'ILS' => [
                'name' => 'Israeli New Shekel',
                'name_ar' => 'شيكل إسرائيلي جديد',
                'symbol' => '₪',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            'USD' => [
                'name' => 'US Dollar',
                'name_ar' => 'دولار أمريكي',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            'EUR' => [
                'name' => 'Euro',
                'name_ar' => 'يورو',
                'symbol' => '€',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            'EGP' => [
                'name' => 'Egyptian Pound',
                'name_ar' => 'جنيه مصري',
                'symbol' => 'E£',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            'TRY' => [
                'name' => 'Turkish Lira',
                'name_ar' => 'ليرة تركية',
                'symbol' => '₺',
                'decimal_places' => 2,
                'is_active' => true,
            ],
        ];

        foreach ($rows as $code => $data) {
            Currency::query()->updateOrCreate(
                ['code' => $code],
                $data,
            );
        }

        // Respect a base currency already configured by the administrator.
        $base = Currency::query()->where('is_base', true)->first();
        if (! $base) {
            $base = Currency::query()->where('code', 'ILS')->firstOrFail();
            $base->update(['is_base' => true]);
        }

        // Guarantee only the selected existing base remains marked as base.
        Currency::query()->where('id', '!=', $base->id)->update(['is_base' => false]);

        return Currency::query()
            ->whereIn('code', array_keys($rows))
            ->get()
            ->keyBy('code')
            ->all();
    }

    /**
     * Demo rates only; they are deterministic test data, not live market quotes.
     * rate_to_base means: 1 unit of the foreign currency = X units of base currency.
     *
     * @param array<string, Currency> $currencies
     */
    private function seedExchangeRates(array $currencies): void
    {
        $base = Currency::query()->where('is_base', true)->firstOrFail();
        $actor = User::query()->where('username', 'admin')->first() ?? User::query()->first();

        $dates = [
            '2026-01-01' => ['ILS' => 1.00000000, 'USD' => 3.60000000, 'EUR' => 3.90000000, 'EGP' => 0.07200000, 'TRY' => 0.10500000],
            '2026-07-01' => ['ILS' => 1.00000000, 'USD' => 3.65000000, 'EUR' => 3.95000000, 'EGP' => 0.07500000, 'TRY' => 0.09200000],
            '2026-08-01' => ['ILS' => 1.00000000, 'USD' => 3.67000000, 'EUR' => 3.98000000, 'EGP' => 0.07600000, 'TRY' => 0.09000000],
        ];

        foreach ($dates as $date => $rates) {
            // The table above is expressed as ILS per currency. Convert it to
            // the administrator's configured base currency when base != ILS.
            // Never silently calculate wrong base values for an unsupported base.
            if (! array_key_exists($base->code, $rates)) {
                throw new RuntimeException(
                    "ProcurementModuleSeeder cannot convert demo exchange rates to base currency {$base->code}. " .
                    'Use one of ILS/USD/EUR/EGP/TRY as base, or add a deterministic conversion rate to this seeder.'
                );
            }

            $baseIlsRate = (float) $rates[$base->code];

            foreach ($rates as $code => $rate) {
                $currency = $currencies[$code] ?? null;
                if (! $currency) {
                    continue;
                }

                $effectiveRate = $currency->id === $base->id
                    ? 1.00000000
                    : round((float) $rate / max($baseIlsRate, 0.00000001), 8);

                ExchangeRate::query()->updateOrCreate(
                    [
                        'currency_id' => $currency->id,
                        'effective_date' => $date,
                    ],
                    [
                        'rate_to_base' => $effectiveRate,
                        'created_by' => $actor?->id,
                    ],
                );
            }
        }
    }

    /** @return array<int, Permission> */
    private function seedPermissions(): array
    {
        $names = [
            'stock_requests.view',
            'stock_transfers.view',
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

            'purchase_returns.view',
            'purchase_returns.create',
            'purchase_returns.approve',
            'purchase_returns.cancel',

            'supplier_invoices.view',
            'supplier_invoices.create',
            'supplier_invoices.cancel',

            'supplier_payments.view',
            'supplier_payments.create',

            'procurement.reports.view',
            'procurement.exchange_rates.view',
            'procurement.exchange_rates.manage',
            'dashboard.procurement',
        ];

        return collect($names)
            ->map(fn (string $name): Permission => Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]))
            ->all();
    }

    /** @param array<int, Permission> $permissions */
    private function grantDefaultRoles(array $permissions): void
    {
        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['Admin', 'super-admin', 'Super Admin'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        $defaults = [
            'Factory Manager' => [
                'suppliers.view', 'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update',
                'goods_receipts.view', 'goods_receipts.create', 'purchase_returns.view', 'purchase_returns.create',
                'stock_requests.view', 'stock_transfers.view', 'receiving_invoices.view', 'dashboard.procurement',
            ],
            'Accountant' => [
                'suppliers.view', 'purchase_orders.view', 'goods_receipts.view', 'purchase_returns.view',
                'supplier_invoices.view', 'supplier_invoices.create',
                'supplier_payments.view', 'supplier_payments.create', 'procurement.reports.view',
                'procurement.exchange_rates.view', 'procurement.exchange_rates.manage', 'dashboard.procurement',
            ],
            'Branch Manager' => [
                'stock_requests.view', 'stock_transfers.view', 'receiving_invoices.view',
            ],
            'Inventory Manager' => [
                'stock_requests.view', 'stock_transfers.view', 'receiving_invoices.view',
            ],
        ];
        foreach ($defaults as $roleName => $permissionNames) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role) {
                $role->givePermissionTo($permissionNames);
            }
        }
    }

    /** @return array<string, mixed> */
    private function resolveContext(): array
    {
        $admin = User::query()->where('username', 'admin')->first() ?? User::query()->first();
        if (! $admin) {
            throw new RuntimeException(
                'ProcurementModuleSeeder requires at least one user. Run the core DatabaseSeeder first.'
            );
        }

        $factoryManager = User::query()->whereIn('username', ['factory_mgr', 'factory.manager'])->first() ?? $admin;
        $accountant = User::query()->where('username', 'accountant')->first() ?? $admin;
        $inventoryManager = User::query()->whereIn('username', ['inventory_mgr', 'inventory.manager'])->first() ?? $factoryManager;

        $factory = Location::query()->where('code', 'FAC')->first()
            ?? Location::query()->where('type', 'factory')->first();

        if (! $factory) {
            throw new RuntimeException(
                'ProcurementModuleSeeder requires a factory Location (expected code FAC). Run the core DatabaseSeeder first.'
            );
        }

        $branch = Location::query()->where('code', 'B01')->first()
            ?? Location::query()->where('type', 'branch')->first()
            ?? $factory;

        $egyptBranch = Location::query()->where('code', 'B05')->first()
            ?? Location::query()->where('type', 'branch')->latest('id')->first()
            ?? $branch;

        $bank = PaymentMethod::query()->whereIn('code', ['BANK', 'bank'])->first();
        $cash = PaymentMethod::query()->whereIn('code', ['CASH', 'cash'])->first();
        $fallbackMethod = $bank ?? $cash ?? PaymentMethod::query()->where('is_active', true)->first();

        if (! $fallbackMethod) {
            throw new RuntimeException(
                'ProcurementModuleSeeder requires at least one active payment method. Run the core DatabaseSeeder first.'
            );
        }

        return compact(
            'admin',
            'factoryManager',
            'accountant',
            'inventoryManager',
            'factory',
            'branch',
            'egyptBranch',
            'bank',
            'cash',
            'fallbackMethod',
        );
    }

    /** @param array<string, mixed> $context */
    private function ensureProcurementPaymentMethods(array $context): void
    {
        $locations = collect([$context['factory'], $context['egyptBranch']])
            ->filter()
            ->unique('id');

        $methods = collect([$context['bank'], $context['cash'], $context['fallbackMethod']])
            ->filter()
            ->unique('id');

        foreach ($locations as $location) {
            foreach ($methods as $method) {
                LocationPaymentMethod::query()->updateOrCreate(
                    [
                        'location_id' => $location->id,
                        'payment_method_id' => $method->id,
                    ],
                    ['is_active' => true],
                );
            }
        }
    }

    /**
     * Procurement-only products are deliberately grouped under their own hidden
     * category and are available only at procurement locations. This keeps the
     * normal customer-facing sweet catalogue clean.
     *
     * @param array<string, mixed> $context
     * @return array<string, Product>
     */
    private function seedProcurementProducts(array $context): array
    {
        $category = Category::query()->updateOrCreate(
            ['slug' => 'procurement-raw-materials'],
            [
                'name' => 'Procurement Raw Materials',
                'name_ar' => 'مواد خام ومستلزمات مشتريات',
                'description' => 'مواد خام ومستلزمات مخصصة للمشتريات والمخزون وليست للبيع المباشر.',
                'is_active' => false,
                'sort_order' => 900,
            ],
        );

        $rows = [
            'flour' => ['PRC-RM-FLOUR', '6291200000011', 'Premium Pastry Flour', 'دقيق حلويات فاخر', 'kg', true, true],
            'sugar' => ['PRC-RM-SUGAR', '6291200000028', 'Fine White Sugar', 'سكر أبيض ناعم', 'kg', true, true],
            'butter' => ['PRC-RM-BUTTER', '6291200000035', 'Unsalted Pastry Butter', 'زبدة حلويات غير مملحة', 'kg', true, true],
            'cream' => ['PRC-RM-CREAM', '6291200000042', 'Whipping Cream', 'كريمة خفق', 'liter', true, true],
            'chocolate' => ['PRC-RM-CHOC', '6291200000059', 'Dark Couverture Chocolate', 'شوكولاتة خام داكنة', 'kg', true, true],
            'pistachio' => ['PRC-RM-PISTACHIO', '6291200000066', 'Premium Pistachio', 'فستق حلبي فاخر', 'kg', true, true],
            'cocoa' => ['PRC-RM-COCOA', '6291200000073', 'Cocoa Powder', 'كاكاو خام', 'kg', true, true],
            'vanilla' => ['PRC-RM-VANILLA', '6291200000080', 'Vanilla Paste', 'معجون فانيلا', 'kg', true, true],
            'cake_box' => ['PRC-PK-CAKEBOX-M', '6291200000097', 'Medium Cake Box', 'علبة كيك متوسطة', 'piece', false, false],
            'gift_box' => ['PRC-PK-GIFTBOX', '6291200000103', 'Premium Gold Gift Box', 'علبة هدايا ذهبية فاخرة', 'piece', false, false],
        ];

        $products = [];

        foreach ($rows as $key => [$sku, $barcode, $name, $nameAr, $unit, $tracksBatch, $tracksExpiry]) {
            $product = Product::withTrashed()->where('sku', $sku)->first();

            if (! $product) {
                $product = new Product(['sku' => $sku]);
            } elseif (method_exists($product, 'trashed') && $product->trashed()) {
                $product->restore();
            }

            $product->fill([
                'category_id' => $category->id,
                'name' => $name,
                'name_ar' => $nameAr,
                'barcode' => $barcode,
                'description' => 'Procurement demo material — ' . $nameAr,
                'unit' => $unit,
                'base_selling_price' => 0,
                'is_active' => true,
                'tracks_batch' => $tracksBatch,
                'tracks_expiry' => $tracksExpiry,
            ]);
            $product->save();

            $products[$key] = $product;
        }

        $locations = collect([$context['factory'], $context['egyptBranch']])
            ->filter()
            ->unique('id');

        foreach ($locations as $location) {
            foreach ($products as $product) {
                LocationProduct::query()->updateOrCreate(
                    ['location_id' => $location->id, 'product_id' => $product->id],
                    [
                        'is_available' => false,
                        'local_selling_price' => null,
                        'minimum_stock_level' => in_array($product->unit, ['kg', 'liter'], true) ? 10 : 25,
                    ],
                );

                Inventory::query()->firstOrCreate(
                    ['location_id' => $location->id, 'product_id' => $product->id],
                    [
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'damaged_quantity' => 0,
                        'in_transit_quantity' => 0,
                        'unit_cost' => 0,
                    ],
                );
            }
        }

        return $products;
    }

    /**
     * @param array<string, Currency> $currencies
     * @param array<string, mixed> $context
     * @return array<string, Supplier>
     */
    private function seedSuppliers(array $currencies, array $context): array
    {
        $rows = [
            'pal_foods' => [
                'supplier_code' => 'SUP-PRC-001',
                'name' => 'شركة فلسطين للمواد الغذائية',
                'company_name' => 'Palestine Food Ingredients Co.',
                'contact_person' => 'محمود أبو عاصي',
                'phone' => '0599101001',
                'whatsapp' => '0599101001',
                'email' => 'sales@palfoods.test',
                'address' => 'غزة — المنطقة الصناعية',
                'city' => 'غزة',
                'country' => 'فلسطين',
                'tax_number' => 'TAX-PRC-10001',
                'commercial_registration' => 'CR-PRC-10001',
                'currency_id' => $currencies['ILS']->id,
                'payment_terms' => '30 days',
                'credit_limit' => 50000,
                'opening_balance' => 1200,
                'status' => 'active',
                'notes' => 'مورد رئيسي للزبدة والكريمة والمواد الغذائية.',
            ],
            'quds_mills' => [
                'supplier_code' => 'SUP-PRC-002',
                'name' => 'مطاحن القدس والحبوب',
                'company_name' => 'Al-Quds Mills & Grains',
                'contact_person' => 'رامي النجار',
                'phone' => '0599101002',
                'whatsapp' => '0599101002',
                'email' => 'orders@qudsmills.test',
                'address' => 'دير البلح — شارع الصناعة',
                'city' => 'دير البلح',
                'country' => 'فلسطين',
                'tax_number' => 'TAX-PRC-10002',
                'commercial_registration' => 'CR-PRC-10002',
                'currency_id' => $currencies['ILS']->id,
                'payment_terms' => '15 days',
                'credit_limit' => 35000,
                'opening_balance' => 0,
                'status' => 'active',
                'notes' => 'مورد دقيق وسكر وحبوب.',
            ],
            'nile' => [
                'supplier_code' => 'SUP-PRC-003',
                'name' => 'شركة النيل لمستلزمات الحلويات',
                'company_name' => 'Nile Confectionery Supplies',
                'contact_person' => 'أحمد منصور',
                'phone' => '+201001110003',
                'whatsapp' => '+201001110003',
                'email' => 'export@nileconfectionery.test',
                'address' => 'القاهرة — مدينة العبور',
                'city' => 'القاهرة',
                'country' => 'مصر',
                'tax_number' => 'EG-TAX-PRC-30003',
                'commercial_registration' => 'EG-CR-PRC-30003',
                'currency_id' => $currencies['EGP']->id,
                'payment_terms' => '45 days',
                'credit_limit' => 250000,
                'opening_balance' => 0,
                'status' => 'active',
                'notes' => 'مورد فرع مصر — الفواتير بالجنيه المصري.',
            ],
            'euro_cacao' => [
                'supplier_code' => 'SUP-PRC-004',
                'name' => 'Euro Cacao Trading',
                'company_name' => 'Euro Cacao Trading GmbH',
                'contact_person' => 'Martin Keller',
                'phone' => '+4915200001004',
                'whatsapp' => '+4915200001004',
                'email' => 'mena@eurocacao.test',
                'address' => 'Hamburg',
                'city' => 'Hamburg',
                'country' => 'Germany',
                'tax_number' => 'DE-PRC-40004',
                'commercial_registration' => 'DE-CR-PRC-40004',
                'currency_id' => $currencies['EUR']->id,
                'payment_terms' => '30 days',
                'credit_limit' => 30000,
                'opening_balance' => 0,
                'status' => 'active',
                'notes' => 'شوكولاتة خام وفستق ومكونات مستوردة.',
            ],
            'global_baking' => [
                'supplier_code' => 'SUP-PRC-005',
                'name' => 'Global Baking Ingredients',
                'company_name' => 'Global Baking Ingredients LLC',
                'contact_person' => 'Omar Saleh',
                'phone' => '+971500001005',
                'whatsapp' => '+971500001005',
                'email' => 'sales@globalbaking.test',
                'address' => 'Dubai — JAFZA',
                'city' => 'Dubai',
                'country' => 'UAE',
                'tax_number' => 'AE-PRC-50005',
                'commercial_registration' => 'AE-CR-PRC-50005',
                'currency_id' => $currencies['USD']->id,
                'payment_terms' => 'Advance / 30 days',
                'credit_limit' => 20000,
                'opening_balance' => 350,
                'status' => 'active',
                'notes' => 'كاكاو وفانيلا ومكونات مستوردة بالدولار.',
            ],
            'gold_pack' => [
                'supplier_code' => 'SUP-PRC-006',
                'name' => 'مؤسسة التغليف الذهبي',
                'company_name' => 'Golden Pack Palestine',
                'contact_person' => 'سامي حمدان',
                'phone' => '0599101006',
                'whatsapp' => '0599101006',
                'email' => 'orders@goldenpack.test',
                'address' => 'خانيونس',
                'city' => 'خانيونس',
                'country' => 'فلسطين',
                'tax_number' => 'TAX-PRC-10006',
                'commercial_registration' => 'CR-PRC-10006',
                'currency_id' => $currencies['ILS']->id,
                'payment_terms' => 'Cash',
                'credit_limit' => 10000,
                'opening_balance' => 0,
                'status' => 'inactive',
                'notes' => 'مورد تغليف غير نشط حالياً — موجود لاختبار حالات المورد.',
            ],
            'blocked_demo' => [
                'supplier_code' => 'SUP-PRC-007',
                'name' => 'مورد تجريبي محظور',
                'company_name' => 'Blocked Supplier Demo',
                'contact_person' => '—',
                'phone' => '0599101007',
                'whatsapp' => null,
                'email' => 'blocked@supplier.test',
                'address' => 'غزة',
                'city' => 'غزة',
                'country' => 'فلسطين',
                'tax_number' => null,
                'commercial_registration' => null,
                'currency_id' => $currencies['ILS']->id,
                'payment_terms' => 'Cash',
                'credit_limit' => 0,
                'opening_balance' => 0,
                'status' => 'blocked',
                'notes' => 'سجل مخصص لاختبار حالة Blocked فقط.',
            ],
        ];

        $suppliers = [];

        foreach ($rows as $key => $row) {
            $supplier = Supplier::withTrashed()
                ->where('supplier_code', $row['supplier_code'])
                ->first();

            if (! $supplier) {
                $supplier = new Supplier(['supplier_code' => $row['supplier_code']]);
            } elseif (method_exists($supplier, 'trashed') && $supplier->trashed()) {
                $supplier->restore();
            }

            $supplier->fill($row + ['created_by' => $context['admin']->id]);
            $supplier->save();
            $suppliers[$key] = $supplier;
        }

        $contacts = [
            ['pal_foods', 'محمود أبو عاصي', 'مدير المبيعات', '0599101001', '0599101001', 'mahmoud@palfoods.test', true],
            ['pal_foods', 'سارة قاسم', 'الحسابات', '0599101101', '0599101101', 'accounts@palfoods.test', false],
            ['quds_mills', 'رامي النجار', 'المبيعات', '0599101002', '0599101002', 'rami@qudsmills.test', true],
            ['nile', 'أحمد منصور', 'Export Manager', '+201001110003', '+201001110003', 'ahmed@nileconfectionery.test', true],
            ['euro_cacao', 'Martin Keller', 'MENA Sales', '+4915200001004', '+4915200001004', 'martin@eurocacao.test', true],
            ['global_baking', 'Omar Saleh', 'Regional Sales', '+971500001005', '+971500001005', 'omar@globalbaking.test', true],
            ['gold_pack', 'سامي حمدان', 'المدير', '0599101006', '0599101006', 'sami@goldenpack.test', true],
        ];

        foreach ($contacts as [$supplierKey, $name, $position, $phone, $whatsapp, $email, $isPrimary]) {
            SupplierContact::query()->updateOrCreate(
                ['supplier_id' => $suppliers[$supplierKey]->id, 'email' => $email],
                [
                    'name' => $name,
                    'position' => $position,
                    'phone' => $phone,
                    'whatsapp' => $whatsapp,
                    'is_primary' => $isPrimary,
                    'notes' => $isPrimary ? 'جهة الاتصال الرئيسية.' : null,
                ],
            );
        }

        return $suppliers;
    }

    /**
     * @param array<string, Supplier> $suppliers
     * @param array<string, Product> $products
     * @param array<string, Currency> $currencies
     * @param array<string, mixed> $context
     * @return array<string, SupplierProduct>
     */
    private function seedSupplierProducts(
        array $suppliers,
        array $products,
        array $currencies,
        array $context,
    ): array {
        $rows = [
            // supplier, product, price, currency, MOQ, lead days, preferred, supplier sku
            ['pal_foods', 'butter', 18.0000, 'ILS', 10, 2, true, 'PF-BUT-10'],
            ['pal_foods', 'cream', 14.0000, 'ILS', 12, 2, true, 'PF-CRM-12'],
            ['pal_foods', 'sugar', 6.3000, 'ILS', 25, 2, false, 'PF-SUG-25'],

            ['quds_mills', 'flour', 7.2000, 'ILS', 25, 1, true, 'QM-FLR-25'],
            ['quds_mills', 'sugar', 6.0000, 'ILS', 25, 1, true, 'QM-SUG-25'],

            ['nile', 'cream', 240.0000, 'EGP', 20, 5, true, 'NCS-CREAM'],
            ['nile', 'cocoa', 310.0000, 'EGP', 10, 5, false, 'NCS-COCOA'],

            ['euro_cacao', 'chocolate', 9.5000, 'EUR', 20, 14, true, 'ECT-CHOC-DARK'],
            ['euro_cacao', 'pistachio', 16.5000, 'EUR', 10, 14, true, 'ECT-PIST'],
            ['euro_cacao', 'cocoa', 7.9000, 'EUR', 20, 14, false, 'ECT-COCOA'],

            ['global_baking', 'cocoa', 8.5000, 'USD', 15, 10, true, 'GBI-COCOA'],
            ['global_baking', 'vanilla', 18.0000, 'USD', 5, 10, true, 'GBI-VANILLA'],
            ['global_baking', 'chocolate', 10.2000, 'USD', 20, 10, false, 'GBI-CHOC'],

            ['gold_pack', 'cake_box', 2.2000, 'ILS', 100, 3, true, 'GP-BOX-M'],
            ['gold_pack', 'gift_box', 5.5000, 'ILS', 50, 3, true, 'GP-GIFT-GOLD'],
        ];

        $result = [];

        foreach ($rows as [$supplierKey, $productKey, $price, $currencyCode, $moq, $leadDays, $preferred, $supplierSku]) {
            $supplier = $suppliers[$supplierKey];
            $product = $products[$productKey];
            $currency = $currencies[$currencyCode];

            $sp = SupplierProduct::query()->updateOrCreate(
                ['supplier_id' => $supplier->id, 'product_id' => $product->id],
                [
                    'supplier_sku' => $supplierSku,
                    'supplier_product_name' => $product->name,
                    'purchase_price' => $price,
                    'currency_id' => $currency->id,
                    'minimum_order_quantity' => $moq,
                    'lead_time_days' => $leadDays,
                    'is_preferred' => $preferred,
                    'is_active' => true,
                    'notes' => $preferred ? 'مورد مفضل لهذا المنتج.' : 'مورد بديل.',
                ],
            );

            $key = $supplierKey . ':' . $productKey;
            $result[$key] = $sp;

            $rate = $this->rateFor($currency, '2026-01-01');
            SupplierProductPriceHistory::query()->updateOrCreate(
                [
                    'supplier_product_id' => $sp->id,
                    'reference_type' => 'procurement_seed_opening_price',
                    'reference_id' => $sp->id,
                ],
                [
                    'purchase_price' => $price,
                    'currency_id' => $currency->id,
                    'exchange_rate' => $rate,
                    'base_purchase_price' => round($price * $rate, 4),
                    'effective_at' => Carbon::parse('2026-01-01 09:00:00'),
                    'created_by' => $context['admin']->id,
                ],
            );
        }

        return $result;
    }

    /**
     * @param array<string, Supplier> $suppliers
     * @param array<string, Product> $products
     * @param array<string, Currency> $currencies
     * @param array<string, mixed> $context
     * @return array<string, PurchaseOrder>
     */
    private function seedPurchaseOrders(
        array $suppliers,
        array $products,
        array $currencies,
        array $context,
    ): array {
        $specs = [
            'draft' => [
                'number' => 'PO-2026-PRC-001',
                'supplier' => 'quds_mills',
                'location' => 'factory',
                'currency' => 'ILS',
                'date' => '2026-08-02',
                'expected' => '2026-08-05',
                'status' => 'draft',
                'shipping' => 40,
                'items' => [
                    ['flour', 80, 7.5000],
                    ['sugar', 60, 6.2000],
                ],
                'notes' => 'طلب مسودة لاختبار التعديل قبل الإرسال.',
            ],
            'submitted' => [
                'number' => 'PO-2026-PRC-002',
                'supplier' => 'global_baking',
                'location' => 'factory',
                'currency' => 'USD',
                'date' => '2026-08-03',
                'expected' => '2026-08-18',
                'status' => 'submitted',
                'shipping' => 25,
                'items' => [
                    ['cocoa', 25, 8.5000],
                    ['vanilla', 10, 18.0000],
                ],
                'notes' => 'طلب مستورد بالدولار بانتظار الموافقة.',
            ],
            'approved' => [
                'number' => 'PO-2026-PRC-003',
                'supplier' => 'euro_cacao',
                'location' => 'factory',
                'currency' => 'EUR',
                'date' => '2026-07-27',
                'expected' => '2026-08-17',
                'status' => 'approved',
                'shipping' => 30,
                'items' => [
                    ['chocolate', 40, 9.5000],
                    ['pistachio', 20, 16.5000],
                ],
                'notes' => 'طلب معتمد — يوجد له سند استلام Draft لم يُرحّل بعد.',
            ],
            'partial' => [
                'number' => 'PO-2026-PRC-004',
                'supplier' => 'quds_mills',
                'location' => 'factory',
                'currency' => 'ILS',
                'date' => '2026-07-20',
                'expected' => '2026-07-24',
                'status' => 'partially_received',
                'shipping' => 50,
                'items' => [
                    ['flour', 100, 7.2000],
                    ['sugar', 80, 6.0000],
                ],
                'notes' => 'استلام جزئي؛ باقي الكميات لم تصل بعد.',
            ],
            'received' => [
                'number' => 'PO-2026-PRC-005',
                'supplier' => 'pal_foods',
                'location' => 'factory',
                'currency' => 'ILS',
                'date' => '2026-07-10',
                'expected' => '2026-07-13',
                'status' => 'received',
                'shipping' => 60,
                'items' => [
                    ['butter', 100, 18.0000],
                    ['cream', 50, 14.0000],
                ],
                'notes' => 'طلب مكتمل على دفعتين: 60/40 للزبدة و30/20 للكريمة.',
            ],
            'cancelled' => [
                'number' => 'PO-2026-PRC-006',
                'supplier' => 'gold_pack',
                'location' => 'factory',
                'currency' => 'ILS',
                'date' => '2026-07-15',
                'expected' => '2026-07-18',
                'status' => 'cancelled',
                'shipping' => 30,
                'items' => [
                    ['cake_box', 300, 2.2000],
                    ['gift_box', 120, 5.5000],
                ],
                'notes' => 'أُلغي الطلب قبل الاستلام.',
                'cancellation_reason' => 'تم تغيير مواصفات التغليف قبل التوريد.',
            ],
            'egypt_received' => [
                'number' => 'PO-2026-PRC-007',
                'supplier' => 'nile',
                'location' => 'egyptBranch',
                'currency' => 'EGP',
                'date' => '2026-07-01',
                'expected' => '2026-07-06',
                'status' => 'received',
                'shipping' => 800,
                'items' => [
                    ['cream', 100, 240.0000],
                ],
                'notes' => 'سيناريو فرع مصر: استلام 100، قبول 90، رفض 10.',
            ],
            'cancelled_receipt_source' => [
                'number' => 'PO-2026-PRC-008',
                'supplier' => 'gold_pack',
                'location' => 'factory',
                'currency' => 'ILS',
                'date' => '2026-08-05',
                'expected' => '2026-08-09',
                'status' => 'approved',
                'shipping' => 20,
                'items' => [
                    ['cake_box', 150, 2.1500],
                ],
                'notes' => 'مصدر لسند استلام ملغى لاختبار الحالة.',
            ],
        ];

        $orders = [];

        foreach ($specs as $key => $spec) {
            $supplier = $suppliers[$spec['supplier']];
            $currency = $currencies[$spec['currency']];
            $location = $context[$spec['location']];
            $rate = $this->rateFor($currency, $spec['date']);

            $subtotal = collect($spec['items'])
                ->sum(fn (array $item): float => (float) $item[1] * (float) $item[2]);
            $discount = 0.0;
            $tax = 0.0;
            $shipping = (float) $spec['shipping'];
            $grandTotal = round($subtotal - $discount + $tax + $shipping, 2);

            $order = PurchaseOrder::withTrashed()
                ->where('purchase_order_number', $spec['number'])
                ->first();

            if (! $order) {
                $order = new PurchaseOrder(['purchase_order_number' => $spec['number']]);
            } elseif (method_exists($order, 'trashed') && $order->trashed()) {
                $order->restore();
            }

            $approved = in_array($spec['status'], ['approved', 'partially_received', 'received'], true);
            $cancelled = $spec['status'] === 'cancelled';

            $order->fill([
                'supplier_id' => $supplier->id,
                'location_id' => $location->id,
                'currency_id' => $currency->id,
                'exchange_rate' => $rate,
                'order_date' => $spec['date'],
                'expected_delivery_date' => $spec['expected'],
                'status' => $spec['status'],
                'subtotal' => round($subtotal, 2),
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'shipping_cost' => $shipping,
                'grand_total' => $grandTotal,
                'base_grand_total' => round($grandTotal * $rate, 2),
                'notes' => $spec['notes'],
                'created_by' => $context['factoryManager']->id,
                'approved_by' => $approved ? $context['factoryManager']->id : null,
                'approved_at' => $approved ? Carbon::parse($spec['date'])->addDay()->setTime(10, 0) : null,
                'cancelled_by' => $cancelled ? $context['factoryManager']->id : null,
                'cancelled_at' => $cancelled ? Carbon::parse($spec['date'])->addDay()->setTime(13, 0) : null,
                'cancellation_reason' => $cancelled ? ($spec['cancellation_reason'] ?? 'تم الإلغاء.') : null,
            ]);
            $order->save();

            foreach ($spec['items'] as [$productKey, $quantity, $unitPrice]) {
                $product = $products[$productKey];
                $lineTotal = round((float) $quantity * (float) $unitPrice, 2);

                PurchaseOrderItem::query()->updateOrCreate(
                    ['purchase_order_id' => $order->id, 'product_id' => $product->id],
                    [
                        'description' => $product->name_ar ?: $product->name,
                        'ordered_quantity' => $quantity,
                        'received_quantity' => 0,
                        'unit_price' => $unitPrice,
                        'discount_amount' => 0,
                        'tax_amount' => 0,
                        'line_total' => $lineTotal,
                        'base_unit_cost' => round((float) $unitPrice * $rate, 4),
                        'base_line_total' => round($lineTotal * $rate, 2),
                        'notes' => null,
                    ],
                );
            }

            $orders[$key] = $order->fresh(['items', 'supplier', 'currency', 'location']);
        }

        return $orders;
    }

    /**
     * @param array<string, PurchaseOrder> $orders
     * @param array<string, mixed> $context
     * @return array<string, GoodsReceipt>
     */
    private function seedGoodsReceipts(array $orders, array $context): array
    {
        $specs = [
            'draft' => [
                'number' => 'GR-2026-PRC-001',
                'po' => 'approved',
                'status' => 'draft',
                'date' => '2026-08-12 10:00:00',
                'items' => [
                    ['chocolate', 10, 10, 0],
                    ['pistachio', 5, 5, 0],
                ],
                'notes' => 'سند استلام Draft لم يتم ترحيله للمخزون.',
            ],
            'partial' => [
                'number' => 'GR-2026-PRC-002',
                'po' => 'partial',
                'status' => 'posted',
                'date' => '2026-07-24 09:30:00',
                'items' => [
                    ['flour', 60, 60, 0],
                    ['sugar', 50, 48, 2],
                ],
                'notes' => 'استلام جزئي مع رفض 2 كغم سكر بسبب تلف التغليف.',
            ],
            'received_1' => [
                'number' => 'GR-2026-PRC-003',
                'po' => 'received',
                'status' => 'posted',
                'date' => '2026-07-13 08:45:00',
                'items' => [
                    ['butter', 60, 60, 0],
                    ['cream', 30, 30, 0],
                ],
                'notes' => 'الدفعة الأولى من أمر الشراء.',
            ],
            'received_2' => [
                'number' => 'GR-2026-PRC-004',
                'po' => 'received',
                'status' => 'posted',
                'date' => '2026-07-16 11:15:00',
                'items' => [
                    ['butter', 40, 40, 0],
                    ['cream', 20, 20, 0],
                ],
                'notes' => 'الدفعة الثانية والأخيرة.',
            ],
            'egypt_rejected' => [
                'number' => 'GR-2026-PRC-005',
                'po' => 'egypt_received',
                'status' => 'posted',
                'date' => '2026-07-06 12:00:00',
                'items' => [
                    ['cream', 100, 90, 10],
                ],
                'notes' => 'استلام 100 لتر، قبول 90 ورفض 10 بسبب عدم مطابقة درجة الحرارة.',
            ],
            'cancelled' => [
                'number' => 'GR-2026-PRC-006',
                'po' => 'cancelled_receipt_source',
                'status' => 'cancelled',
                'date' => '2026-08-08 10:30:00',
                'items' => [
                    ['cake_box', 50, 50, 0],
                ],
                'notes' => 'تم إلغاء سند الاستلام قبل الترحيل؛ لا يؤثر على المخزون.',
            ],
        ];

        $result = [];

        foreach ($specs as $key => $spec) {
            $po = $orders[$spec['po']]->fresh(['items.product']);
            $posted = $spec['status'] === 'posted';

            $receipt = GoodsReceipt::withTrashed()
                ->where('receipt_number', $spec['number'])
                ->first();

            if (! $receipt) {
                $receipt = new GoodsReceipt(['receipt_number' => $spec['number']]);
            } elseif (method_exists($receipt, 'trashed') && $receipt->trashed()) {
                $receipt->restore();
            }

            $receipt->fill([
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'location_id' => $po->location_id,
                'currency_id' => $po->currency_id,
                'exchange_rate' => $po->exchange_rate,
                'status' => $spec['status'],
                'received_by' => $context['inventoryManager']->id,
                'received_at' => Carbon::parse($spec['date']),
                'posted_by' => $posted ? $context['inventoryManager']->id : null,
                'posted_at' => $posted ? Carbon::parse($spec['date'])->addMinutes(25) : null,
                'notes' => $spec['notes'],
            ]);
            $receipt->save();

            foreach ($spec['items'] as [$productKey, $receivedQty, $acceptedQty, $rejectedQty]) {
                $poItem = $po->items->first(fn (PurchaseOrderItem $item) => $item->product?->sku === $this->productSku($productKey));

                if (! $poItem) {
                    throw new RuntimeException("PO item not found for product key {$productKey} in {$po->purchase_order_number}");
                }

                $unitCost = (float) $poItem->unit_price;
                $lineTotal = round((float) $acceptedQty * $unitCost, 2);
                $batchNumber = $poItem->product->tracks_batch
                    ? 'BATCH-' . str_replace(['GR-2026-PRC-', '-'], ['', ''], $spec['number']) . '-' . $poItem->product_id
                    : null;

                $mfg = $poItem->product->tracks_expiry
                    ? Carbon::parse($spec['date'])->subDays(5)->toDateString()
                    : null;
                $expiry = $poItem->product->tracks_expiry
                    ? Carbon::parse($spec['date'])->addMonths(6)->toDateString()
                    : null;

                $gri = GoodsReceiptItem::query()->updateOrCreate(
                    [
                        'goods_receipt_id' => $receipt->id,
                        'purchase_order_item_id' => $poItem->id,
                    ],
                    [
                        'product_id' => $poItem->product_id,
                        'ordered_quantity' => $poItem->ordered_quantity,
                        'received_quantity' => $receivedQty,
                        'accepted_quantity' => $acceptedQty,
                        'rejected_quantity' => $rejectedQty,
                        'unit_cost' => $unitCost,
                        'base_unit_cost' => round($unitCost * (float) $po->exchange_rate, 4),
                        'line_total' => $lineTotal,
                        'base_line_total' => round($lineTotal * (float) $po->exchange_rate, 2),
                        'batch_number' => $batchNumber,
                        'manufacturing_date' => $mfg,
                        'expiry_date' => $expiry,
                        'notes' => $rejectedQty > 0 ? 'يوجد كمية مرفوضة موثقة.' : null,
                    ],
                );

                if ($posted && $poItem->product->tracks_batch && (float) $acceptedQty > 0) {
                    InventoryBatch::query()->updateOrCreate(
                        [
                            'goods_receipt_item_id' => $gri->id,
                            'product_id' => $gri->product_id,
                            'location_id' => $receipt->location_id,
                        ],
                        [
                            'batch_number' => $batchNumber,
                            'manufacturing_date' => $mfg,
                            'expiry_date' => $expiry,
                            'received_quantity' => $acceptedQty,
                            'available_quantity' => $acceptedQty,
                            'unit_cost' => $unitCost,
                            'base_unit_cost' => round($unitCost * (float) $po->exchange_rate, 4),
                            'currency_id' => $po->currency_id,
                        ],
                    );
                }
            }

            $result[$key] = $receipt->fresh(['items.product', 'purchaseOrder.items']);
        }

        $this->recalculatePurchaseOrderReceiving($orders);

        return $result;
    }

    /** @param array<string, PurchaseOrder> $orders */
    private function recalculatePurchaseOrderReceiving(array $orders): void
    {
        foreach ($orders as $key => $order) {
            $order->load('items');

            foreach ($order->items as $item) {
                $physicalReceived = (float) GoodsReceiptItem::query()
                    ->where('purchase_order_item_id', $item->id)
                    ->whereHas('goodsReceipt', fn ($q) => $q->where('status', 'posted'))
                    ->sum('received_quantity');

                $item->update(['received_quantity' => round($physicalReceived, 3)]);
            }

            // Preserve workflow terminal states that are not computed from receipts.
            if (in_array($key, ['draft', 'submitted', 'cancelled'], true)) {
                continue;
            }

            $freshItems = $order->fresh('items')->items;
            $ordered = (float) $freshItems->sum('ordered_quantity');
            $received = (float) $freshItems->sum('received_quantity');

            $status = $received <= 0
                ? 'approved'
                : ($received + 0.0001 < $ordered ? 'partially_received' : 'received');

            $order->update(['status' => $status]);
        }
    }

    /**
     * @param array<string, PurchaseOrder> $orders
     * @param array<string, GoodsReceipt> $receipts
     * @param array<string, Currency> $currencies
     * @param array<string, mixed> $context
     * @return array<string, SupplierInvoice>
     */
    private function seedSupplierInvoices(
        array $orders,
        array $receipts,
        array $currencies,
        array $context,
    ): array {
        $specs = [
            'unpaid' => [
                'number' => 'SINV-2026-PRC-001',
                'po' => 'approved',
                'receipt' => null,
                'invoice_date' => '2026-08-10',
                'due_date' => '2026-09-09',
                'notes' => 'فاتورة مورد غير مدفوعة مرتبطة بأمر شراء معتمد.',
                'source' => 'po',
            ],
            'partial' => [
                'number' => 'SINV-2026-PRC-002',
                'po' => 'partial',
                'receipt' => 'partial',
                'invoice_date' => '2026-07-25',
                'due_date' => '2026-08-24',
                'notes' => 'فاتورة للاستلام الجزئي؛ عليها دفعة جزئية.',
                'source' => 'receipt',
            ],
            'paid' => [
                'number' => 'SINV-2026-PRC-003',
                'po' => 'received',
                'receipt' => 'received_2',
                'invoice_date' => '2026-07-17',
                'due_date' => '2026-08-16',
                'notes' => 'فاتورة أمر شراء مكتمل؛ تمت تسويتها بدفعات + إشعار مرتجع.',
                'source' => 'po',
            ],
            'overdue' => [
                'number' => 'SINV-2026-PRC-004',
                'po' => 'egypt_received',
                'receipt' => 'egypt_rejected',
                'invoice_date' => '2026-07-07',
                'due_date' => '2026-07-25',
                'notes' => 'فاتورة فرع مصر متأخرة وغير مدفوعة.',
                'source' => 'receipt',
            ],
            'cancelled' => [
                'number' => 'SINV-2026-PRC-005',
                'po' => 'cancelled',
                'receipt' => null,
                'invoice_date' => '2026-07-16',
                'due_date' => '2026-07-16',
                'notes' => 'فاتورة ملغاة بالتزامن مع إلغاء أمر التغليف.',
                'source' => 'po',
                'cancelled' => true,
            ],
        ];

        $result = [];

        foreach ($specs as $key => $spec) {
            $po = $orders[$spec['po']]->fresh(['items.product', 'currency']);
            $receipt = $spec['receipt'] ? $receipts[$spec['receipt']]->fresh(['items.product']) : null;
            $cancelled = (bool) ($spec['cancelled'] ?? false);

            $lines = [];
            if ($spec['source'] === 'receipt' && $receipt) {
                foreach ($receipt->items as $item) {
                    if ((float) $item->accepted_quantity <= 0) {
                        continue;
                    }
                    $lines[] = [
                        'product_id' => $item->product_id,
                        'description' => $item->product?->name_ar ?: $item->product?->name ?: 'صنف مورد',
                        'quantity' => (float) $item->accepted_quantity,
                        'unit_price' => (float) $item->unit_cost,
                    ];
                }
            } else {
                foreach ($po->items as $item) {
                    $lines[] = [
                        'product_id' => $item->product_id,
                        'description' => $item->product?->name_ar ?: $item->product?->name ?: 'صنف مورد',
                        'quantity' => (float) $item->ordered_quantity,
                        'unit_price' => (float) $item->unit_price,
                    ];
                }
            }

            $subtotal = round(collect($lines)->sum(
                fn (array $line): float => $line['quantity'] * $line['unit_price']
            ), 2);
            $discount = 0.0;
            $tax = 0.0;
            $grandTotal = round($subtotal - $discount + $tax, 2);

            $invoice = SupplierInvoice::withTrashed()
                ->where('invoice_number', $spec['number'])
                ->first();

            if (! $invoice) {
                $invoice = new SupplierInvoice(['invoice_number' => $spec['number']]);
            } elseif (method_exists($invoice, 'trashed') && $invoice->trashed()) {
                $invoice->restore();
            }

            $invoice->fill([
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'goods_receipt_id' => $receipt?->id,
                'location_id' => $po->location_id,
                'currency_id' => $po->currency_id,
                'exchange_rate' => $po->exchange_rate,
                'invoice_date' => $spec['invoice_date'],
                'due_date' => $spec['due_date'],
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'grand_total' => $grandTotal,
                'base_grand_total' => round($grandTotal * (float) $po->exchange_rate, 2),
                'paid_amount' => 0,
                'credited_amount' => 0,
                'remaining_amount' => $grandTotal,
                'status' => $cancelled ? 'cancelled' : 'unpaid',
                'notes' => $spec['notes'],
                'created_by' => $context['accountant']->id,
                'cancelled_by' => $cancelled ? $context['accountant']->id : null,
                'cancelled_at' => $cancelled ? Carbon::parse($spec['invoice_date'])->addHours(3) : null,
            ]);
            $invoice->save();

            foreach ($lines as $line) {
                $lineTotal = round($line['quantity'] * $line['unit_price'], 2);
                SupplierInvoiceItem::query()->updateOrCreate(
                    [
                        'supplier_invoice_id' => $invoice->id,
                        'product_id' => $line['product_id'],
                        'description' => $line['description'],
                    ],
                    [
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'discount_amount' => 0,
                        'tax_amount' => 0,
                        'line_total' => $lineTotal,
                    ],
                );
            }

            $result[$key] = $invoice->fresh(['items', 'supplier', 'currency']);
        }

        return $result;
    }

    /**
     * @param array<string, SupplierInvoice> $invoices
     * @param array<string, Currency> $currencies
     * @param array<string, mixed> $context
     */
    private function seedSupplierPayments(array $invoices, array $currencies, array $context): void
    {
        $bank = $context['bank'] ?? $context['fallbackMethod'];
        $cash = $context['cash'] ?? $context['fallbackMethod'];

        // Partially paid invoice: 40%.
        $partial = $invoices['partial'];
        $partialAmount = round((float) $partial->grand_total * 0.40, 2);
        $this->upsertPayment(
            'SP-2026-PRC-001',
            $partial,
            $partialAmount,
            '2026-07-28',
            $bank,
            'BANK-PRC-001',
            $context['accountant'],
        );

        // Paid invoice: 90 ILS will be settled by the posted purchase return.
        $paid = $invoices['paid'];
        $expectedCredit = 90.00;
        $cashTarget = max(0, round((float) $paid->grand_total - $expectedCredit, 2));
        $first = round($cashTarget * 0.60, 2);
        $second = round($cashTarget - $first, 2);

        $this->upsertPayment(
            'SP-2026-PRC-002',
            $paid,
            $first,
            '2026-07-20',
            $bank,
            'BANK-PRC-002',
            $context['accountant'],
        );
        $this->upsertPayment(
            'SP-2026-PRC-003',
            $paid,
            $second,
            '2026-07-26',
            $cash,
            'CASH-PRC-003',
            $context['accountant'],
        );
    }

    private function upsertPayment(
        string $number,
        SupplierInvoice $invoice,
        float $amount,
        string $date,
        PaymentMethod $method,
        string $reference,
        User $actor,
    ): SupplierPayment {
        return SupplierPayment::query()->updateOrCreate(
            ['payment_number' => $number],
            [
                'supplier_id' => $invoice->supplier_id,
                'supplier_invoice_id' => $invoice->id,
                'location_id' => $invoice->location_id,
                'currency_id' => $invoice->currency_id,
                'exchange_rate' => $invoice->exchange_rate,
                'amount' => $amount,
                'base_amount' => round($amount * (float) $invoice->exchange_rate, 2),
                'applied_amount' => $amount,
                'payment_date' => $date,
                'payment_method_id' => $method->id,
                'reference_number' => $reference,
                'status' => 'confirmed',
                'notes' => 'دفعة تجريبية مرتبطة مباشرة بفاتورة المورد.',
                'created_by' => $actor->id,
            ],
        );
    }

    /**
     * @param array<string, GoodsReceipt> $receipts
     * @param array<string, SupplierInvoice> $invoices
     * @param array<string, mixed> $context
     * @return array<string, PurchaseReturn>
     */
    private function seedPurchaseReturns(array $receipts, array $invoices, array $context): array
    {
        $specs = [
            'draft' => [
                'number' => 'PR-2026-PRC-001',
                'receipt' => 'received_1',
                'invoice' => 'paid',
                'status' => 'draft',
                'date' => '2026-07-18 10:00:00',
                'product_sku' => 'PRC-RM-CREAM',
                'quantity' => 2,
                'reason' => 'فحص جودة إضافي — لم يتم ترحيل المرتجع.',
            ],
            'posted' => [
                'number' => 'PR-2026-PRC-002',
                'receipt' => 'received_1',
                'invoice' => 'paid',
                'status' => 'posted',
                'date' => '2026-07-19 13:00:00',
                'product_sku' => 'PRC-RM-BUTTER',
                'quantity' => 5,
                'reason' => 'تلف جزئي بعد الفحص — مرتجع للمورد.',
            ],
            'cancelled' => [
                'number' => 'PR-2026-PRC-003',
                'receipt' => 'egypt_rejected',
                'invoice' => 'overdue',
                'status' => 'cancelled',
                'date' => '2026-07-08 09:00:00',
                'product_sku' => 'PRC-RM-CREAM',
                'quantity' => 3,
                'reason' => 'تم إلغاء المرتجع بعد اتفاق المورد على الاستبدال.',
            ],
        ];

        $result = [];

        foreach ($specs as $key => $spec) {
            $receipt = $receipts[$spec['receipt']]->fresh(['items.product', 'currency']);
            $invoice = $invoices[$spec['invoice']];
            $gri = $receipt->items->first(fn (GoodsReceiptItem $item) => $item->product?->sku === $spec['product_sku']);

            if (! $gri) {
                throw new RuntimeException("Goods receipt item {$spec['product_sku']} was not found for {$receipt->receipt_number}");
            }

            $unitCost = (float) $gri->unit_cost;
            $lineTotal = round((float) $spec['quantity'] * $unitCost, 2);
            $posted = $spec['status'] === 'posted';

            $return = PurchaseReturn::withTrashed()
                ->where('return_number', $spec['number'])
                ->first();

            if (! $return) {
                $return = new PurchaseReturn(['return_number' => $spec['number']]);
            } elseif (method_exists($return, 'trashed') && $return->trashed()) {
                $return->restore();
            }

            $return->fill([
                'supplier_id' => $receipt->supplier_id,
                'goods_receipt_id' => $receipt->id,
                'supplier_invoice_id' => $invoice->id,
                'location_id' => $receipt->location_id,
                'currency_id' => $receipt->currency_id,
                'exchange_rate' => $receipt->exchange_rate,
                'status' => $spec['status'],
                'grand_total' => $lineTotal,
                'base_grand_total' => round($lineTotal * (float) $receipt->exchange_rate, 2),
                'returned_by' => $context['inventoryManager']->id,
                'returned_at' => Carbon::parse($spec['date']),
                'posted_by' => $posted ? $context['inventoryManager']->id : null,
                'posted_at' => $posted ? Carbon::parse($spec['date'])->addMinutes(20) : null,
                'notes' => $spec['reason'],
            ]);
            $return->save();

            $batch = InventoryBatch::query()
                ->where('goods_receipt_item_id', $gri->id)
                ->first();

            PurchaseReturnItem::query()->updateOrCreate(
                [
                    'purchase_return_id' => $return->id,
                    'goods_receipt_item_id' => $gri->id,
                    'product_id' => $gri->product_id,
                ],
                [
                    'inventory_batch_id' => $batch?->id,
                    'return_quantity' => $spec['quantity'],
                    'unit_cost' => $unitCost,
                    'base_unit_cost' => round($unitCost * (float) $receipt->exchange_rate, 4),
                    'line_total' => $lineTotal,
                    'base_line_total' => round($lineTotal * (float) $receipt->exchange_rate, 2),
                    'reason' => 'quality_return',
                    'notes' => $spec['reason'],
                ],
            );

            $result[$key] = $return->fresh(['items.product']);
        }

        return $result;
    }

    /** @param array<string, SupplierInvoice> $invoices */
    private function synchroniseInvoiceBalances(array $invoices): void
    {
        foreach ($invoices as $key => $invoice) {
            $invoice = $invoice->fresh();

            if ($key === 'cancelled' || $invoice->statusValue() === 'cancelled') {
                $invoice->update([
                    'paid_amount' => 0,
                    'credited_amount' => 0,
                    'remaining_amount' => 0,
                    'status' => 'cancelled',
                ]);
                continue;
            }

            $paid = (float) SupplierPayment::query()
                ->where('supplier_invoice_id', $invoice->id)
                ->where('status', 'confirmed')
                ->sum('applied_amount');

            $credited = (float) PurchaseReturn::query()
                ->where('supplier_invoice_id', $invoice->id)
                ->where('status', 'posted')
                ->sum('grand_total');

            $total = (float) $invoice->grand_total;
            $remaining = max(0, round($total - $paid - $credited, 2));

            if ($remaining <= 0.009) {
                $status = 'paid';
            } elseif ($paid > 0 || $credited > 0) {
                $status = 'partially_paid';
            } elseif (
                $invoice->due_date
                && Carbon::parse($invoice->due_date)->startOfDay()->lt(Carbon::parse(self::DEMO_AS_OF)->startOfDay())
            ) {
                $status = 'overdue';
            } else {
                $status = 'unpaid';
            }

            $invoice->update([
                'paid_amount' => round($paid, 2),
                'credited_amount' => round($credited, 2),
                'remaining_amount' => $remaining,
                'status' => $status,
            ]);
        }
    }

    /**
     * Rebuilds stock only for the dedicated PRC-* procurement demo products.
     * That makes repeated seeding deterministic and prevents double-counting.
     *
     * @param array<string, Product> $products
     * @param array<string, GoodsReceipt> $receipts
     * @param array<string, PurchaseReturn> $returns
     * @param array<string, mixed> $context
     */
    private function synchroniseProcurementInventory(
        array $products,
        array $receipts,
        array $returns,
        array $context,
    ): void {
        $productIds = collect($products)->pluck('id')->all();

        $events = [];

        $postedReceipts = GoodsReceipt::query()
            ->whereIn('id', collect($receipts)->pluck('id'))
            ->where('status', 'posted')
            ->with(['items.product', 'currency'])
            ->get();

        foreach ($postedReceipts as $receipt) {
            foreach ($receipt->items as $item) {
                $quantity = (float) $item->accepted_quantity;
                if ($quantity <= 0) {
                    continue;
                }

                $batch = InventoryBatch::query()
                    ->where('goods_receipt_item_id', $item->id)
                    ->first();

                $events[] = [
                    'kind' => 'receipt',
                    'at' => $receipt->posted_at ?? $receipt->received_at,
                    'location_id' => $receipt->location_id,
                    'product_id' => $item->product_id,
                    'quantity' => $quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'base_unit_cost' => (float) $item->base_unit_cost,
                    'currency_id' => $receipt->currency_id,
                    'exchange_rate' => (float) $receipt->exchange_rate,
                    'batch_id' => $batch?->id,
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $receipt->id,
                    'idempotency_key' => 'procurement-seeder:receipt-item:' . $item->id,
                    'note' => 'استلام مشتريات مرحّل — ' . $receipt->receipt_number,
                ];
            }
        }

        $postedReturns = PurchaseReturn::query()
            ->whereIn('id', collect($returns)->pluck('id'))
            ->where('status', 'posted')
            ->with('items')
            ->get();

        foreach ($postedReturns as $return) {
            foreach ($return->items as $item) {
                $events[] = [
                    'kind' => 'return',
                    'at' => $return->posted_at ?? $return->returned_at,
                    'location_id' => $return->location_id,
                    'product_id' => $item->product_id,
                    'quantity' => (float) $item->return_quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'base_unit_cost' => (float) $item->base_unit_cost,
                    'currency_id' => $return->currency_id,
                    'exchange_rate' => (float) $return->exchange_rate,
                    'batch_id' => $item->inventory_batch_id,
                    'reference_type' => PurchaseReturn::class,
                    'reference_id' => $return->id,
                    'idempotency_key' => 'procurement-seeder:return-item:' . $item->id,
                    'note' => 'مرتجع مشتريات مرحّل — ' . $return->return_number,
                ];
            }
        }

        usort($events, fn (array $a, array $b): int => Carbon::parse($a['at'])->timestamp <=> Carbon::parse($b['at'])->timestamp);

        // Reset only dedicated procurement demo product inventory at the locations
        // touched by this seeder. No customer-sale product stock is overwritten.
        $locationIds = collect([$context['factory'], $context['egyptBranch']])
            ->filter()
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        if ($locationIds !== []) {
            Inventory::query()
                ->whereIn('product_id', $productIds)
                ->whereIn('location_id', $locationIds)
                ->update([
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'damaged_quantity' => 0,
                    'in_transit_quantity' => 0,
                    'unit_cost' => 0,
                    'last_movement_at' => null,
                ]);
        }

        // Reset available quantities for seeder-created batches, then apply returns.
        InventoryBatch::query()
            ->whereIn('product_id', $productIds)
            ->whereHas('goodsReceiptItem.goodsReceipt', fn ($q) => $q->whereIn('id', collect($receipts)->pluck('id')))
            ->get()
            ->each(function (InventoryBatch $batch): void {
                $batch->update(['available_quantity' => $batch->received_quantity]);
            });

        if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'idempotency_key')) {
            $currentMovementKeys = collect($events)->pluck('idempotency_key')->values()->all();

            $staleSeederMovements = DB::table('stock_movements')
                ->where('idempotency_key', 'like', 'procurement-seeder:%');

            if ($currentMovementKeys !== []) {
                $staleSeederMovements->whereNotIn('idempotency_key', $currentMovementKeys);
            }

            $staleSeederMovements->delete();
        }

        $balances = [];
        $costLayers = [];

        foreach ($events as $event) {
            $key = $event['location_id'] . ':' . $event['product_id'];
            $before = (float) ($balances[$key] ?? 0);
            $delta = $event['kind'] === 'receipt' ? $event['quantity'] : -$event['quantity'];
            $after = max(0, round($before + $delta, 3));
            $balances[$key] = $after;

            if ($event['kind'] === 'receipt') {
                $costLayers[$key][] = [$event['quantity'], $event['base_unit_cost']];
            }

            if ($event['kind'] === 'return' && $event['batch_id']) {
                $batch = InventoryBatch::query()->find($event['batch_id']);
                if ($batch) {
                    $batch->update([
                        'available_quantity' => max(0, round((float) $batch->available_quantity - $event['quantity'], 3)),
                    ]);
                }
            }

            $inventory = Inventory::query()->firstOrCreate(
                ['location_id' => $event['location_id'], 'product_id' => $event['product_id']],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'damaged_quantity' => 0,
                    'in_transit_quantity' => 0,
                    'unit_cost' => 0,
                ],
            );

            $weightedBaseCost = $this->weightedCost($costLayers[$key] ?? []);
            $inventory->update([
                'quantity' => $after,
                'unit_cost' => $weightedBaseCost,
                'last_movement_at' => Carbon::parse($event['at']),
            ]);

            $this->upsertStockMovement(
                event: $event,
                balanceBefore: $before,
                balanceAfter: $after,
                actorId: $context['inventoryManager']->id,
            );
        }
    }

    /** @param array<int, array{0:float|int,1:float|int}> $layers */
    private function weightedCost(array $layers): float
    {
        $qty = 0.0;
        $value = 0.0;

        foreach ($layers as [$layerQty, $unitCost]) {
            $qty += (float) $layerQty;
            $value += (float) $layerQty * (float) $unitCost;
        }

        return $qty > 0 ? round($value / $qty, 4) : 0.0;
    }

    /** @param array<string, mixed> $event */
    private function upsertStockMovement(
        array $event,
        float $balanceBefore,
        float $balanceAfter,
        int $actorId,
    ): void {
        if (! Schema::hasTable('stock_movements') || ! Schema::hasColumn('stock_movements', 'idempotency_key')) {
            return;
        }

        $movementType = $this->resolveEnumBackedValue(
            '\\App\\Enums\\MovementType',
            $event['kind'] === 'receipt' ? ['in', 'IN'] : ['out', 'OUT'],
            $event['kind'] === 'receipt' ? 'in' : 'out',
        );

        $movementReason = $this->resolveEnumBackedValue(
            '\\App\\Enums\\MovementReason',
            $event['kind'] === 'receipt'
                ? ['purchase_receipt', 'goods_receipt', 'supplier_receipt', 'stock_receipt', 'transfer_receipt']
                : ['purchase_return', 'supplier_return', 'stock_return', 'sale_return', 'order_cancellation'],
            $event['kind'] === 'receipt' ? 'purchase_receipt' : 'purchase_return',
        );

        $row = [
            'location_id' => $event['location_id'],
            'product_id' => $event['product_id'],
            'movement_type' => $movementType,
            'reason' => $movementReason,
            'quantity' => round($event['quantity'], 3),
            'balance_before' => round($balanceBefore, 3),
            'balance_after' => round($balanceAfter, 3),
            'reference_type' => $event['reference_type'],
            'reference_id' => $event['reference_id'],
            'created_by' => $actorId,
            'note' => $event['note'],
            'created_at' => Carbon::parse($event['at']),
            'currency_id' => $event['currency_id'],
            'exchange_rate' => $event['exchange_rate'],
            'unit_cost' => $event['unit_cost'],
            'base_unit_cost' => $event['base_unit_cost'],
            'total_cost' => round($event['quantity'] * $event['unit_cost'], 2),
            'base_total_cost' => round($event['quantity'] * $event['base_unit_cost'], 2),
            'inventory_batch_id' => $event['batch_id'],
            'idempotency_key' => $event['idempotency_key'],
        ];

        $availableColumns = array_flip(Schema::getColumnListing('stock_movements'));
        $row = array_intersect_key($row, $availableColumns);

        DB::table('stock_movements')->updateOrInsert(
            ['idempotency_key' => $event['idempotency_key']],
            $row,
        );
    }

    /**
     * Resolve a value supported by an existing backed enum. This keeps this
     * seeder compatible with older projects where MovementReason naming differs.
     *
     * @param array<int, string> $candidates
     */
    private function resolveEnumBackedValue(
        string $enumClass,
        array $candidates,
        string $fallback,
    ): string {
        if (! enum_exists($enumClass)) {
            return $fallback;
        }

        $cases = $enumClass::cases();
        $values = [];

        foreach ($cases as $case) {
            $value = $case instanceof \BackedEnum ? (string) $case->value : (string) $case->name;
            $values[$this->normaliseEnumToken($value)] = $value;
            $values[$this->normaliseEnumToken($case->name)] = $value;
        }

        foreach ($candidates as $candidate) {
            $normalised = $this->normaliseEnumToken($candidate);
            if (isset($values[$normalised])) {
                return $values[$normalised];
            }
        }

        // Last-resort semantic matching, e.g. GoodsReceipt -> goods_receipt.
        foreach ($candidates as $candidate) {
            $needle = $this->normaliseEnumToken($candidate);
            foreach ($values as $normalised => $actual) {
                if (str_contains($normalised, $needle) || str_contains($needle, $normalised)) {
                    return $actual;
                }
            }
        }

        // The DB column is string-based. Keeping the procurement-specific value
        // is preferable to silently labelling a purchase as another business event.
        return $fallback;
    }

    private function normaliseEnumToken(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $value));
    }

    /**
     * @param array<string, SupplierProduct> $supplierProducts
     * @param array<string, PurchaseOrder> $orders
     * @param array<string, mixed> $context
     */
    private function seedPurchasePriceHistory(
        array $supplierProducts,
        array $orders,
        array $context,
    ): void {
        foreach ($orders as $order) {
            $order = $order->fresh(['items.product', 'supplier']);

            foreach ($order->items as $item) {
                $sp = SupplierProduct::query()
                    ->where('supplier_id', $order->supplier_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if (! $sp) {
                    continue;
                }

                SupplierProductPriceHistory::query()->updateOrCreate(
                    [
                        'supplier_product_id' => $sp->id,
                        'reference_type' => PurchaseOrder::class,
                        'reference_id' => $order->id,
                    ],
                    [
                        'purchase_price' => $item->unit_price,
                        'currency_id' => $order->currency_id,
                        'exchange_rate' => $order->exchange_rate,
                        'base_purchase_price' => $item->base_unit_cost,
                        'effective_at' => Carbon::parse($order->order_date)->setTime(10, 0),
                        'created_by' => $context['factoryManager']->id,
                    ],
                );
            }
        }
    }

    private function rateFor(Currency $currency, string $date): float
    {
        $base = Currency::query()->where('is_base', true)->first();
        if ($base && $currency->id === $base->id) {
            return 1.0;
        }

        $rate = ExchangeRate::query()
            ->where('currency_id', $currency->id)
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->value('rate_to_base');

        return $rate !== null ? (float) $rate : 1.0;
    }

    private function productSku(string $key): string
    {
        return match ($key) {
            'flour' => 'PRC-RM-FLOUR',
            'sugar' => 'PRC-RM-SUGAR',
            'butter' => 'PRC-RM-BUTTER',
            'cream' => 'PRC-RM-CREAM',
            'chocolate' => 'PRC-RM-CHOC',
            'pistachio' => 'PRC-RM-PISTACHIO',
            'cocoa' => 'PRC-RM-COCOA',
            'vanilla' => 'PRC-RM-VANILLA',
            'cake_box' => 'PRC-PK-CAKEBOX-M',
            'gift_box' => 'PRC-PK-GIFTBOX',
            default => throw new RuntimeException("Unknown procurement product key: {$key}"),
        };
    }
}