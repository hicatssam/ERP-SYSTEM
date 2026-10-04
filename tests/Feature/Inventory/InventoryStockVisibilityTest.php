<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InventoryStockVisibilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function exhausted_stock_and_available_product_without_inventory_are_visible_by_branch(): void
    {
        $branch = Location::create(['name' => 'Branch A', 'code' => 'STA', 'type' => 'branch', 'is_active' => true]);
        $other = Location::create(['name' => 'Branch B', 'code' => 'STB', 'type' => 'branch', 'is_active' => true]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $user->givePermissionTo(Permission::findOrCreate('inventory.view', 'web'));
        $category = Category::create(['name' => 'Meals', 'name_ar' => 'وجبات', 'is_active' => true]);
        $make = fn (string $name) => Product::create([
            'category_id' => $category->id, 'name' => $name, 'name_ar' => $name,
            'sku' => $name, 'barcode' => app(\App\Services\ProductCodeService::class)->generateEan13(),
            'base_selling_price' => 10, 'is_active' => true, 'unit' => 'piece',
        ]);
        $empty = $make('Empty');
        $missing = $make('Missing');
        $foreign = $make('Foreign');
        $fromDraft = $make('Draft Burger');
        Inventory::create(['location_id' => $branch->id, 'product_id' => $empty->id, 'quantity' => 0, 'reserved_quantity' => 0, 'unit_cost' => 0]);
        LocationProduct::create(['location_id' => $branch->id, 'product_id' => $missing->id, 'is_available' => true, 'minimum_stock_level' => 0]);
        Inventory::create(['location_id' => $other->id, 'product_id' => $foreign->id, 'quantity' => 0, 'reserved_quantity' => 0, 'unit_cost' => 0]);
        $draft = Order::create(['order_number' => 'ORD-STOCK-CHECK', 'location_id' => $branch->id,
            'status' => 'draft', 'payment_arrangement' => 'pay_now', 'subtotal' => 10,
            'total_amount' => 10, 'created_by' => $user->id]);
        OrderItem::create(['order_id' => $draft->id, 'product_id' => $fromDraft->id,
            'product_name' => 'Draft Burger', 'quantity' => 1, 'unit_price' => 10, 'line_total' => 10]);

        $this->actingAs($user)->get(route('inventory.index', ['stock' => 'out']))
            ->assertOk()->assertSee('منتجات نافدة')->assertSee('Empty')
            ->assertSee('Missing')->assertSee('Draft Burger')->assertDontSee('Foreign');
    }
}
