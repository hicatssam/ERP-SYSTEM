<?php

namespace Tests\Feature\Restaurant;

use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\LocationPaymentMethod;
use App\Models\PaymentMethod;
use App\Models\SalesChannel;
use App\Models\Order;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductModifierGroup;
use App\Models\ProductVariant;
use App\Models\RestaurantMenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RestaurantPosCatalogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cashier_only_sees_branch_payment_methods_and_cannot_submit_another_branch_method(): void
    {
        $branch = $this->branch('PAY-A');
        $other = $this->branch('PAY-B');
        $cashier = $this->cashier($branch);
        $cashier->givePermissionTo(Permission::findOrCreate('orders.create', 'web'));
        $channel = SalesChannel::query()->create([
            'name' => 'Cashier channel', 'slug' => 'pos-checkout', 'type' => 'direct',
            'discount_type' => 'percentage', 'discount_value' => 10, 'is_active' => true,
        ]);
        $global = PaymentMethod::query()->create([
            'name' => 'Global cash', 'name_ar' => 'نقد عام', 'code' => 'pos-global-cash',
            'type' => 'cash', 'is_active' => true,
        ]);
        $otherOnly = PaymentMethod::query()->create([
            'name' => 'Other branch cash', 'name_ar' => 'نقد فرع آخر', 'code' => 'pos-other-cash',
            'type' => 'cash', 'is_active' => true,
        ]);
        LocationPaymentMethod::query()->create([
            'location_id' => $other->id, 'payment_method_id' => $otherOnly->id, 'is_active' => true,
        ]);
        $product = $this->product($this->category(), 'PAY-ITEM');
        $this->onMenu($branch, $product, 1);
        $this->atBranch($branch, $product, true);

        $this->actingAs($cashier)->get(route('restaurant.pos.index'))->assertOk()
            ->assertSee('نقد عام')->assertDontSee('نقد فرع آخر')
            ->assertSee('rbChannelDiscount')->assertSee('"discount_value":10', false);

        $payload = [
            'location_id' => $branch->id, 'service_type' => 'takeaway',
            'payment_arrangement' => 'pay_now', 'payment_method_id' => $otherOnly->id,
            'sales_channel_id' => $channel->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];
        $this->actingAs($cashier)->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertNotSame($global->id, $otherOnly->id);

        $payload['payment_method_id'] = $global->id;
        $payload['payment_arrangement'] = 'pending_verification';
        $payload['reference_number'] = 'POS-CHECK-100';
        $payload['payment_proof'] = UploadedFile::fake()->image('transfer.jpg');
        $this->actingAs($cashier)->post(route('restaurant.pos.orders.store'), $payload, [
            'Accept' => 'application/json',
        ])->assertCreated()->assertJsonPath('status', 'draft');

        $order = \App\Models\Order::query()->sole();
        $this->assertEquals(10, $order->subtotal);
        $this->assertEquals(1, $order->channel_discount_amount);
        $this->assertEquals(9, $order->total_amount);
        $this->assertEquals(9, $order->payments()->sole()->amount);
        $this->assertSame('pending_verification', $order->payments()->sole()->statusValue());
        $this->assertNull($order->invoice);
    }

    #[Test]
    public function qr_drawer_queries_real_customer_menu_orders_scoped_to_cashier_branch(): void
    {
        $branch = $this->branch('QR-A');
        $other = $this->branch('QR-B');
        $cashier = $this->cashier($branch);
        foreach ([
            ['POS-QR-LOCAL', $branch->id, 'customer_menu'],
            ['POS-QR-FOREIGN', $other->id, 'customer_menu'],
            ['POS-QR-NORMAL', $branch->id, null],
        ] as [$number, $locationId, $source]) {
            Order::query()->create([
                'order_number' => $number, 'location_id' => $locationId,
                'order_source' => $source, 'status' => 'draft',
                'payment_arrangement' => 'pay_on_pickup',
                'created_by' => $cashier->id,
            ]);
        }

        $this->actingAs($cashier)->getJson(route('restaurant.pos.qr-orders'))
            ->assertOk()->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.number', 'POS-QR-LOCAL');
        $this->actingAs($cashier)->getJson(route('restaurant.pos.qr-orders', ['q' => 'not-found']))
            ->assertOk()->assertJsonCount(0, 'orders');
        $this->actingAs($cashier)->getJson(route('restaurant.pos.qr-orders', ['location_id' => $other->id]))
            ->assertForbidden();
    }

    #[Test]
    public function cashier_sees_only_available_branch_menu_in_menu_order_with_branch_prices(): void
    {
        $branch = $this->branch('A');
        $other = $this->branch('B');
        $cashier = $this->cashier($branch);
        $category = $this->category();
        $brand = Brand::query()->create([
            'code' => 'COFFEE', 'name' => 'Coffee', 'name_ar' => 'قهوة', 'is_active' => true,
        ]);

        $later = $this->product($category, 'LATER');
        $first = $this->product($category, 'FIRST', [
            'brand_id' => $brand->id, 'barcode' => '6291234567890',
            'description' => 'الوصف من المنتج', 'base_selling_price' => 10,
            'product_type' => 'variant', 'image' => 'https://example.test/product.webp',
        ]);
        $hidden = $this->product($category, 'HIDDEN');
        $off = $this->product($category, 'OFF');
        $foreign = $this->product($category, 'FOREIGN');

        $this->onMenu($branch, $later, 20);
        $this->onMenu($branch, $first, 1, [
            'display_name_ar' => 'اسم المنيو', 'description' => 'وصف المنيو',
            'image' => 'https://example.test/menu.webp',
        ]);
        $this->onMenu($branch, $hidden, 0, ['show_in_pos' => false]);
        $this->onMenu($branch, $off, 0);
        $this->onMenu($other, $foreign, 0);

        foreach ([$later, $first, $hidden] as $product) {
            $this->atBranch($branch, $product, true, $product->id === $first->id ? 14.5 : null);
        }
        $this->atBranch($branch, $off, false);
        $this->atBranch($other, $foreign, true);
        $this->atBranch($other, $first, true, 99);
        $variant = ProductVariant::query()->create([
            'product_id' => $first->id, 'name_ar' => 'كبير', 'name' => 'Large',
            'sku' => 'FIRST-L', 'selling_price' => 18, 'is_active' => true,
        ]);
        $group = ModifierGroup::query()->create([
            'code' => 'milk', 'name' => 'Milk', 'name_ar' => 'الحليب',
            'selection_type' => 'single', 'is_required' => true, 'is_active' => true,
        ]);
        $modifier = Modifier::query()->create([
            'modifier_group_id' => $group->id, 'code' => 'oat',
            'name' => 'Oat', 'name_ar' => 'شوفان', 'price_delta' => 2,
            'is_active' => true,
        ]);
        ProductModifierGroup::query()->create([
            'product_id' => $first->id, 'modifier_group_id' => $group->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($cashier)->get(route('restaurant.pos.index'));
        $response->assertOk()->assertSee('rbProductOptionsModal')->assertSee('rbLoadMore');

        preg_match('/let products = (.*?);\s*const catalogUrl/s', $response->getContent(), $match);
        $this->assertCount(2, $match);
        $catalog = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([$first->id, $later->id], array_column($catalog, 'id'));
        $this->assertSame('اسم المنيو', $catalog[0]['name']);
        $this->assertSame('وصف المنيو', $catalog[0]['description']);
        $this->assertSame('قهوة', $catalog[0]['brand']);
        $this->assertSame('6291234567890', $catalog[0]['barcode']);
        $this->assertSame('https://example.test/menu.webp', $catalog[0]['image']);
        $this->assertSame(14.5, $catalog[0]['price']);
        $this->assertTrue($catalog[0]['requires_variant']);
        $this->assertSame($variant->id, $catalog[0]['variants'][0]['id']);
        $this->assertEquals(18, $catalog[0]['variants'][0]['price']);
        $this->assertSame($modifier->id, $catalog[0]['modifier_groups'][0]['modifiers'][0]['id']);
        $this->assertEquals(10.0, $catalog[1]['price']);

        $this->actingAs($cashier)->get(route('restaurant.pos.index', ['location_id' => $other->id]))
            ->assertForbidden();
    }

    #[Test]
    public function paged_catalog_search_and_draft_lookup_respect_branch_and_menu_visibility(): void
    {
        $branch = $this->branch('A');
        $other = $this->branch('B');
        $cashier = $this->cashier($branch);
        $category = $this->category();
        $brand = Brand::query()->create([
            'code' => 'POS-BRAND', 'name' => 'Brand', 'name_ar' => 'ماركة', 'is_active' => true,
        ]);

        for ($index = 1; $index <= 53; $index++) {
            $product = $this->product($category, 'ITEM-'.$index, [
                'brand_id' => $index === 53 ? $brand->id : null,
            ]);
            $this->onMenu($branch, $product, $index, $index === 53
                ? ['display_name_ar' => 'آخر منتج في المنيو'] : []);
            $this->atBranch($branch, $product, true, $index === 53 ? 27 : null);
            if ($index === 53) {
                $last = $product;
            }
        }

        $foreign = $this->product($category, 'OTHER-BRANCH');
        $hidden = $this->product($category, 'HIDDEN-ITEM');
        $this->onMenu($other, $foreign, 0);
        $this->onMenu($branch, $hidden, 0, ['show_in_pos' => false]);
        $this->atBranch($other, $foreign, true);
        $this->atBranch($branch, $hidden, true);

        $initial = $this->actingAs($cashier)->get(route('restaurant.pos.index'))->assertOk();
        preg_match('/let products = (.*?);\s*const catalogUrl/s', $initial->getContent(), $match);
        $firstPage = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(48, $firstPage);
        $this->assertSame('ITEM-1', $firstPage[0]['sku']);

        $page2 = $this->getJson(route('restaurant.pos.catalog', ['page' => 2]))->assertOk()
            ->assertJsonPath('total', 53)->assertJsonPath('has_more', false);
        $this->assertCount(5, $page2->json('products'));
        $this->assertSame($last->id, $page2->json('products.4.id'));

        $this->getJson(route('restaurant.pos.catalog', [
            'q' => 'آخر منتج', 'category_id' => $category->id, 'brand_id' => $brand->id,
        ]))->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('products.0.id', $last->id)
            ->assertJsonPath('products.0.price', 27);

        $lookup = $this->getJson(route('restaurant.pos.catalog', [
            'ids' => [$last->id, $foreign->id, $hidden->id],
        ]))->assertOk();
        $this->assertSame([$last->id], array_column($lookup->json('products'), 'id'));

        $this->getJson(route('restaurant.pos.catalog', ['location_id' => $other->id]))
            ->assertForbidden();
        $this->getJson(route('restaurant.pos.catalog', ['ids' => [$last->id, $last->id]]))
            ->assertUnprocessable();
    }

    #[Test]
    public function unavailable_menu_filter_includes_products_without_branch_listing(): void
    {
        $branch = $this->branch('A');
        $cashier = $this->cashier($branch);
        $category = $this->category();
        $missing = $this->product($category, 'MISSING');
        $disabled = $this->product($category, 'DISABLED');
        $ready = $this->product($category, 'READY');
        $this->onMenu($branch, $missing, 1);
        $this->onMenu($branch, $disabled, 2);
        $this->onMenu($branch, $ready, 3);
        $this->atBranch($branch, $disabled, false);
        $this->atBranch($branch, $ready, true);

        $response = $this->actingAs($cashier)->get(route('restaurant.menu.index', [
            'status' => 'unavailable',
        ]));

        $response->assertOk()->assertSee('MISSING')->assertSee('DISABLED')
            ->assertDontSee('READY');
    }

    private function branch(string $code): Location
    {
        return Location::query()->create([
            'name' => 'Branch '.$code, 'code' => 'POS-'.$code,
            'type' => 'branch', 'is_active' => true,
        ]);
    }

    private function cashier(Location $branch): User
    {
        $this->withoutMiddleware(EnsureModuleEnabled::class);
        $employee = Employee::query()->create([
            'employee_number' => 'POS-'.$branch->id, 'full_name' => 'Cashier',
        ]);
        $employee->locations()->attach($branch->id, ['is_primary' => true]);
        $user = User::factory()->create(['employee_id' => $employee->id]);
        foreach (['restaurant.view', 'restaurant_pos.use', 'restaurant_menu.view'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function category(): Category
    {
        return Category::query()->create(['name' => 'Drinks', 'name_ar' => 'مشروبات', 'is_active' => true]);
    }

    private function product(Category $category, string $sku, array $extra = []): Product
    {
        return Product::query()->create([
            'category_id' => $category->id, 'name' => $sku, 'sku' => $sku,
            'barcode' => 'BC-'.$sku,
            'base_selling_price' => 10, 'is_active' => true, ...$extra,
        ]);
    }

    private function onMenu(Location $location, Product $product, int $sortOrder, array $extra = []): void
    {
        RestaurantMenuItem::query()->create([
            'location_id' => $location->id, 'product_id' => $product->id,
            'sort_order' => $sortOrder, 'is_active' => true, 'show_in_pos' => true,
            ...$extra,
        ]);
    }

    private function atBranch(Location $branch, Product $product, bool $available, ?float $price = null): void
    {
        LocationProduct::query()->create([
            'location_id' => $branch->id, 'product_id' => $product->id,
            'is_available' => $available, 'local_selling_price' => $price,
        ]);
    }
}
