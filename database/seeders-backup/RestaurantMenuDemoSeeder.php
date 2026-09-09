<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RestaurantMenuDemoSeeder extends Seeder
{
    public function run(): void
    {
        $locations = Location::query()
            ->where('is_active', true)
            ->where('type', 'branch')
            ->orderBy('id')
            ->get();

        if ($locations->isEmpty()) {
            $locations = Location::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get();
        }

        if ($locations->isEmpty()) {
            $this->command?->error('لا يوجد فرع/موقع فعال. أنشئ Location أولاً ثم أعد تشغيل Seeder.');
            return;
        }

        $categories = $this->seedCategories();
        $pieceUnitId = Schema::hasTable('units')
            ? DB::table('units')->where('code', 'piece')->value('id')
            : null;

        $menu = [
            // =========================
            // BURGERS
            // =========================
            ['restaurant-burgers', 'REST-BUR-001', '6299100001001', 'Classic Beef Burger', 'برغر لحم كلاسيك', 28.00, 'برغر لحم مشوي مع خس وطماطم وصوص خاص.', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-burgers', 'REST-BUR-002', '6299100001002', 'Cheese Burger', 'تشيز برغر', 32.00, 'برغر لحم مع جبنة شيدر وصوص المطعم.', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-burgers', 'REST-BUR-003', '6299100001003', 'Double Beef Burger', 'دبل برغر لحم', 42.00, 'قطعتان لحم مع جبنة وخضار وصوص خاص.', 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-burgers', 'REST-BUR-004', '6299100001004', 'Crispy Chicken Burger', 'برغر دجاج كرسبي', 30.00, 'دجاج مقرمش مع خس ومايونيز.', 'https://images.unsplash.com/photo-1562967914-608f82629710?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-burgers', 'REST-BUR-005', '6299100001005', 'Mushroom Swiss Burger', 'برغر مشروم سويس', 36.00, 'لحم مشوي مع مشروم وجبنة سويسرية.', 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=700&q=85'],

            // =========================
            // PIZZA
            // =========================
            ['restaurant-pizza', 'REST-PIZ-001', '6299100002001', 'Margherita Pizza', 'بيتزا مارغريتا', 30.00, 'صلصة طماطم وموزاريلا وريحان.', 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-pizza', 'REST-PIZ-002', '6299100002002', 'Pepperoni Pizza', 'بيتزا بيبروني', 38.00, 'موزاريلا وبيبروني وصلصة طماطم.', 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-pizza', 'REST-PIZ-003', '6299100002003', 'BBQ Chicken Pizza', 'بيتزا دجاج باربكيو', 42.00, 'دجاج وصوص باربكيو وبصل وموزاريلا.', 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-pizza', 'REST-PIZ-004', '6299100002004', 'Vegetable Pizza', 'بيتزا خضار', 34.00, 'فلفل ومشروم وزيتون وبصل وموزاريلا.', 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?auto=format&fit=crop&w=700&q=85'],

            // =========================
            // APPETIZERS
            // =========================
            ['restaurant-appetizers', 'REST-APP-001', '6299100003001', 'French Fries', 'بطاطا مقلية', 12.00, 'بطاطا ذهبية مقرمشة.', 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-appetizers', 'REST-APP-002', '6299100003002', 'Onion Rings', 'حلقات بصل', 14.00, 'حلقات بصل مقرمشة مع صوص.', 'https://images.unsplash.com/photo-1639024471283-03518883512d?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-appetizers', 'REST-APP-003', '6299100003003', 'Mozzarella Sticks', 'أصابع موزاريلا', 18.00, 'أصابع جبنة موزاريلا مقلية.', 'https://images.unsplash.com/photo-1625944525533-473f1a3d54e7?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-appetizers', 'REST-APP-004', '6299100003004', 'Chicken Wings', 'أجنحة دجاج', 24.00, 'أجنحة دجاج بصوص حار أو باربكيو.', 'https://images.unsplash.com/photo-1527477396000-e27163b481c2?auto=format&fit=crop&w=700&q=85'],

            // =========================
            // SALADS
            // =========================
            ['restaurant-salads', 'REST-SAL-001', '6299100004001', 'Caesar Salad', 'سلطة سيزر', 22.00, 'خس روماني وبارميزان وكروتون وصوص سيزر.', 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-salads', 'REST-SAL-002', '6299100004002', 'Greek Salad', 'سلطة يونانية', 20.00, 'خيار وطماطم وزيتون وجبنة فيتا.', 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-salads', 'REST-SAL-003', '6299100004003', 'Chicken Caesar Salad', 'سلطة سيزر بالدجاج', 29.00, 'سلطة سيزر مع شرائح دجاج مشوي.', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=700&q=85'],

            // =========================
            // MAIN DISHES
            // =========================
            ['restaurant-mains', 'REST-MAI-001', '6299100005001', 'Grilled Chicken', 'دجاج مشوي', 45.00, 'صدر دجاج مشوي مع بطاطا وخضار.', 'https://images.unsplash.com/photo-1532550907401-a500c9a57435?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-mains', 'REST-MAI-002', '6299100005002', 'Beef Steak', 'ستيك لحم', 68.00, 'ستيك لحم مشوي مع صوص وفرايز.', 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-mains', 'REST-MAI-003', '6299100005003', 'Chicken Strips', 'تشيكن ستربس', 32.00, 'شرائح دجاج مقرمشة مع فرايز.', 'https://images.unsplash.com/photo-1562967914-608f82629710?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-mains', 'REST-MAI-004', '6299100005004', 'Fish & Chips', 'فيش آند تشبس', 40.00, 'سمك مقرمش مع بطاطا وصوص تارتار.', 'https://images.unsplash.com/photo-1579208030886-b937da0925dc?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-mains', 'REST-MAI-005', '6299100005005', 'Chicken Alfredo Pasta', 'باستا ألفريدو بالدجاج', 38.00, 'باستا كريمية مع دجاج ومشروم.', 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?auto=format&fit=crop&w=700&q=85'],

            // =========================
            // DRINKS
            // =========================
            ['restaurant-drinks', 'REST-DRK-001', '6299100006001', 'Cola', 'كولا', 6.00, 'مشروب غازي بارد.', 'https://images.unsplash.com/photo-1581006852262-e4307cf6283a?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-drinks', 'REST-DRK-002', '6299100006002', 'Lemon Mint', 'ليمون بالنعناع', 12.00, 'ليمون طازج مع نعناع وثلج.', 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-drinks', 'REST-DRK-003', '6299100006003', 'Orange Juice', 'عصير برتقال', 10.00, 'عصير برتقال طازج.', 'https://images.unsplash.com/photo-1600271886742-f049cd451bba?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-drinks', 'REST-DRK-004', '6299100006004', 'Iced Tea', 'آيس تي', 9.00, 'شاي مثلج بنكهة الليمون.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-drinks', 'REST-DRK-005', '6299100006005', 'Mineral Water', 'مياه معدنية', 3.00, 'مياه معدنية باردة.', 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?auto=format&fit=crop&w=700&q=85'],

            // =========================
            // DESSERTS
            // =========================
            ['restaurant-desserts', 'REST-DES-001', '6299100007001', 'Chocolate Brownie', 'براوني شوكولاتة', 18.00, 'براوني غني بالشوكولاتة.', 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-desserts', 'REST-DES-002', '6299100007002', 'Cheesecake Slice', 'قطعة تشيز كيك', 20.00, 'تشيز كيك كريمي مع صوص.', 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-desserts', 'REST-DES-003', '6299100007003', 'Chocolate Cake Slice', 'قطعة كيك شوكولاتة', 18.00, 'قطعة كيك شوكولاتة غنية.', 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=700&q=85'],
            ['restaurant-desserts', 'REST-DES-004', '6299100007004', 'Ice Cream', 'آيس كريم', 15.00, 'ثلاث كرات آيس كريم.', 'https://images.unsplash.com/photo-1560008581-09826d1de69e?auto=format&fit=crop&w=700&q=85'],
        ];

        DB::transaction(function () use ($menu, $categories, $locations, $pieceUnitId) {
            foreach ($menu as [$categorySlug, $sku, $barcode, $name, $nameAr, $price, $description, $image]) {
                $category = $categories[$categorySlug];

                $product = Product::withTrashed()
                    ->where('sku', $sku)
                    ->first();

                if (! $product) {
                    $product = new Product();
                    $product->sku = $sku;
                } elseif (method_exists($product, 'trashed') && $product->trashed()) {
                    $product->restore();
                }

                $payload = [
                    'category_id' => $category->id,
                    'name' => $name,
                    'name_ar' => $nameAr,
                    'barcode' => $barcode,
                    'description' => $description,
                    'unit' => 'piece',
                    'base_selling_price' => $price,
                    'image' => $image,
                    'is_active' => true,
                ];

                if (Schema::hasColumn('products', 'tracks_batch')) {
                    $payload['tracks_batch'] = false;
                }

                if (Schema::hasColumn('products', 'tracks_expiry')) {
                    $payload['tracks_expiry'] = false;
                }

                if (Schema::hasColumn('products', 'product_type')) {
                    $payload['product_type'] = 'standard';
                }

                if (Schema::hasColumn('products', 'unit_id') && $pieceUnitId) {
                    $payload['unit_id'] = $pieceUnitId;
                }

                $product->forceFill($payload);
                $product->save();

                foreach ($locations as $location) {
                    LocationProduct::query()->updateOrCreate(
                        [
                            'location_id' => $location->id,
                            'product_id' => $product->id,
                        ],
                        [
                            'is_available' => true,
                            'local_selling_price' => $price,
                            'minimum_stock_level' => 5,
                        ]
                    );

                    if (Schema::hasTable('inventories')) {
                        $inventoryPayload = [];

                        foreach ([
                            'quantity' => 500,
                            'reserved_quantity' => 0,
                            'damaged_quantity' => 0,
                            'in_transit_quantity' => 0,
                            'unit_cost' => round($price * 0.45, 2),
                        ] as $column => $value) {
                            if (Schema::hasColumn('inventories', $column)) {
                                $inventoryPayload[$column] = $value;
                            }
                        }

                        if ($inventoryPayload) {
                            Inventory::query()->updateOrCreate(
                                [
                                    'location_id' => $location->id,
                                    'product_id' => $product->id,
                                ],
                                $inventoryPayload
                            );
                        }
                    }
                }
            }
        });

        $this->command?->info('Restaurant demo menu seeded successfully.');
        $this->command?->info('Products: ' . count($menu));
        $this->command?->info('Locations: ' . $locations->pluck('name')->join(', '));
    }

    private function seedCategories(): array
    {
        $rows = [
            ['slug' => 'restaurant-burgers',    'name' => 'Burgers',     'name_ar' => 'برغر',          'sort_order' => 10],
            ['slug' => 'restaurant-pizza',      'name' => 'Pizza',       'name_ar' => 'بيتزا',          'sort_order' => 20],
            ['slug' => 'restaurant-appetizers', 'name' => 'Appetizers',  'name_ar' => 'مقبلات',         'sort_order' => 30],
            ['slug' => 'restaurant-salads',     'name' => 'Salads',      'name_ar' => 'سلطات',          'sort_order' => 40],
            ['slug' => 'restaurant-mains',      'name' => 'Main Dishes', 'name_ar' => 'وجبات رئيسية',   'sort_order' => 50],
            ['slug' => 'restaurant-drinks',     'name' => 'Drinks',      'name_ar' => 'مشروبات',        'sort_order' => 60],
            ['slug' => 'restaurant-desserts',   'name' => 'Desserts',    'name_ar' => 'حلويات',         'sort_order' => 70],
        ];

        $result = [];

        foreach ($rows as $row) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'name_ar' => $row['name_ar'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ]
            );

            $result[$row['slug']] = $category;
        }

        return $result;
    }
}