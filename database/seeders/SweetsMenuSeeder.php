<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Standalone sweets-only demo data.
 *
 * Run explicitly:
 * php artisan db:seed --class=Database\\Seeders\\SweetsMenuSeeder
 *
 * This seeder is intentionally NOT called by DatabaseSeeder. It is idempotent,
 * does not delete existing records, and never creates burger/savoury products.
 */
class SweetsMenuSeeder extends Seeder
{
    private const PRODUCT_PREFIX = 'SWT-';
    private const INGREDIENT_PREFIX = 'SWT-ING-';

    public function run(): void
    {
        $this->assertRequiredTables();
        $this->ensureSeedLocations();
        $this->ensureAssetDirectories();

        DB::transaction(function (): void {
            $actorId = DB::table('users')->orderBy('id')->value('id');
            $categories = $this->seedCategories();
            $ingredients = $this->seedIngredients($categories['ingredients']);
            $products = $this->seedProducts($categories);

            $this->seedLocationStock($products, $ingredients, $actorId);
            $this->seedRecipes($products, $ingredients, $actorId);
            $this->seedMenusAndKitchenRoutes($products, $actorId);
            $this->seedAdvertisements();
            $this->seedMenuSettings();
            $this->validateSeed($products);
        });

        $this->printSummary();
    }

    private function assertRequiredTables(): void
    {
        $required = [
            'categories', 'products', 'locations', 'location_products',
            'inventories', 'restaurant_menu_items', 'recipes', 'recipe_items',
        ];

        $missing = collect($required)
            ->reject(fn (string $table): bool => Schema::hasTable($table))
            ->values()
            ->all();

        if ($missing !== []) {
            throw new RuntimeException(
                'شغّل migrations أولاً. الجداول المفقودة: '.implode(', ', $missing)
            );
        }
    }

    private function ensureSeedLocations(): void
    {
        $activeBranches = DB::table('locations')
            ->where('type', 'branch')
            ->where('is_active', true)
            ->count();

        if ($activeBranches === 0) {
            $this->upsert('locations', ['code' => 'SWT-B01'], [
                'name' => 'فرع الحلويات التجريبي',
                'type' => 'branch',
                'phone' => '0599000100',
                'address' => 'الفرع التجريبي للحلويات',
                'is_active' => true,
            ]);
        }

        $activeFactories = DB::table('locations')
            ->where('type', 'factory')
            ->where('is_active', true)
            ->count();

        if ($activeFactories === 0) {
            $this->upsert('locations', ['code' => 'SWT-FAC'], [
                'name' => 'مصنع الحلويات التجريبي',
                'type' => 'factory',
                'phone' => '0599000101',
                'address' => 'مصنع إنتاج الحلويات',
                'is_active' => true,
            ]);
        }
    }

    /** @return array<string, int> */
    private function seedCategories(): array
    {
        $definitions = [
            'cakes' => ['Cakes', 'كيك وتورت', 'cake', '#B0003A', 10],
            'oriental-sweets' => ['Oriental Sweets', 'حلويات شرقية', 'baklava', '#C88716', 20],
            'western-sweets' => ['Western Sweets', 'حلويات غربية', 'cookie', '#8B4A6B', 30],
            'chocolate' => ['Chocolate', 'شوكولاتة', 'chocolate', '#5A2E24', 40],
            'milkshakes' => ['Milkshakes', 'ميلك شيك', 'milkshake', '#B64A75', 50],
            'drinks' => ['Dessert Drinks', 'مشروبات حلوة', 'cup', '#8A5A34', 60],
            'sweets-ingredients' => ['Sweets Ingredients', 'مواد خام للحلويات', 'boxes', '#6B7280', 900],
        ];

        $ids = [];
        foreach ($definitions as $slug => [$name, $nameAr, $icon, $color, $sort]) {
            $this->upsert('categories', ['slug' => $slug], [
                'name' => $name,
                'name_ar' => $nameAr,
                'description' => $slug === 'sweets-ingredients'
                    ? 'مواد خام داخلية غير معروضة في منيو الزبون.'
                    : 'تشكيلة حلويات ومشروبات طازجة.',
                'icon_key' => $icon,
                'icon_color' => $color,
                'is_active' => true,
                'sort_order' => $sort,
            ]);
            $ids[$slug === 'sweets-ingredients' ? 'ingredients' : $slug] =
                (int) DB::table('categories')->where('slug', $slug)->value('id');
        }

        return $ids;
    }

