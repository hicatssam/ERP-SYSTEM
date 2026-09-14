<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RestaurantSmashMenuSeeder extends Seeder
{
    /**
     * Seeds ONLY restaurant catalog/menu demo data:
     * - categories
     * - products
     * - per-branch product availability/pricing
     * - per-branch restaurant menu items
     *
     * It does NOT create inventory quantities, recipes, orders, payments,
     * accounting entries, users, or operational transactions.
     */
    public function run(): void
    {
        $now = now();

        $categories = [
            ['key' => 'smash',       'name' => 'Smash Burgers',   'name_ar' => 'سماش برجر',       'icon' => 'burger',       'sort' => 10],
            ['key' => 'burger',      'name' => 'Burgers',         'name_ar' => 'برجر',             'icon' => 'burger',       'sort' => 20],
            ['key' => 'chicken',     'name' => 'Chicken Burgers', 'name_ar' => 'برجر دجاج',        'icon' => 'chicken',      'sort' => 30],
            ['key' => 'appetizers',  'name' => 'Appetizers',      'name_ar' => 'مقبلات',           'icon' => 'fries',        'sort' => 40],
            ['key' => 'fries',       'name' => 'Fries',           'name_ar' => 'بطاطا',            'icon' => 'fries',        'sort' => 50],
            ['key' => 'salads',      'name' => 'Salads',          'name_ar' => 'سلطات',            'icon' => 'salad',        'sort' => 60],
            ['key' => 'sauces',      'name' => 'Sauces',          'name_ar' => 'صوصات',            'icon' => 'sparkles',     'sort' => 70],
            ['key' => 'drinks',      'name' => 'Soft Drinks',     'name_ar' => 'مشروبات غازية',    'icon' => 'drink',        'sort' => 80],
            ['key' => 'juices',      'name' => 'Fresh Juices',    'name_ar' => 'عصائر طبيعية',     'icon' => 'juice',        'sort' => 90],
            ['key' => 'milkshakes',  'name' => 'Milkshakes',      'name_ar' => 'ميلك شيك',          'icon' => 'drink',        'sort' => 100],
            ['key' => 'desserts',    'name' => 'Desserts',        'name_ar' => 'حلويات',           'icon' => 'dessert',      'sort' => 110],
            ['key' => 'kids',        'name' => 'Kids Meals',      'name_ar' => 'وجبات أطفال',      'icon' => 'gift',         'sort' => 120],
        ];

        $categoryIds = [];

        foreach ($categories as $category) {
            $model = Category::query()->updateOrCreate(
                ['slug' => 'menu-' . $category['key']],
                [
                    'name' => $category['name'],
                    'name_ar' => $category['name_ar'],
                    'description' => 'تصنيف منيو المطعم - ' . $category['name_ar'],
                    'is_active' => true,
                    'sort_order' => $category['sort'],
                ]
            );

            // The visual category fields were added later in some installations,
            // therefore update them only when those columns exist.
            $extra = [];
            if (Schema::hasColumn('categories', 'icon_key')) {
                $extra['icon_key'] = $category['icon'];
            }
            if (Schema::hasColumn('categories', 'icon_color')) {
                $extra['icon_color'] = '#9F1239';
            }
            if ($extra !== []) {
                DB::table('categories')->where('id', $model->id)->update($extra);
            }

            $categoryIds[$category['key']] = (int) $model->id;
        }

        $products = [
            // Smash burgers
            ['smash', 'SMASH-001', 'Classic Smash', 'كلاسيك سماش', 'لحم سماش، جبنة أمريكية، مخلل، بصل وصوص البيت.', 24.00, 10],
            ['smash', 'SMASH-002', 'Double Smash', 'دبل سماش', 'قطعتان لحم سماش، جبنة مضاعفة، مخلل، بصل وصوص البيت.', 32.00, 12],
            ['smash', 'SMASH-003', 'Triple Smash', 'تربل سماش', 'ثلاث قطع لحم سماش مع جبنة وصوص البيت.', 39.00, 14],
            ['smash', 'SMASH-004', 'Mushroom Smash', 'مشروم سماش', 'سماش بيف، مشروم سوتيه، جبنة وصوص كريمي.', 30.00, 14],
            ['smash', 'SMASH-005', 'Jalapeno Smash', 'هالبينو سماش', 'سماش بيف حار، هالبينو، جبنة وصوص سبايسي.', 29.00, 13],

            // Regular burgers
            ['burger', 'BURG-001', 'Classic Beef Burger', 'كلاسيك بيف برجر', 'قطعة لحم، خس، طماطم، مخلل وصوص البرجر.', 25.00, 15],
            ['burger', 'BURG-002', 'BBQ Beef Burger', 'باربكيو بيف برجر', 'لحم، جبنة، بصل كرسبي وصوص باربكيو.', 29.00, 16],
            ['burger', 'BURG-003', 'Cheese Burger', 'تشيز برجر', 'لحم بقري مع جبنة أمريكية وصوص البيت.', 27.00, 15],

            // Chicken burgers
            ['chicken', 'CHKN-001', 'Crispy Chicken', 'كرسبي تشيكن', 'دجاج كرسبي، خس، مخلل ومايونيز.', 24.00, 14],
            ['chicken', 'CHKN-002', 'Spicy Chicken', 'سبايسي تشيكن', 'دجاج كرسبي حار، خس، مخلل وصوص سبايسي.', 26.00, 14],
            ['chicken', 'CHKN-003', 'BBQ Chicken', 'باربكيو تشيكن', 'دجاج كرسبي، جبنة، بصل وصوص باربكيو.', 27.00, 15],

            // Appetizers
            ['appetizers', 'APP-001', 'Mozzarella Sticks', 'أصابع موزاريلا', 'أصابع جبنة موزاريلا مقرمشة مع صوص.', 16.00, 9],
            ['appetizers', 'APP-002', 'Onion Rings', 'حلقات بصل', 'حلقات بصل مقرمشة.', 12.00, 8],
            ['appetizers', 'APP-003', 'Chicken Bites', 'تشيكن بايتس', 'قطع دجاج كرسبي مع صوص من اختيارك.', 18.00, 10],
            ['appetizers', 'APP-004', 'Loaded Nachos', 'ناتشوز محملة', 'ناتشوز مع جبنة وصوصات وإضافات.', 20.00, 10],

            // Fries
            ['fries', 'FRY-001', 'French Fries', 'بطاطا مقلية', 'بطاطا ذهبية مقرمشة.', 8.00, 7],
            ['fries', 'FRY-002', 'Cheese Fries', 'بطاطا بالجبنة', 'بطاطا مع صوص جبنة.', 13.00, 8],
            ['fries', 'FRY-003', 'Loaded Smash Fries', 'لودد سماش فرايز', 'بطاطا، لحم سماش، جبنة وصوص البيت.', 20.00, 11],

            // Salads
            ['salads', 'SAL-001', 'Caesar Salad', 'سلطة سيزر', 'خس، بارميزان، كروتون وصوص سيزر.', 18.00, 8],
            ['salads', 'SAL-002', 'Chicken Caesar', 'سيزر دجاج', 'سلطة سيزر مع دجاج مشوي.', 24.00, 10],
            ['salads', 'SAL-003', 'Fresh Garden Salad', 'سلطة خضراء', 'خضار طازجة مع تتبيلة خفيفة.', 15.00, 7],

            // Sauces
            ['sauces', 'SAU-001', 'House Sauce', 'صوص البيت', 'صوص البرجر الخاص.', 3.00, 1],
            ['sauces', 'SAU-002', 'Spicy Sauce', 'صوص سبايسي', 'صوص حار كريمي.', 3.00, 1],
            ['sauces', 'SAU-003', 'Cheese Sauce', 'صوص جبنة', 'صوص جبنة كريمي.', 4.00, 1],
            ['sauces', 'SAU-004', 'BBQ Sauce', 'صوص باربكيو', 'صوص باربكيو مدخن.', 3.00, 1],

            // Soft drinks
            ['drinks', 'DRK-001', 'Cola', 'كولا', 'مشروب غازي بارد.', 5.00, 1],
            ['drinks', 'DRK-002', 'Sprite', 'سبرايت', 'مشروب غازي بارد.', 5.00, 1],
            ['drinks', 'DRK-003', 'Water', 'مياه معدنية', 'مياه معدنية باردة.', 3.00, 1],

            // Juices
            ['juices', 'JUI-001', 'Orange Juice', 'عصير برتقال', 'عصير برتقال طبيعي.', 10.00, 5],
            ['juices', 'JUI-002', 'Lemon Mint', 'ليمون نعنع', 'ليمون طازج مع نعنع.', 12.00, 6],
            ['juices', 'JUI-003', 'Mango Juice', 'عصير مانجا', 'عصير مانجا بارد.', 12.00, 5],
            ['juices', 'JUI-004', 'Strawberry Juice', 'عصير فراولة', 'عصير فراولة طازج.', 12.00, 5],

            // Milkshakes
            ['milkshakes', 'SHAKE-001', 'Chocolate Milkshake', 'ميلك شيك شوكولاتة', 'ميلك شيك شوكولاتة كريمي.', 16.00, 6],
            ['milkshakes', 'SHAKE-002', 'Oreo Milkshake', 'ميلك شيك أوريو', 'ميلك شيك أوريو كريمي.', 18.00, 6],
            ['milkshakes', 'SHAKE-003', 'Strawberry Milkshake', 'ميلك شيك فراولة', 'ميلك شيك فراولة كريمي.', 17.00, 6],

            // Desserts
            ['desserts', 'DES-001', 'Chocolate Cake', 'كيك شوكولاتة', 'قطعة كيك شوكولاتة غنية.', 15.00, 3],
            ['desserts', 'DES-002', 'Cheesecake', 'تشيز كيك', 'قطعة تشيز كيك كريمية.', 17.00, 3],
            ['desserts', 'DES-003', 'Brownie', 'براوني', 'براوني شوكولاتة دافئ.', 14.00, 5],

            // Kids
            ['kids', 'KIDS-001', 'Kids Burger Meal', 'وجبة برجر أطفال', 'برجر صغير، بطاطا وعصير.', 20.00, 12],
            ['kids', 'KIDS-002', 'Kids Chicken Meal', 'وجبة دجاج أطفال', 'دجاج كرسبي صغير، بطاطا وعصير.', 20.00, 12],
        ];

        $unitId = Schema::hasTable('units')
            ? DB::table('units')->where('code', 'piece')->value('id')
            : null;

        $productRows = [];
        $sortOrder = 10;

        foreach ($products as [$categoryKey, $sku, $name, $nameAr, $description, $price, $prep]) {
            $attributes = [
                'category_id' => $categoryIds[$categoryKey],
                'name' => $name,
                'name_ar' => $nameAr,
                'description' => $description,
                'unit' => 'piece',
                'base_selling_price' => $price,
                'is_active' => true,
            ];

            if (Schema::hasColumn('products', 'prep_time_minutes')) {
                $attributes['prep_time_minutes'] = $prep;
            }
            if (Schema::hasColumn('products', 'product_type')) {
                $attributes['product_type'] = 'standard';
            }
            if (Schema::hasColumn('products', 'unit_id') && $unitId) {
                $attributes['unit_id'] = $unitId;
            }

            $product = Product::withTrashed()->where('sku', $sku)->first();
            if ($product) {
                if (method_exists($product, 'trashed') && $product->trashed()) {
                    $product->restore();
                }
                $product->fill($attributes)->save();
            } else {
                $product = Product::create(array_merge(['sku' => $sku], $attributes));
            }

            $productRows[] = [
                'product' => $product,
                'price' => $price,
                'sort_order' => $sortOrder,
            ];

            $sortOrder += 10;
        }

        $branches = Location::query()->branches()->active()->get(['id']);

        foreach ($branches as $branch) {
            foreach ($productRows as $row) {
                /** @var Product $product */
                $product = $row['product'];

                DB::table('location_products')->updateOrInsert(
                    [
                        'location_id' => (int) $branch->id,
                        'product_id' => (int) $product->id,
                    ],
                    [
                        'is_available' => true,
                        'local_selling_price' => $row['price'],
                        'minimum_stock_level' => 0,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                if (Schema::hasTable('restaurant_menu_items')) {
                    $menuData = [
                        'display_name' => $product->name,
                        'display_name_ar' => $product->name_ar,
                        'description' => $product->description,
                        'sort_order' => $row['sort_order'],
                        'is_active' => true,
                        'show_in_pos' => true,
                        'show_in_qr' => true,
                        'show_in_delivery' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ];

                    if (Schema::hasColumn('restaurant_menu_items', 'inventory_mode')) {
                        $menuData['inventory_mode'] = 'product';
                    }

                    DB::table('restaurant_menu_items')->updateOrInsert(
                        [
                            'location_id' => (int) $branch->id,
                            'product_id' => (int) $product->id,
                        ],
                        $menuData
                    );
                }
            }
        }

        $this->command?->info(sprintf(
            'RestaurantSmashMenuSeeder: %d categories, %d products, %d active branches seeded.',
            count($categories),
            count($products),
            $branches->count()
        ));
    }
}
