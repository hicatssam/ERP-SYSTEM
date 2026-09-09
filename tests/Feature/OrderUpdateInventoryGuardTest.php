<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderUpdateInventoryGuardTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────────

    private function makeLocation(): Location
    {
        return Location::create([
            'name'      => 'Test Branch ' . uniqid(),
            'code'      => 'TB-' . uniqid(),
            'type'      => 'branch',
            'is_active' => true,
        ]);
    }

    private function makeUserWithUpdatePermission(Location $location): User
    {
        Permission::firstOrCreate(['name' => 'orders.update',  'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'orders.view',    'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'orders.confirm', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'OrderEditor-' . uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(['orders.update', 'orders.view']);

        $employee = Employee::create([
            'employee_number'   => 'EMP-' . Str::random(6),
            'full_name'         => 'Test Staff',
            'employment_status' => 'active',
        ]);
        $employee->locations()->attach($location->id, ['is_primary' => true]);

        $user = User::factory()->create(['employee_id' => $employee->id]);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function makeProduct(string $name = 'Test Product'): Product
    {
        $category = Category::firstOrCreate(
            ['name' => 'Test Category'],
            ['name_ar' => 'Test Category', 'is_active' => true]
        );

        return Product::create([
            'category_id'        => $category->id,
            'name'               => $name,
            'name_ar'            => $name,
            'sku'                => 'SKU-' . uniqid(),
            'barcode'            => app(\App\Services\ProductCodeService::class)->generateEan13(),
            'base_selling_price' => '10.00',
            'is_active'          => true,
            'unit'               => 'piece',
        ]);
    }

    private function makeConfirmedOrder(Location $location, User $user): Order
    {
        return Order::create([
            'order_number'        => 'ORD-' . uniqid(),
            'location_id'         => $location->id,
            'status'              => 'confirmed',
            'payment_arrangement' => 'pay_now',
            'subtotal'            => '0',
            'total_amount'        => '0',
            'created_by'          => $user->id,
        ]);
    }

    private function makeDraftOrder(Location $location, User $user): Order
    {
        return Order::create([
            'order_number'        => 'ORD-' . uniqid(),
            'location_id'         => $location->id,
            'status'              => 'draft',
            'payment_arrangement' => 'pay_now',
            'subtotal'            => '0',
            'total_amount'        => '0',
            'created_by'          => $user->id,
        ]);
    }

    private function addItem(Order $order, Product $product, float $quantity): OrderItem
    {
        $lineTotal = bcmul((string) $quantity, (string) $product->base_selling_price, 3);

        return OrderItem::create([
            'order_id'    => $order->id,
            'product_id'  => $product->id,
            'product_name'=> $product->name,
            'quantity'    => $quantity,
            'unit_price'  => $product->base_selling_price,
            'line_total'  => $lineTotal,
        ]);
    }

    private function setInventory(Location $location, Product $product, float $quantity): Inventory
    {
        return Inventory::updateOrCreate(
            ['location_id' => $location->id, 'product_id' => $product->id],
            ['quantity' => $quantity]
        );
    }

    private function updatePayload(Order $order, array $itemOverrides = []): array
    {
        $items = $order->items->map(fn ($item) => [
            'id'       => $item->id,
            'quantity' => $itemOverrides[$item->id] ?? (float) $item->quantity,
        ])->values()->toArray();

        return [
            'payment_arrangement' => $order->payment_arrangement?->value ?? $order->payment_arrangement,
            'notes'               => $order->notes,
            'items'               => $items,
        ];
    }

    // ─── Tests ────────────────────────────────────────────────────────────────────

    #[Test]
    public function editing_a_confirmed_order_with_sufficient_stock_saves_and_adjusts_inventory(): void
    {
        $location = $this->makeLocation();
        $user     = $this->makeUserWithUpdatePermission($location);
        $product  = $this->makeProduct();
        $order    = $this->makeConfirmedOrder($location, $user);
        $item     = $this->addItem($order, $product, 5.0);
        $this->setInventory($location, $product, 10.0); // 10 available, increasing by 2

        $payload = $this->updatePayload($order, [$item->id => 7.0]);

        $this->actingAs($user)
            ->put(route('orders.update', $order), $payload)
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success');

        $this->assertEquals(7.0, (float) $item->fresh()->quantity);
        $this->assertEquals(8.0, (float) Inventory::where('location_id', $location->id)
            ->where('product_id', $product->id)->value('quantity')); // 10 - 2 = 8
    }

    #[Test]
    public function editing_a_confirmed_order_is_blocked_when_stock_would_go_negative(): void
    {
        $location = $this->makeLocation();
        $user     = $this->makeUserWithUpdatePermission($location);
        $product  = $this->makeProduct();
        $order    = $this->makeConfirmedOrder($location, $user);
        $item     = $this->addItem($order, $product, 5.0);
        $this->setInventory($location, $product, 3.0); // only 3 available; increasing by 4 should fail

        $payload = $this->updatePayload($order, [$item->id => 9.0]);

        $this->actingAs($user)
            ->put(route('orders.update', $order), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('items');

        // Item quantity and inventory must be unchanged
        $this->assertEquals(5.0, (float) $item->fresh()->quantity);
        $this->assertEquals(3.0, (float) Inventory::where('location_id', $location->id)
            ->where('product_id', $product->id)->value('quantity'));
    }

    #[Test]
    public function duplicate_product_lines_aggregate_delta_before_checking_stock(): void
    {
        // Two lines for the same product; net increase = 4 + 4 = 8, but only 5 available
        $location = $this->makeLocation();
        $user     = $this->makeUserWithUpdatePermission($location);
        $product  = $this->makeProduct();
        $order    = $this->makeConfirmedOrder($location, $user);
        $itemA    = $this->addItem($order, $product, 3.0);
        $itemB    = $this->addItem($order, $product, 3.0);
        $this->setInventory($location, $product, 5.0);

        // Each line individually needs 4 more (3→7), aggregate delta = 8 — exceeds 5
        $payload = $this->updatePayload($order, [
            $itemA->id => 7.0,
            $itemB->id => 7.0,
        ]);

        $this->actingAs($user)
            ->put(route('orders.update', $order), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('items');

        $this->assertEquals(3.0, (float) $itemA->fresh()->quantity);
        $this->assertEquals(3.0, (float) $itemB->fresh()->quantity);
        $this->assertEquals(5.0, (float) Inventory::where('location_id', $location->id)
            ->where('product_id', $product->id)->value('quantity'));
    }

    #[Test]
    public function reducing_quantity_on_a_confirmed_order_restores_inventory(): void
    {
        $location = $this->makeLocation();
        $user     = $this->makeUserWithUpdatePermission($location);
        $product  = $this->makeProduct();
        $order    = $this->makeConfirmedOrder($location, $user);
        $item     = $this->addItem($order, $product, 10.0);
        $this->setInventory($location, $product, 2.0);

        // Decrease from 10 → 6 should restore 4 units to inventory
        $payload = $this->updatePayload($order, [$item->id => 6.0]);

        $this->actingAs($user)
            ->put(route('orders.update', $order), $payload)
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success');

        $this->assertEquals(6.0, (float) $item->fresh()->quantity);
        $this->assertEquals(6.0, (float) Inventory::where('location_id', $location->id)
            ->where('product_id', $product->id)->value('quantity')); // 2 + 4 = 6
    }

    #[Test]
    public function editing_a_draft_order_does_not_check_or_touch_inventory(): void
    {
        $location = $this->makeLocation();
        $user     = $this->makeUserWithUpdatePermission($location);
        $product  = $this->makeProduct();
        $order    = $this->makeDraftOrder($location, $user);
        $item     = $this->addItem($order, $product, 3.0);
        // Zero inventory — would be blocked if a check ran
        $this->setInventory($location, $product, 0.0);

        $payload = $this->updatePayload($order, [$item->id => 20.0]);

        $this->actingAs($user)
            ->put(route('orders.update', $order), $payload)
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success');

        $this->assertEquals(20.0, (float) $item->fresh()->quantity);
        // Inventory must remain untouched for a pending order
        $this->assertEquals(0.0, (float) Inventory::where('location_id', $location->id)
            ->where('product_id', $product->id)->value('quantity'));
    }

    #[Test]
    public function header_fields_are_saved_on_a_confirmed_order_edit(): void
    {
        $location = $this->makeLocation();
        $user     = $this->makeUserWithUpdatePermission($location);
        $product  = $this->makeProduct();
        $order    = $this->makeConfirmedOrder($location, $user);
        $item     = $this->addItem($order, $product, 2.0);
        $this->setInventory($location, $product, 10.0);

        $payload = [
            'payment_arrangement' => 'deposit',
            'notes'               => 'Updated note',
            'items'               => [
                ['id' => $item->id, 'quantity' => 2.0],
            ],
        ];

        $this->actingAs($user)
            ->put(route('orders.update', $order), $payload)
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success');

        $fresh = $order->fresh();
        $this->assertEquals('deposit', $fresh->payment_arrangement?->value ?? $fresh->payment_arrangement);
        $this->assertEquals('Updated note', $fresh->notes);
    }

    #[Test]
    public function invoice_totals_and_line_items_are_synced_when_editing_a_confirmed_order(): void
    {
        $location = $this->makeLocation();
        $user     = $this->makeUserWithUpdatePermission($location);
        $product  = $this->makeProduct();
        $order    = $this->makeConfirmedOrder($location, $user);
        $item     = $this->addItem($order, $product, 5.0);
        $this->setInventory($location, $product, 20.0);

        // Create a linked invoice (simulates what confirmOrder() does)
        $invoice = \App\Models\Invoice::create([
            'invoice_number'  => 'INV-' . uniqid(),
            'invoice_type'    => 'regular_order',
            'order_type'      => 'order',
            'order_id'        => $order->id,
            'location_id'     => $location->id,
            'subtotal'        => '50.00',
            'total_amount'    => '50.00',
            'paid_amount'     => '20.00',
            'remaining_amount'=> '30.00',
            'status'          => 'active',
            'issued_at'       => now(),
            'issued_by'       => $user->id,
        ]);
        \App\Models\InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'product_id'  => $product->id,
            'description' => $product->name,
            'quantity'    => 5.0,
            'unit_price'  => '10.00',
            'line_total'  => '50.00',
        ]);
        $order->update(['invoice_id' => $invoice->id]);

        // Increase quantity from 5 → 8 (+3 units, +30 amount)
        $payload = $this->updatePayload($order, [$item->id => 8.0]);

        $this->actingAs($user)
            ->put(route('orders.update', $order), $payload)
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success');

        $freshInvoice = $invoice->fresh(['items']);
        $this->assertEquals('80.00', $freshInvoice->total_amount);   // 8 × 10
        $this->assertEquals('80.00', $freshInvoice->subtotal);
        $this->assertEquals('60.00', $freshInvoice->remaining_amount); // 80 - 20 paid
        $this->assertEquals('8.000', $freshInvoice->items->first()->quantity);
        $this->assertEquals('80.00', $freshInvoice->items->first()->line_total);
    }

    #[Test]
    public function a_user_cannot_edit_an_order_belonging_to_a_different_branch(): void
    {
        $locationA = $this->makeLocation();
        $locationB = $this->makeLocation();
        $user      = $this->makeUserWithUpdatePermission($locationA); // user belongs to A
        $product   = $this->makeProduct();
        // Order belongs to location B
        $order = Order::create([
            'order_number'        => 'ORD-' . uniqid(),
            'location_id'         => $locationB->id,
            'status'              => 'draft',
            'payment_arrangement' => 'pay_now',
            'subtotal'            => '0',
            'total_amount'        => '0',
            'created_by'          => $user->id,
        ]);
        $item = $this->addItem($order, $product, 2.0);

        $payload = $this->updatePayload($order, [$item->id => 5.0]);

        $this->actingAs($user)
            ->put(route('orders.update', $order), $payload)
            ->assertForbidden();
    }
}