    /** @return Collection<string, object> */
    private function seedIngredients(int $categoryId): Collection
    {
        $definitions = [
            ['FLOUR', 'Pastry Flour', 'طحين حلويات', 'kg', 2.20],
            ['SUGAR', 'Fine Sugar', 'سكر ناعم', 'kg', 2.60],
            ['SEMOLINA', 'Fine Semolina', 'سميد ناعم', 'kg', 3.00],
            ['BUTTER', 'Unsalted Butter', 'زبدة غير مملحة', 'kg', 18.00],
            ['EGGS', 'Fresh Eggs', 'بيض طازج', 'piece', 0.90],
            ['MILK', 'Fresh Milk', 'حليب طازج', 'liter', 5.00],
            ['CREAM', 'Whipping Cream', 'كريمة خفق', 'liter', 16.00],
            ['CHEESE', 'Kunafa Cheese', 'جبنة كنافة', 'kg', 22.00],
            ['CHOCOLATE', 'Belgian Chocolate', 'شوكولاتة بلجيكية', 'kg', 34.00],
            ['COCOA', 'Cocoa Powder', 'بودرة كاكاو', 'kg', 24.00],
            ['PISTACHIO', 'Pistachio', 'فستق حلبي', 'kg', 70.00],
            ['WALNUT', 'Walnut', 'جوز', 'kg', 42.00],
            ['DATES', 'Date Paste', 'عجوة تمر', 'kg', 14.00],
            ['VANILLA', 'Vanilla', 'فانيلا', 'kg', 45.00],
            ['STRAWBERRY', 'Strawberry Puree', 'بيوريه فراولة', 'kg', 18.00],
            ['COFFEE', 'Coffee Beans', 'حبوب قهوة', 'kg', 35.00],
            ['OREO', 'Oreo Crumbs', 'فتات أوريو', 'kg', 20.00],
            ['LOTUS', 'Lotus Spread', 'كريمة لوتس', 'kg', 28.00],
        ];

        foreach ($definitions as $index => [$code, $name, $nameAr, $unit, $cost]) {
            $sku = self::INGREDIENT_PREFIX.$code;
            $this->upsert('products', ['sku' => $sku], [
                'barcode' => sprintf('6291198%05d', $index + 1),
                'category_id' => $categoryId,
                'name' => $name,
                'name_ar' => $nameAr,
                'description' => 'مادة خام مخصصة لوصفات الحلويات.',
                'unit' => $unit,
                'product_type' => 'standard',
                'base_selling_price' => 0,
                'is_active' => true,
                'tracks_batch' => true,
                'tracks_expiry' => true,
                'deleted_at' => null,
            ]);
        }

        return DB::table('products')
            ->where('sku', 'like', self::INGREDIENT_PREFIX.'%')
            ->get()
            ->keyBy(fn (object $product): string => substr($product->sku, strlen(self::INGREDIENT_PREFIX)));
    }

