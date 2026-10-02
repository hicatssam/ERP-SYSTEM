<?php

namespace Tests\Feature\Restaurant;

use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductModifierGroup;
use App\Models\ProductVariant;
use App\Models\RestaurantMenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RestaurantPosCatalogTest extends TestCase
{
    use RefreshDatabase;

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

        preg_match('/const products = (.*?);\s*const oldItems/s', $response->getContent(), $match);
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