    /** @return Collection<string, object> */
    private function seedProducts(array $categories): Collection
    {
        $catalog = [
            'cakes' => [
                ['CAKE-CHOC', 'Chocolate Celebration Cake', 'تورتة شوكولاتة فاخرة', 'piece', 85, 'غنية بالشوكولاتة البلجيكية والكريمة.', 35],
                ['CAKE-RED', 'Red Velvet Cake', 'تورتة ريد فيلفت', 'piece', 95, 'طبقات ريد فيلفت مع كريمة الجبن.', 40],
                ['CAKE-PIST', 'Pistachio Cake', 'تورتة فستق', 'piece', 110, 'كيك فستق بكريمة ناعمة ولمسة ذهبية.', 45],
                ['CAKE-LOTUS', 'Lotus Cake', 'تورتة لوتس', 'piece', 100, 'كيك إسفنجي مع كريمة وفتات اللوتس.', 40],
                ['CAKE-CHEESE', 'Berry Cheesecake', 'تشيز كيك التوت', 'piece', 90, 'تشيز كيك كريمي مع صوص الفراولة.', 30],
            ],
            'oriental-sweets' => [
                ['ORI-BAKLAVA', 'Mixed Baklava', 'بقلاوة مشكلة', 'kg', 60, 'بقلاوة مشكلة محشوة بالفستق والجوز.', 18],
                ['ORI-KUNAFA', 'Cheese Kunafa', 'كنافة بالجبنة', 'kg', 45, 'كنافة ساخنة بجبنة طرية وقطر خفيف.', 20],
                ['ORI-WARBAT', 'Cream Warbat', 'وربات بالقشطة', 'kg', 58, 'رقائق هشة محشوة بالقشطة والفستق.', 22],
                ['ORI-MAAMOUL', 'Date Maamoul', 'معمول تمر', 'kg', 55, 'معمول بالزبدة ومحشو بعجوة التمر.', 16],
                ['ORI-HARISSA', 'Almond Harissa', 'هريسة لوز', 'tray', 42, 'هريسة سميد طرية مزينة باللوز.', 24],
                ['ORI-BASBOUSA', 'Cream Basbousa', 'بسبوسة بالقشطة', 'tray', 48, 'بسبوسة ذهبية محشوة بالقشطة.', 24],
                ['ORI-BURMA', 'Pistachio Burma', 'برمة فستق', 'kg', 82, 'عجينة كنافة محشوة بالفستق الحلبي.', 25],
            ],
            'western-sweets' => [
                ['WES-BROWNIE', 'Fudge Brownie', 'براوني فادج', 'piece', 9, 'براوني كثيف بالشوكولاتة والكاكاو.', 12],
                ['WES-CINNAMON', 'Cinnamon Roll', 'رول القرفة', 'piece', 8, 'عجينة طرية بالقرفة وصوص الفانيلا.', 18],
                ['WES-CROISSANT', 'Butter Croissant', 'كرواسان زبدة', 'piece', 7, 'طبقات هشة مخبوزة بالزبدة.', 18],
                ['WES-TIRAMISU', 'Tiramisu Cup', 'كوب تيراميسو', 'cup', 16, 'كريمة خفيفة وقهوة وكاكاو.', 10],
                ['WES-MOLTEN', 'Molten Chocolate Cake', 'مولتن كيك شوكولاتة', 'piece', 20, 'كيك دافئ بقلب شوكولاتة سائل.', 16],
                ['WES-MACARON', 'Macaron Box', 'علبة ماكرون', 'box', 48, 'تشكيلة ماكرون ملونة بنكهات موسمية.', 25],
            ],
            'chocolate' => [
                ['CHO-BOX', 'Dahab Chocolate Box', 'علبة شوكولاتة دهب', 'box', 55, 'تشكيلة شوكولاتة بلجيكية بحشوات فاخرة.', 15],
                ['CHO-TRUFFLE', 'Pistachio Truffle Box', 'علبة ترافل فستق', 'box', 68, 'ترافل شوكولاتة محشو بكريمة الفستق.', 18],
            ],
            'milkshakes' => [
                ['MS-CHOC', 'Chocolate Milkshake', 'ميلك شيك شوكولاتة', 'cup', 16, 'حليب وكريمة وشوكولاتة بلجيكية.', 8],
                ['MS-STRAW', 'Strawberry Milkshake', 'ميلك شيك فراولة', 'cup', 16, 'فراولة طبيعية وحليب بارد.', 8],
                ['MS-OREO', 'Oreo Milkshake', 'ميلك شيك أوريو', 'cup', 18, 'أوريو وكريمة وحليب بارد.', 8],
                ['MS-LOTUS', 'Lotus Milkshake', 'ميلك شيك لوتس', 'cup', 18, 'لوتس كريمي مع الحليب والكريمة.', 8],
            ],
            'drinks' => [
                ['DR-HOT-CHOC', 'Hot Chocolate', 'شوكولاتة ساخنة', 'cup', 12, 'شوكولاتة ساخنة غنية بالكريمة.', 7],
                ['DR-ICED-COFFEE', 'Iced Coffee', 'قهوة مثلجة', 'cup', 13, 'قهوة باردة مع الحليب والفانيلا.', 6],
            ],
        ];

        $sequence = 100;
        foreach ($catalog as $categorySlug => $items) {
            foreach ($items as $index => [$code, $name, $nameAr, $unit, $price, $description, $prep]) {
                $sku = self::PRODUCT_PREFIX.$code;
                $image = $this->createProductImage($sku, $nameAr, $categorySlug, $index);
                $this->upsert('products', ['sku' => $sku], [
                    'barcode' => sprintf('6291199%05d', $sequence++),
                    'category_id' => $categories[$categorySlug],
                    'name' => $name,
                    'name_ar' => $nameAr,
                    'description' => $description,
                    'description_ar' => $description,
                    'image' => $image,
                    'image_path' => $image,
                    'image_url' => '/'.$image,
                    'prep_time_minutes' => $prep,
                    'unit' => $unit,
                    'product_type' => 'standard',
                    'base_selling_price' => $price,
                    'is_active' => true,
                    'tracks_batch' => false,
                    'tracks_expiry' => true,
                    'deleted_at' => null,
                ]);
            }
        }

        return DB::table('products')
            ->where('sku', 'like', self::PRODUCT_PREFIX.'%')
            ->where('sku', 'not like', self::INGREDIENT_PREFIX.'%')
            ->get()
            ->keyBy('sku');
    }

    private function seedLocationStock(Collection $products, Collection $ingredients, ?int $actorId): void
    {
        $locations = DB::table('locations')->where('is_active', true)->orderBy('id')->get();

        foreach ($locations as $locationIndex => $location) {
            foreach ($products->values() as $index => $product) {
                $quantity = 18 + (($index * 7 + $locationIndex * 3) % 35);
                $price = (float) $product->base_selling_price;
                $this->upsert('location_products', [
                    'location_id' => $location->id,
                    'product_id' => $product->id,
                ], [
                    'is_available' => true,
                    'local_selling_price' => $price,
                    'minimum_stock_level' => 5,
                ]);
                $this->upsertInventory((int) $location->id, (int) $product->id, $quantity, max(1, $price * .42));
                $this->seedOpeningMovement((int) $location->id, $product, $quantity, $actorId);
            }

            foreach ($ingredients->values() as $index => $ingredient) {
                $quantity = 55 + (($index * 11 + $locationIndex * 5) % 75);
                $this->upsertInventory((int) $location->id, (int) $ingredient->id, $quantity, 2 + $index);
                $this->seedOpeningMovement((int) $location->id, $ingredient, $quantity, $actorId);
            }
        }
    }

    private function upsertInventory(int $locationId, int $productId, float $quantity, float $unitCost): void
    {
        $this->upsert('inventories', [
            'location_id' => $locationId,
            'product_id' => $productId,
        ], [
            'quantity' => $quantity,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'in_transit_quantity' => 0,
            'unit_cost' => round($unitCost, 4),
            'last_movement_at' => now(),
        ]);
    }

    private function seedOpeningMovement(int $locationId, object $product, float $quantity, ?int $actorId): void
    {
        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        $this->upsert('stock_movements', [
            'location_id' => $locationId,
            'product_id' => $product->id,
            'reference_type' => 'sweets_menu_seeder',
            'reference_id' => $product->id,
        ], [
            'movement_type' => 'in',
            'reason' => 'opening_stock',
            'quantity' => $quantity,
            'balance_before' => 0,
            'balance_after' => $quantity,
            'created_by' => $actorId,
            'note' => 'رصيد افتتاحي أنشأه Seeder الحلويات المستقل.',
        ]);
    }

    private function seedRecipes(Collection $products, Collection $ingredients, ?int $actorId): void
    {
        $categoryRecipes = [
            'cakes' => ['FLOUR' => .25, 'SUGAR' => .18, 'BUTTER' => .12, 'EGGS' => 4, 'MILK' => .15, 'CREAM' => .20],
            'oriental-sweets' => ['SEMOLINA' => .30, 'SUGAR' => .18, 'BUTTER' => .12, 'CREAM' => .10, 'PISTACHIO' => .06],
            'western-sweets' => ['FLOUR' => .22, 'SUGAR' => .12, 'BUTTER' => .14, 'EGGS' => 2, 'CREAM' => .12, 'VANILLA' => .004],
            'chocolate' => ['CHOCOLATE' => .30, 'CREAM' => .12, 'BUTTER' => .05, 'PISTACHIO' => .05],
            'milkshakes' => ['MILK' => .35, 'CREAM' => .08, 'SUGAR' => .025, 'VANILLA' => .003],
            'drinks' => ['MILK' => .30, 'SUGAR' => .025, 'CREAM' => .04],
        ];
        $flavours = [
            'CAKE-CHOC' => ['CHOCOLATE' => .18, 'COCOA' => .03],
            'CAKE-RED' => ['COCOA' => .02, 'CHEESE' => .12],
            'CAKE-PIST' => ['PISTACHIO' => .15],
            'CAKE-LOTUS' => ['LOTUS' => .16],
            'CAKE-CHEESE' => ['CHEESE' => .25, 'STRAWBERRY' => .10],
            'ORI-BAKLAVA' => ['PISTACHIO' => .12, 'WALNUT' => .12],
            'ORI-KUNAFA' => ['CHEESE' => .35],
            'ORI-WARBAT' => ['CREAM' => .30, 'PISTACHIO' => .06],
            'ORI-MAAMOUL' => ['DATES' => .28],
            'ORI-HARISSA' => ['SEMOLINA' => .18],
            'ORI-BASBOUSA' => ['CREAM' => .25],
            'ORI-BURMA' => ['PISTACHIO' => .25],
            'WES-BROWNIE' => ['CHOCOLATE' => .14, 'COCOA' => .04],
            'WES-TIRAMISU' => ['COFFEE' => .02, 'COCOA' => .01],
            'WES-MOLTEN' => ['CHOCOLATE' => .18],
            'CHO-BOX' => ['CHOCOLATE' => .25],
            'CHO-TRUFFLE' => ['CHOCOLATE' => .20, 'PISTACHIO' => .12],
            'MS-CHOC' => ['CHOCOLATE' => .08],
            'MS-STRAW' => ['STRAWBERRY' => .12],
            'MS-OREO' => ['OREO' => .10],
            'MS-LOTUS' => ['LOTUS' => .10],
            'DR-HOT-CHOC' => ['CHOCOLATE' => .08, 'COCOA' => .01],
            'DR-ICED-COFFEE' => ['COFFEE' => .02, 'VANILLA' => .002],
        ];

        $categoryById = DB::table('categories')->pluck('slug', 'id');

        foreach ($products as $product) {
            $shortCode = substr($product->sku, strlen(self::PRODUCT_PREFIX));
            $categorySlug = $categoryById[$product->category_id] ?? 'western-sweets';
            $formula = array_replace($categoryRecipes[$categorySlug] ?? $categoryRecipes['western-sweets'], $flavours[$shortCode] ?? []);
            $recipeCode = 'SWT-RCP-'.$shortCode;

            $this->upsert('recipes', ['code' => $recipeCode], [
                'product_id' => $product->id,
                'product_variant_id' => null,
                'name' => 'وصفة '.$product->name_ar,
                'version' => 1,
                'yield_quantity' => in_array($product->unit, ['kg', 'tray', 'box'], true) ? 1 : 10,
                'labor_cost_per_batch' => 18,
                'overhead_percent' => 8,
                'status' => 'approved',
                'is_active' => true,
                'notes' => 'وصفة معتمدة أنشأها Seeder الحلويات المستقل.',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'activated_by' => $actorId,
                'activated_at' => now(),
                'deleted_at' => null,
            ]);

            $recipeId = (int) DB::table('recipes')->where('code', $recipeCode)->value('id');
            $sort = 10;
            foreach ($formula as $ingredientCode => $quantity) {
                $ingredient = $ingredients->get($ingredientCode);
                if (! $ingredient) {
                    throw new RuntimeException("مادة الوصفة غير موجودة: {$ingredientCode}");
                }
                $this->upsert('recipe_items', [
                    'recipe_id' => $recipeId,
                    'ingredient_product_id' => $ingredient->id,
                ], [
                    'quantity' => $quantity,
                    'waste_percent' => 3,
                    'expected_waste_percent' => 3,
                    'unit_snapshot' => $ingredient->unit,
                    'stage' => $categorySlug === 'milkshakes' || $categorySlug === 'drinks' ? 'mixing' : 'preparation',
                    'estimated_unit_cost' => 4,
                    'is_optional' => false,
                    'notes' => 'مكوّن أساسي.',
                    'sort_order' => $sort,
                ]);
                $sort += 10;
            }
        }
    }

    private function seedMenusAndKitchenRoutes(Collection $products, ?int $actorId): void
    {
        $branches = DB::table('locations')
            ->where('type', 'branch')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($branches as $branch) {
            $stationId = $this->resolveSweetsStation((int) $branch->id, $actorId);

            foreach ($products->values() as $index => $product) {
                $this->upsert('restaurant_menu_items', [
                    'location_id' => $branch->id,
                    'product_id' => $product->id,
                ], [
                    'display_name' => $product->name,
                    'display_name_ar' => $product->name_ar,
                    'description' => $product->description,
                    'image' => $product->image ?? null,
                    'image_path' => $product->image ?? null,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'show_in_pos' => true,
                    'show_in_qr' => true,
                    'show_in_delivery' => true,
                    'is_featured' => $index < 6,
                    'created_by' => $actorId,
                ]);

                if ($stationId && Schema::hasTable('kitchen_product_routes')) {
                    $this->upsert('kitchen_product_routes', [
                        'location_id' => $branch->id,
                        'product_id' => $product->id,
                    ], [
                        'kitchen_station_id' => $stationId,
                    ]);
                }
            }
        }
    }

    private function resolveSweetsStation(int $locationId, ?int $actorId): ?int
    {
        if (! Schema::hasTable('kitchen_stations')) {
            return null;
        }

        $existing = DB::table('kitchen_stations')
            ->where('location_id', $locationId)
            ->where('code', 'SWEETS')
            ->value('id');
        if ($existing) {
            return (int) $existing;
        }

        if ($actorId === null && Schema::hasColumn('kitchen_stations', 'created_by')) {
            return DB::table('kitchen_stations')
                ->where('location_id', $locationId)
                ->orderByDesc('is_default')
                ->value('id');
        }

        $this->upsert('kitchen_stations', [
            'location_id' => $locationId,
            'code' => 'SWEETS',
        ], [
            'name' => 'محطة الحلويات والمشروبات',
            'description' => 'تحضير الكيك والحلويات الشرقية والغربية والميلك شيك.',
            'target_minutes' => 20,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 20,
            'created_by' => $actorId,
        ]);

        return (int) DB::table('kitchen_stations')
            ->where('location_id', $locationId)
            ->where('code', 'SWEETS')
            ->value('id');
    }

    private function seedAdvertisements(): void
    {
        if (! Schema::hasTable('menu_banners')) {
            return;
        }

        $banners = [
            ['SWT-BANNER-01', 'مذاق استثنائي', 'تشكيلة الحلويات الشرقية الفاخرة وصلت الآن', 'عرض اليوم', '#3A150D', '#D8A31A'],
            ['SWT-BANNER-02', 'كيك يستحق الاحتفال', 'اختر تورتتك المفضلة وحضّرها بطريقتك', 'الأكثر طلباً', '#310D20', '#D8124B'],
            ['SWT-BANNER-03', 'ميلك شيك بارد ومنعش', 'شوكولاتة، فراولة، أوريو ولوتس', 'جديد', '#301839', '#EF87AE'],
        ];

        foreach ($banners as $index => [$code, $title, $subtitle, $badge, $background, $accent]) {
            $image = $this->createBannerImage($code, $title, $subtitle, $background, $accent);
            $this->upsert('menu_banners', [
                'location_id' => null,
                'title' => $title,
            ], [
                'location_id' => null,
                'image' => $image,
                'title' => $title,
                'subtitle' => $subtitle,
                'badge_text' => $badge,
                'link_url' => '#menu-products',
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addYears(2),
            ]);
        }
    }

    private function seedMenuSettings(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $settings = [
            ['customer_menu_enabled', '1', 'boolean', 'customer-menu', 'تفعيل منيو الزبون'],
            ['customer_menu_title', 'أهلاً بك', 'string', 'customer-menu', 'عنوان المنيو'],
            ['customer_menu_subtitle', 'شو بتحب تأكل اليوم؟', 'string', 'customer-menu', 'وصف المنيو'],
            ['customer_menu_primary_color', '#B0003A', 'string', 'customer-menu', 'اللون الرئيسي'],
            ['customer_menu_accent_color', '#D8124B', 'string', 'customer-menu', 'اللون المساند'],
            ['customer_menu_background_color', '#FBFAF8', 'string', 'customer-menu', 'لون الخلفية'],
            ['customer_menu_show_hero', '1', 'boolean', 'customer-menu', 'عرض الإعلانات'],
            ['customer_menu_show_search', '1', 'boolean', 'customer-menu', 'عرض البحث'],
            ['customer_menu_show_categories', '1', 'boolean', 'customer-menu', 'عرض التصنيفات'],
            ['customer_menu_show_descriptions', '1', 'boolean', 'customer-menu', 'عرض وصف المنتجات'],
            ['customer_menu_show_featured', '1', 'boolean', 'customer-menu', 'عرض الأكثر طلباً'],
            ['customer_menu_featured_title', 'الأكثر طلباً', 'string', 'customer-menu', 'عنوان المنتجات المميزة'],
            ['customer_menu_featured_limit', '6', 'integer', 'customer-menu', 'عدد المنتجات المميزة'],
            ['customer_menu_card_image_ratio', '4-3', 'string', 'customer-menu', 'نسبة صورة المنتج'],
            ['customer_menu_image_fit', 'cover', 'string', 'customer-menu', 'ملاءمة الصور'],
            ['customer_menu_allow_takeaway', '1', 'boolean', 'customer-menu', 'تفعيل الاستلام'],
            ['customer_menu_allow_dine_in', '1', 'boolean', 'customer-menu', 'تفعيل الطلب داخل الفرع'],
        ];

        foreach ($settings as [$key, $value, $type, $group, $label]) {
            $this->upsert('system_settings', ['key' => $key], compact('value', 'type', 'group', 'label'));
            Cache::forget("setting:{$key}");
        }
    }

    private function validateSeed(Collection $products): void
    {
        $productIds = $products->pluck('id');
        $branchCount = DB::table('locations')->where('type', 'branch')->where('is_active', true)->count();
        $recipeCount = DB::table('recipes')->whereIn('product_id', $productIds)->where('status', 'approved')->count();
        $menuCount = DB::table('restaurant_menu_items')->whereIn('product_id', $productIds)->where('is_active', true)->count();
        $inventoryCount = DB::table('inventories')->whereIn('product_id', $productIds)->count();

        if ($products->isEmpty()) {
            throw new RuntimeException('لم يتم إنشاء أي منتج حلويات.');
        }
        if ($recipeCount !== $products->count()) {
            throw new RuntimeException("الوصفات غير مكتملة: {$recipeCount}/{$products->count()}");
        }
        if ($menuCount < $products->count() * $branchCount) {
            throw new RuntimeException('عناصر منيو الحلويات غير مكتملة لكل الفروع.');
        }
        if ($inventoryCount < $products->count() * $branchCount) {
            throw new RuntimeException('أرصدة مخزون الحلويات غير مكتملة.');
        }
        if ($products->contains(fn (object $product): bool => str_contains(strtolower($product->name), 'burger'))) {
            throw new RuntimeException('Seeder الحلويات يحتوي منتج برغر بالخطأ.');
        }
    }

    private function ensureAssetDirectories(): void
    {
        foreach ([public_path('images/sweets-menu/products'), public_path('images/sweets-menu/banners')] as $directory) {
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException("تعذر إنشاء مجلد الصور: {$directory}");
            }
        }
    }

    private function createProductImage(string $sku, string $nameAr, string $category, int $index): string
    {
        $palettes = [
            'cakes' => ['#2A0B14', '#D8124B'],
            'oriental-sweets' => ['#3B210A', '#E0AA2B'],
            'western-sweets' => ['#3B162B', '#D87AA6'],
            'chocolate' => ['#24100B', '#C88716'],
            'milkshakes' => ['#321637', '#F08AB5'],
            'drinks' => ['#2D1C12', '#D9A35F'],
        ];
        [$dark, $accent] = $palettes[$category] ?? ['#1F2937', '#D8A31A'];
        $safeName = htmlspecialchars($nameAr, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $safeSku = preg_replace('/[^A-Za-z0-9_-]/', '-', $sku);
        $relative = 'images/sweets-menu/products/'.$safeSku.'.svg';
        $circle = 170 + (($index % 3) * 28);
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="900" viewBox="0 0 1200 900">
 <defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop stop-color="{$dark}"/><stop offset="1" stop-color="#080605"/></linearGradient><radialGradient id="glow"><stop stop-color="{$accent}" stop-opacity=".45"/><stop offset="1" stop-color="{$accent}" stop-opacity="0"/></radialGradient></defs>
 <rect width="1200" height="900" rx="54" fill="url(#bg)"/><circle cx="780" cy="350" r="340" fill="url(#glow)"/>
 <ellipse cx="775" cy="640" rx="330" ry="70" fill="#000" opacity=".35"/><circle cx="775" cy="420" r="{$circle}" fill="{$accent}" opacity=".92"/>
 <path d="M610 455 Q775 300 940 455 L900 610 Q775 690 650 610Z" fill="#fff" opacity=".90"/><path d="M635 480 Q775 365 915 480" fill="none" stroke="{$accent}" stroke-width="22"/>
 <text x="90" y="190" fill="{$accent}" font-size="48" font-weight="800">حلويات دهب</text><text x="90" y="300" fill="#fff" font-size="66" font-weight="900" direction="rtl">{$safeName}</text>
 <text x="90" y="750" fill="#fff" opacity=".72" font-size="32">Freshly prepared • {$safeSku}</text>
</svg>
SVG;
        $this->writeAsset(public_path($relative), $svg);

        return $relative;
    }

    private function createBannerImage(string $code, string $title, string $subtitle, string $dark, string $accent): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $safeSubtitle = htmlspecialchars($subtitle, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $relative = 'images/sweets-menu/banners/'.strtolower($code).'.svg';
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1800" height="650" viewBox="0 0 1800 650">
 <defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="0"><stop stop-color="#090505"/><stop offset="1" stop-color="{$dark}"/></linearGradient><radialGradient id="g"><stop stop-color="{$accent}" stop-opacity=".55"/><stop offset="1" stop-color="{$accent}" stop-opacity="0"/></radialGradient></defs>
 <rect width="1800" height="650" rx="45" fill="url(#bg)"/><circle cx="390" cy="300" r="360" fill="url(#g)"/><circle cx="330" cy="320" r="190" fill="{$accent}" opacity=".9"/>
 <path d="M155 350 Q330 180 505 350 L470 500 Q330 565 190 500Z" fill="#fff" opacity=".9"/><path d="M190 365 Q330 245 470 365" fill="none" stroke="{$accent}" stroke-width="24"/>
 <text x="1670" y="235" text-anchor="end" fill="{$accent}" font-size="48" font-weight="800" direction="rtl">مذاق استثنائي</text>
 <text x="1670" y="355" text-anchor="end" fill="#fff" font-size="82" font-weight="900" direction="rtl">{$safeTitle}</text>
 <text x="1670" y="445" text-anchor="end" fill="#fff" opacity=".82" font-size="36" direction="rtl">{$safeSubtitle}</text>
</svg>
SVG;
        $this->writeAsset(public_path($relative), $svg);

        return $relative;
    }

    private function writeAsset(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("تعذر حفظ الصورة: {$path}");
        }
    }

    private function upsert(string $table, array $unique, array $values): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing($table));
        $unique = array_intersect_key($unique, $columns);
        $values = array_intersect_key($values, $columns);

        if ($unique === []) {
            throw new RuntimeException("لا يوجد مفتاح صالح لزرع جدول {$table}.");
        }
        if (isset($columns['created_at']) && ! array_key_exists('created_at', $values)) {
            $values['created_at'] = now();
        }
        if (isset($columns['updated_at'])) {
            $values['updated_at'] = now();
        }

        DB::table($table)->updateOrInsert($unique, $values);
    }

    private function printSummary(): void
    {
        $productIds = DB::table('products')
            ->where('sku', 'like', self::PRODUCT_PREFIX.'%')
            ->where('sku', 'not like', self::INGREDIENT_PREFIX.'%')
            ->pluck('id');

        $rows = [
            ['منتجات الحلويات', $productIds->count()],
            ['مواد الوصفات', DB::table('products')->where('sku', 'like', self::INGREDIENT_PREFIX.'%')->count()],
            ['الوصفات المعتمدة', DB::table('recipes')->whereIn('product_id', $productIds)->where('status', 'approved')->count()],
            ['عناصر الوصفات', DB::table('recipe_items')->whereIn('recipe_id', DB::table('recipes')->whereIn('product_id', $productIds)->pluck('id'))->count()],
            ['عناصر المنيو', DB::table('restaurant_menu_items')->whereIn('product_id', $productIds)->count()],
            ['أرصدة المخزون', DB::table('inventories')->whereIn('product_id', $productIds)->count()],
            ['الإعلانات', Schema::hasTable('menu_banners') ? DB::table('menu_banners')->where('image', 'like', 'images/sweets-menu/banners/%')->count() : 0],
            ['صور المنتجات', count(glob(public_path('images/sweets-menu/products/*.svg')) ?: [])],
        ];

        $this->command?->info('تم زرع منيو الحلويات فقط بنجاح، بدون أي بيانات برغر.');
        $this->command?->table(['البيانات', 'العدد'], $rows);
    }
}
