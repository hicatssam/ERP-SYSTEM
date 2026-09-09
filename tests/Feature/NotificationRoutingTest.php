<?php

namespace Tests\Feature;

use App\Events\LowStockDetected;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\PaymentReceived;
use App\Events\SpecialCakeOrderTransitioned;
use App\Listeners\NotifyStaffOnLowStock;
use App\Listeners\NotifyStaffOnOrderCreated;
use App\Listeners\NotifyStaffOnOrderStatusChanged;
use App\Listeners\NotifyStaffOnPaymentReceived;
use App\Listeners\NotifyStaffOnSpecialCakeOrderTransitioned;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Notifications\LowStockDetectedNotification;
use App\Notifications\OrderCreatedNotification;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    /** Create a branch Location. */
    private function branch(string $suffix = ''): Location
    {
        static $n = 0;
        $n++;
        return Location::create([
            'name'      => 'Branch ' . $n . $suffix,
            'code'      => 'BR-' . $n . $suffix,
            'type'      => 'branch',
            'is_active' => true,
        ]);
    }

    /** Create a factory Location. */
    private function factory(string $suffix = ''): Location
    {
        static $n = 0;
        $n++;
        return Location::create([
            'name'      => 'Factory ' . $n . $suffix,
            'code'      => 'FAC-' . $n . $suffix,
            'type'      => 'factory',
            'is_active' => true,
        ]);
    }

    /**
     * Create an active User assigned to $role and located at $location.
     * The Employee ↔ Location link is stored in employee_locations (pivot).
     */
    private function userAt(string $roleName, Location $location): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $this->grantOperationalPermissions($role, $roleName);

        $employee = Employee::create([
            'employee_number'   => 'EMP-' . uniqid(),
            'full_name'         => fake()->name(),
            'employment_status' => 'active',
        ]);

        // Associate via pivot so listeners' whereHas('employee.locations', ...) works.
        $employee->locations()->attach($location->id, ['is_primary' => true]);

        $user = User::factory()->create([
            'employee_id' => $employee->id,
            'is_active'   => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Create a global (no specific branch) active User assigned to $role.
     * The factory already creates an Employee row; we just skip the location link.
     */
    private function globalUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $this->grantOperationalPermissions($role, $roleName);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * RefreshDatabase does not run the production role/permission seeders.
     * Give each test role the minimal real permissions used by notification
     * listeners so tests exercise permission + location routing together.
     */
    private function grantOperationalPermissions(Role $role, string $roleName): void
    {
        $permissions = match ($roleName) {
            'Branch Manager' => [
                'orders.view',
                'inventory.view',
                'payments.record',
                'financial.branch.view',
                'cake_orders.view',
                'cake_orders.receive',
            ],
            'General Manager' => [
                'orders.view',
                'inventory.view',
                'financial.global.view',
                'cake_orders.view',
                'cake_orders.manage',
            ],
            'Accountant' => [
                'financial.global.view',
                'payments.verify',
            ],
            'Inventory Manager' => [
                'inventory.view',
                'inventory.adjust',
            ],
            'Factory Manager' => [
                'cake_orders.view',
                'cake_orders.review',
                'cake_orders.accept',
                'cake_orders.manage',
            ],
            'Cake Designer' => [
                'cake_orders.view',
                'cake_orders.decorate',
                'cake_orders.quality_check',
            ],
            'Production Employee' => [
                'cake_orders.view',
                'cake_orders.prepare',
            ],
            default => [],
        };

        foreach ($permissions as $permissionName) {
            $permission = Permission::findOrCreate($permissionName, 'web');
            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }

    /** Create a minimal Order belonging to $location, created by $creator. */
    private function orderAt(Location $location, User $creator): Order
    {
        return Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'location_id'  => $location->id,
            'created_by'   => $creator->id,
            'status'       => 'draft',
        ]);
    }

    /** Create a PaymentMethod row (required FK for Payment). */
    private function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::create([
            'name'                  => 'Cash',
            'name_ar'               => 'نقد',
            'code'                  => 'CASH-' . uniqid(),
            'type'                  => 'cash',
            'requires_verification' => false,
            'requires_reference'    => false,
            'is_active'             => true,
            'sort_order'            => 0,
        ]);
    }

    /** Create a minimal Payment at $location, received by $receiver. */
    private function paymentAt(Location $location, User $receiver): Payment
    {
        $creator = $this->globalUser('Admin');
        $order   = $this->orderAt($location, $creator);
        $method  = $this->paymentMethod();

        return Payment::create([
            'order_type'        => 'order',
            'order_id'          => $order->id,
            'payment_method_id' => $method->id,
            'location_id'       => $location->id,
            'amount'            => 100.00,
            'status'            => 'confirmed',
            'received_by'       => $receiver->id,
            'paid_at'           => now(),
        ]);
    }

    /** Create a minimal Customer (required FK for SpecialCakeOrder). */
    private function customer(): Customer
    {
        return Customer::create([
            'name'  => 'Customer-' . uniqid(),
            'phone' => '050' . rand(1000000, 9999999),
        ]);
    }

    /** Create a minimal SpecialCakeOrder originating from $branch. */
    private function cakeOrderFrom(Location $branch, ?Location $factory = null, ?User $creator = null): SpecialCakeOrder
    {
        $creator ??= $this->globalUser('Admin');

        return SpecialCakeOrder::create([
            'order_number'        => 'CAKE-' . uniqid(),
            'customer_id'         => $this->customer()->id,
            'origin_branch_id'    => $branch->id,
            'factory_location_id' => $factory?->id,
            'required_date'       => now()->addDays(7)->toDateString(),
            'status'              => 'draft',
            'created_by'          => $creator->id,
        ]);
    }

    /**
     * Set up a Product + LocationProduct + Inventory at $location.
     * Returns the Inventory record.
     */
    private function inventoryAt(Location $location, float $qty, float $minLevel): Inventory
    {
        $category = Category::create([
            'name'       => 'Cat-' . uniqid(),
            'slug'       => 'cat-' . uniqid(),
            'is_active'  => true,
            'sort_order' => 0,
        ]);

        $product = Product::create([
            'category_id'        => $category->id,
            'name'               => 'Product-' . uniqid(),
            'sku'                => 'SKU-' . uniqid(),
            'barcode'            => app(\App\Services\ProductCodeService::class)->generateEan13(),
            'base_selling_price' => 10.00,
            'is_active'          => true,
        ]);

        LocationProduct::create([
            'location_id'         => $location->id,
            'product_id'          => $product->id,
            'is_available'        => true,
            'minimum_stock_level' => $minLevel,
        ]);

        return Inventory::create([
            'location_id' => $location->id,
            'product_id'  => $product->id,
            'quantity'    => $qty,
        ]);
    }

    // ─── OrderCreated: branch scoping ─────────────────────────────────────────────

    #[Test]
    public function order_created_notifies_branch_manager_at_same_branch(): void
    {
        $branch  = $this->branch();
        $manager = $this->userAt('Branch Manager', $branch);
        $creator = $this->globalUser('Admin');

        $order = $this->orderAt($branch, $creator);
        (new NotifyStaffOnOrderCreated)->handle(new OrderCreated($order, $creator));

        $this->assertCount(1, $manager->fresh()->notifications);
    }

    #[Test]
    public function order_created_does_not_notify_branch_manager_at_different_branch(): void
    {
        $branchA = $this->branch('A');
        $branchB = $this->branch('B');

        $managerB = $this->userAt('Branch Manager', $branchB);
        $creator  = $this->globalUser('Admin');

        // Order is at branch A; manager is at branch B
        $order = $this->orderAt($branchA, $creator);
        (new NotifyStaffOnOrderCreated)->handle(new OrderCreated($order, $creator));

        $this->assertCount(0, $managerB->fresh()->notifications);
    }

    #[Test]
    public function order_created_notifies_admin_regardless_of_branch(): void
    {
        $branch  = $this->branch();
        $admin   = $this->globalUser('Admin');
        $creator = $this->globalUser('General Manager');

        $order = $this->orderAt($branch, $creator);
        (new NotifyStaffOnOrderCreated)->handle(new OrderCreated($order, $creator));

        $this->assertCount(1, $admin->fresh()->notifications);
    }

    #[Test]
    public function order_created_does_not_notify_the_creator(): void
    {
        $branch  = $this->branch();
        $creator = $this->userAt('Branch Manager', $branch);

        $order = $this->orderAt($branch, $creator);
        (new NotifyStaffOnOrderCreated)->handle(new OrderCreated($order, $creator));

        $this->assertCount(0, $creator->fresh()->notifications);
    }

    // ─── OrderCreated: dedup (1-day window) ───────────────────────────────────────

    #[Test]
    public function duplicate_order_created_notification_within_dedup_window_is_suppressed(): void
    {
        $branch  = $this->branch();
        $manager = $this->userAt('Branch Manager', $branch);
        $creator = $this->globalUser('Admin');

        $order = $this->orderAt($branch, $creator);
        $event = new OrderCreated($order, $creator);

        (new NotifyStaffOnOrderCreated)->handle($event);
        (new NotifyStaffOnOrderCreated)->handle($event); // same fingerprint

        $this->assertCount(1, $manager->fresh()->notifications);
    }

    #[Test]
    public function order_created_notification_is_sent_again_after_dedup_window_expires(): void
    {
        $branch  = $this->branch();
        $manager = $this->userAt('Branch Manager', $branch);
        $creator = $this->globalUser('Admin');

        $order       = $this->orderAt($branch, $creator);
        $event       = new OrderCreated($order, $creator);
        $fingerprint = 'order_created_' . $order->id;

        // First fire — sets cache and sends
        (new NotifyStaffOnOrderCreated)->handle($event);

        // Expire both the cache key and the DB timestamp past the 1-day window
        Cache::forget("notif_dedup_{$manager->id}_{$fingerprint}");
        $manager->notifications()->update(['created_at' => now()->subDays(2)]);

        // Second fire — outside dedup window; should notify again
        (new NotifyStaffOnOrderCreated)->handle($event);

        $this->assertCount(2, $manager->fresh()->notifications);
    }

    // ─── Concurrency: atomic Cache::add gate ─────────────────────────────────────

    #[Test]
    public function concurrent_order_created_listeners_emit_only_one_notification(): void
    {
        $branch  = $this->branch();
        $manager = $this->userAt('Branch Manager', $branch);
        $creator = $this->globalUser('Admin');
        $order   = $this->orderAt($branch, $creator);

        $fingerprint = 'order_created_' . $order->id;
        $cacheKey    = "notif_dedup_{$manager->id}_{$fingerprint}";

        // Simulate Worker A: has won the Cache::add race and is in the process of
        // persisting its notification. The DB is still empty at this point.
        Cache::add($cacheKey, 1, 86400);

        // Worker B arrives; the DB still shows no notification for this fingerprint
        // (Layer 1 passes), but Cache::add fails (Layer 2 blocks it).
        (new NotifyStaffOnOrderCreated)->handle(new OrderCreated($order, $creator));

        $this->assertCount(
            0,
            $manager->fresh()->notifications,
            'A worker that loses the Cache::add race must not send a duplicate notification'
        );
    }

    #[Test]
    public function concurrent_low_stock_listeners_emit_only_one_notification(): void
    {
        $branch  = $this->branch();
        $manager = $this->userAt('Branch Manager', $branch);

        $inv         = $this->inventoryAt($branch, qty: 3, minLevel: 10);
        $fingerprint = "low_stock_{$branch->id}_{$inv->product_id}";
        $cacheKey    = "notif_dedup_{$manager->id}_{$fingerprint}";

        // Worker A has already claimed the cache slot; DB is still empty
        Cache::add($cacheKey, 1, 6 * 3600);

        // Worker B is blocked by the cache gate
        (new NotifyStaffOnLowStock)->handle(
            new LowStockDetected($inv, currentQuantity: 3.0, minimumLevel: 10.0)
        );

        $this->assertCount(
            0,
            $manager->fresh()->notifications,
            'A worker that loses the Cache::add race must not send a duplicate low-stock notification'
        );
    }

    // ─── LowStockDetected: routing ────────────────────────────────────────────────

    #[Test]
    public function low_stock_detected_notifies_branch_manager_at_same_location(): void
    {
        $branch  = $this->branch();
        $manager = $this->userAt('Branch Manager', $branch);

        $inv = $this->inventoryAt($branch, qty: 5, minLevel: 10);

        (new NotifyStaffOnLowStock)->handle(
            new LowStockDetected($inv, currentQuantity: 5.0, minimumLevel: 10.0)
        );

        $this->assertCount(1, $manager->fresh()->notifications);
    }

    #[Test]
    public function low_stock_detected_does_not_notify_branch_manager_at_different_location(): void
    {
        $branchA = $this->branch('A');
        $branchB = $this->branch('B');

        $managerB = $this->userAt('Branch Manager', $branchB);

        // Inventory event is for branch A
        $inv = $this->inventoryAt($branchA, qty: 2, minLevel: 10);

        (new NotifyStaffOnLowStock)->handle(
            new LowStockDetected($inv, currentQuantity: 2.0, minimumLevel: 10.0)
        );

        $this->assertCount(0, $managerB->fresh()->notifications);
    }

    #[Test]
    public function low_stock_detected_notifies_global_roles_at_any_location(): void
    {
        $branch     = $this->branch();
        $admin      = $this->globalUser('Admin');
        $genManager = $this->globalUser('General Manager');
        $invManager = $this->globalUser('Inventory Manager');

        $inv = $this->inventoryAt($branch, qty: 1, minLevel: 5);

        (new NotifyStaffOnLowStock)->handle(
            new LowStockDetected($inv, currentQuantity: 1.0, minimumLevel: 5.0)
        );

        $this->assertCount(1, $admin->fresh()->notifications);
        $this->assertCount(1, $genManager->fresh()->notifications);
        $this->assertCount(1, $invManager->fresh()->notifications);
    }

    // ─── LowStockDetected: dedup (6-hour window) ─────────────────────────────────

    #[Test]
    public function duplicate_low_stock_notification_within_6_hours_is_suppressed(): void
    {
        $branch  = $this->branch();
        $manager = $this->userAt('Branch Manager', $branch);

        $inv   = $this->inventoryAt($branch, qty: 3, minLevel: 10);
        $event = new LowStockDetected($inv, currentQuantity: 3.0, minimumLevel: 10.0);

        (new NotifyStaffOnLowStock)->handle($event);
        (new NotifyStaffOnLowStock)->handle($event); // within 6 hours

        $this->assertCount(1, $manager->fresh()->notifications);
    }

    #[Test]
    public function low_stock_notification_is_sent_again_after_6_hour_dedup_window(): void
    {
        $branch  = $this->branch();
        $manager = $this->userAt('Branch Manager', $branch);

        $inv         = $this->inventoryAt($branch, qty: 3, minLevel: 10);
        $event       = new LowStockDetected($inv, currentQuantity: 3.0, minimumLevel: 10.0);
        $fingerprint = "low_stock_{$branch->id}_{$inv->product_id}";

        (new NotifyStaffOnLowStock)->handle($event);

        // Expire both the cache key and the DB timestamp past the 6-hour window
        Cache::forget("notif_dedup_{$manager->id}_{$fingerprint}");
        $manager->notifications()->update(['created_at' => now()->subHours(7)]);

        (new NotifyStaffOnLowStock)->handle($event);

        $this->assertCount(2, $manager->fresh()->notifications);
    }

    // ─── LowStockDetected: dispatch threshold ─────────────────────────────────────

    #[Test]
    public function low_stock_event_is_not_dispatched_when_stock_remains_above_minimum(): void
    {
        Event::fake([LowStockDetected::class]);

        $branch  = $this->branch();
        $creator = $this->globalUser('Admin');

        // Inventory at 20, minimum 10; reduce by 5 → still 15, above minimum
        $inv = $this->inventoryAt($branch, qty: 20, minLevel: 10);

        app(InventoryService::class)->adjust(
            $branch->id, $inv->product_id, -5, 'correction', $creator->id,
        );

        Event::assertNotDispatched(LowStockDetected::class);
    }

    #[Test]
    public function low_stock_event_is_dispatched_when_stock_falls_below_minimum(): void
    {
        Event::fake([LowStockDetected::class]);

        $branch  = $this->branch();
        $creator = $this->globalUser('Admin');

        // Inventory at 10, minimum 10; reduce by 1 → 9 (below minimum)
        $inv = $this->inventoryAt($branch, qty: 10, minLevel: 10);

        app(InventoryService::class)->adjust(
            $branch->id, $inv->product_id, -1, 'correction', $creator->id,
        );

        Event::assertDispatched(LowStockDetected::class, function (LowStockDetected $e) use ($inv) {
            return $e->inventory->product_id === $inv->product_id
                && $e->currentQuantity == 9.0
                && $e->minimumLevel    == 10.0;
        });
    }

    #[Test]
    public function low_stock_event_is_dispatched_when_stock_is_exactly_at_minimum(): void
    {
        Event::fake([LowStockDetected::class]);

        $branch  = $this->branch();
        $creator = $this->globalUser('Admin');

        // Inventory at 11, minimum 10; reduce by 1 → exactly 10 (≤ minimum)
        $inv = $this->inventoryAt($branch, qty: 11, minLevel: 10);

        app(InventoryService::class)->adjust(
            $branch->id, $inv->product_id, -1, 'correction', $creator->id,
        );

        Event::assertDispatched(LowStockDetected::class);
    }

    #[Test]
    public function low_stock_event_is_not_dispatched_on_stock_increase(): void
    {
        Event::fake([LowStockDetected::class]);

        $branch  = $this->branch();
        $creator = $this->globalUser('Admin');

        // Even if stock is already below minimum, an upward adjust should not fire the event
        $inv = $this->inventoryAt($branch, qty: 2, minLevel: 10);

        app(InventoryService::class)->adjust(
            $branch->id, $inv->product_id, +5, 'correction', $creator->id,
        );

        Event::assertNotDispatched(LowStockDetected::class);
    }

    // ─── OrderStatusChanged: routing ─────────────────────────────────────────────

    #[Test]
    public function order_status_changed_notifies_branch_manager_at_same_branch(): void
    {
        $branch  = $this->branch();
        $manager = $this->userAt('Branch Manager', $branch);
        $actor   = $this->globalUser('Admin');

        $order = $this->orderAt($branch, $actor);
        (new NotifyStaffOnOrderStatusChanged)->handle(
            new OrderStatusChanged($order, 'draft', 'confirmed', $actor)
        );

        $this->assertCount(1, $manager->fresh()->notifications);
    }

    #[Test]
    public function order_status_changed_does_not_notify_branch_manager_at_different_branch(): void
    {
        $branchA = $this->branch('A');
        $branchB = $this->branch('B');

        $managerB = $this->userAt('Branch Manager', $branchB);
        $actor    = $this->globalUser('Admin');

        $order = $this->orderAt($branchA, $actor);
        (new NotifyStaffOnOrderStatusChanged)->handle(
            new OrderStatusChanged($order, 'draft', 'confirmed', $actor)
        );

        $this->assertCount(0, $managerB->fresh()->notifications);
    }

    #[Test]
    public function order_status_changed_does_not_notify_the_actor(): void
    {
        $branch = $this->branch();
        $actor  = $this->userAt('Branch Manager', $branch);
        $order  = $this->orderAt($branch, $actor);

        (new NotifyStaffOnOrderStatusChanged)->handle(
            new OrderStatusChanged($order, 'draft', 'confirmed', $actor)
        );

        $this->assertCount(0, $actor->fresh()->notifications);
    }

    // ─── OrderStatusChanged: dedup (1-day window) ────────────────────────────────

    #[Test]
    public function duplicate_order_status_changed_notification_within_dedup_window_is_suppressed(): void
    {
        $branch  = $this->branch();
        $manager = $this->userAt('Branch Manager', $branch);
        $actor   = $this->globalUser('Admin');

        $order = $this->orderAt($branch, $actor);
        $event = new OrderStatusChanged($order, 'draft', 'confirmed', $actor);

        (new NotifyStaffOnOrderStatusChanged)->handle($event);
        (new NotifyStaffOnOrderStatusChanged)->handle($event); // same fingerprint

        $this->assertCount(1, $manager->fresh()->notifications);
    }

    #[Test]
    public function order_status_changed_notification_is_sent_again_after_dedup_window_expires(): void
    {
        $branch  = $this->branch();
        $manager = $this->userAt('Branch Manager', $branch);
        $actor   = $this->globalUser('Admin');

        $order       = $this->orderAt($branch, $actor);
        $event       = new OrderStatusChanged($order, 'draft', 'confirmed', $actor);
        $fingerprint = "order_status_{$order->id}_draft_confirmed";

        // First fire — sets cache and sends
        (new NotifyStaffOnOrderStatusChanged)->handle($event);

        // Expire both the cache key and the DB timestamp past the 1-day window
        Cache::forget("notif_dedup_{$manager->id}_{$fingerprint}");
        $manager->notifications()->update(['created_at' => now()->subDays(2)]);

        // Second fire — outside dedup window; should notify again
        (new NotifyStaffOnOrderStatusChanged)->handle($event);

        $this->assertCount(2, $manager->fresh()->notifications);
    }

    // ─── PaymentReceived: routing ─────────────────────────────────────────────────

    #[Test]
    public function payment_received_notifies_accountant_globally(): void
    {
        $branch     = $this->branch();
        $receiver   = $this->globalUser('Admin');
        $accountant = $this->globalUser('Accountant');

        $payment = $this->paymentAt($branch, $receiver);
        (new NotifyStaffOnPaymentReceived)->handle(new PaymentReceived($payment, $receiver));

        $this->assertCount(1, $accountant->fresh()->notifications);
    }

    #[Test]
    public function payment_received_notifies_branch_manager_at_same_location(): void
    {
        $branch   = $this->branch();
        $receiver = $this->globalUser('Admin');
        $manager  = $this->userAt('Branch Manager', $branch);

        $payment = $this->paymentAt($branch, $receiver);
        (new NotifyStaffOnPaymentReceived)->handle(new PaymentReceived($payment, $receiver));

        $this->assertCount(1, $manager->fresh()->notifications);
    }

    #[Test]
    public function payment_received_does_not_notify_branch_manager_at_different_location(): void
    {
        $branchA  = $this->branch('A');
        $branchB  = $this->branch('B');
        $receiver = $this->globalUser('Admin');
        $managerB = $this->userAt('Branch Manager', $branchB);

        $payment = $this->paymentAt($branchA, $receiver);
        (new NotifyStaffOnPaymentReceived)->handle(new PaymentReceived($payment, $receiver));

        $this->assertCount(0, $managerB->fresh()->notifications);
    }

    #[Test]
    public function payment_received_does_not_notify_the_receiver(): void
    {
        $branch   = $this->branch();
        $receiver = $this->userAt('Branch Manager', $branch);

        $payment = $this->paymentAt($branch, $receiver);
        (new NotifyStaffOnPaymentReceived)->handle(new PaymentReceived($payment, $receiver));

        $this->assertCount(0, $receiver->fresh()->notifications);
    }

    // ─── PaymentReceived: dedup (1-day window) ────────────────────────────────────

    #[Test]
    public function duplicate_payment_received_notification_within_dedup_window_is_suppressed(): void
    {
        $branch     = $this->branch();
        $receiver   = $this->globalUser('Admin');
        $accountant = $this->globalUser('Accountant');

        $payment = $this->paymentAt($branch, $receiver);
        $event   = new PaymentReceived($payment, $receiver);

        (new NotifyStaffOnPaymentReceived)->handle($event);
        (new NotifyStaffOnPaymentReceived)->handle($event); // same fingerprint

        $this->assertCount(1, $accountant->fresh()->notifications);
    }

    #[Test]
    public function payment_received_notification_is_sent_again_after_dedup_window_expires(): void
    {
        $branch     = $this->branch();
        $receiver   = $this->globalUser('Admin');
        $accountant = $this->globalUser('Accountant');

        $payment     = $this->paymentAt($branch, $receiver);
        $event       = new PaymentReceived($payment, $receiver);
        $fingerprint = 'payment_received_' . $payment->id;

        // First fire — sets cache and sends
        (new NotifyStaffOnPaymentReceived)->handle($event);

        // Expire both the cache key and the DB timestamp past the 1-day window
        Cache::forget("notif_dedup_{$accountant->id}_{$fingerprint}");
        $accountant->notifications()->update(['created_at' => now()->subDays(2)]);

        // Second fire — outside dedup window; should notify again
        (new NotifyStaffOnPaymentReceived)->handle($event);

        $this->assertCount(2, $accountant->fresh()->notifications);
    }

    // ─── SpecialCakeOrderTransitioned: routing ────────────────────────────────────

    #[Test]
    public function cake_order_transition_notifies_branch_manager_at_origin_branch(): void
    {
        $branch   = $this->branch();
        $factory  = $this->factory();
        $manager  = $this->userAt('Branch Manager', $branch);
        $actor    = $this->globalUser('Admin');

        $order = $this->cakeOrderFrom($branch, $factory);
        (new NotifyStaffOnSpecialCakeOrderTransitioned)->handle(
            new SpecialCakeOrderTransitioned($order, 'draft', 'pending_factory_review', $actor)
        );

        $this->assertCount(1, $manager->fresh()->notifications);
    }

    #[Test]
    public function cake_order_transition_does_not_notify_branch_manager_at_different_branch(): void
    {
        $branchA = $this->branch('A');
        $branchB = $this->branch('B');
        $factory = $this->factory();

        $managerB = $this->userAt('Branch Manager', $branchB);
        $actor    = $this->globalUser('Admin');

        $order = $this->cakeOrderFrom($branchA, $factory);
        (new NotifyStaffOnSpecialCakeOrderTransitioned)->handle(
            new SpecialCakeOrderTransitioned($order, 'draft', 'pending_factory_review', $actor)
        );

        $this->assertCount(0, $managerB->fresh()->notifications);
    }

    #[Test]
    public function cake_order_transition_notifies_factory_role_at_assigned_factory(): void
    {
        $branch        = $this->branch();
        $factory       = $this->factory();
        $cakeDesigner  = $this->userAt('Cake Designer', $factory);
        $actor         = $this->globalUser('Admin');

        $order = $this->cakeOrderFrom($branch, $factory);
        (new NotifyStaffOnSpecialCakeOrderTransitioned)->handle(
            new SpecialCakeOrderTransitioned($order, 'accepted', 'in_preparation', $actor)
        );

        $this->assertCount(1, $cakeDesigner->fresh()->notifications);
    }

    #[Test]
    public function cake_order_transition_does_not_notify_factory_role_at_different_factory(): void
    {
        $branch    = $this->branch();
        $factoryA  = $this->factory('A');
        $factoryB  = $this->factory('B');

        $workerB = $this->userAt('Production Employee', $factoryB);
        $actor   = $this->globalUser('Admin');

        // Order is assigned to factory A
        $order = $this->cakeOrderFrom($branch, $factoryA);
        (new NotifyStaffOnSpecialCakeOrderTransitioned)->handle(
            new SpecialCakeOrderTransitioned($order, 'accepted', 'in_preparation', $actor)
        );

        $this->assertCount(0, $workerB->fresh()->notifications);
    }

    #[Test]
    public function cake_order_transition_notifies_global_roles_regardless_of_branch_or_factory(): void
    {
        $branch       = $this->branch();
        $factory      = $this->factory();
        $admin        = $this->globalUser('Admin');
        $genManager   = $this->globalUser('General Manager');
        $factoryMgr   = $this->globalUser('Factory Manager');
        $actor        = $this->globalUser('Admin'); // separate user from $admin above

        $order = $this->cakeOrderFrom($branch, $factory);
        (new NotifyStaffOnSpecialCakeOrderTransitioned)->handle(
            new SpecialCakeOrderTransitioned($order, 'quality_check', 'ready', $actor)
        );

        $this->assertCount(1, $admin->fresh()->notifications);
        $this->assertCount(1, $genManager->fresh()->notifications);
        $this->assertCount(1, $factoryMgr->fresh()->notifications);
    }

    #[Test]
    public function cake_order_transition_does_not_notify_the_actor(): void
    {
        $branch  = $this->branch();
        $factory = $this->factory();
        $actor   = $this->userAt('Branch Manager', $branch);

        $order = $this->cakeOrderFrom($branch, $factory);
        (new NotifyStaffOnSpecialCakeOrderTransitioned)->handle(
            new SpecialCakeOrderTransitioned($order, 'draft', 'pending_factory_review', $actor)
        );

        $this->assertCount(0, $actor->fresh()->notifications);
    }

    // ─── Deactivated-user exclusion ──────────────────────────────────────────────

    #[Test]
    public function deactivated_branch_manager_stops_receiving_order_status_changed_notifications(): void
    {
        $branch  = $this->branch();
        $manager = $this->userAt('Branch Manager', $branch);
        $actor   = $this->globalUser('Admin');

        $order = $this->orderAt($branch, $actor);

        // First fire — user is active; should receive notification.
        (new NotifyStaffOnOrderStatusChanged)->handle(
            new OrderStatusChanged($order, 'draft', 'confirmed', $actor)
        );
        $this->assertCount(1, $manager->fresh()->notifications);

        // Deactivate the account.
        $manager->update(['is_active' => false]);

        // Second fire — different transition so the dedup fingerprint is fresh.
        (new NotifyStaffOnOrderStatusChanged)->handle(
            new OrderStatusChanged($order, 'confirmed', 'processing', $actor)
        );

        // No new notification must have been added.
        $this->assertCount(1, $manager->fresh()->notifications);
    }

    #[Test]
    public function deactivated_branch_manager_stops_receiving_payment_received_notifications(): void
    {
        $branch   = $this->branch();
        $manager  = $this->userAt('Branch Manager', $branch);
        $receiver = $this->globalUser('Admin');

        // First payment — user is active; should receive notification.
        $payment1 = $this->paymentAt($branch, $receiver);
        (new NotifyStaffOnPaymentReceived)->handle(new PaymentReceived($payment1, $receiver));
        $this->assertCount(1, $manager->fresh()->notifications);

        // Deactivate the account.
        $manager->update(['is_active' => false]);

        // Second payment — fresh fingerprint (different payment row).
        $payment2 = $this->paymentAt($branch, $receiver);
        (new NotifyStaffOnPaymentReceived)->handle(new PaymentReceived($payment2, $receiver));

        // No new notification must have been added.
        $this->assertCount(1, $manager->fresh()->notifications);
    }

    #[Test]
    public function deactivated_branch_manager_stops_receiving_special_cake_order_transition_notifications(): void
    {
        $branch  = $this->branch();
        $factory = $this->factory();
        $manager = $this->userAt('Branch Manager', $branch);
        $actor   = $this->globalUser('Admin');

        $order = $this->cakeOrderFrom($branch, $factory);

        // First fire — user is active; should receive notification.
        (new NotifyStaffOnSpecialCakeOrderTransitioned)->handle(
            new SpecialCakeOrderTransitioned($order, 'draft', 'pending_factory_review', $actor)
        );
        $this->assertCount(1, $manager->fresh()->notifications);

        // Deactivate the account.
        $manager->update(['is_active' => false]);

        // Second fire — different transition so the dedup fingerprint is fresh.
        (new NotifyStaffOnSpecialCakeOrderTransitioned)->handle(
            new SpecialCakeOrderTransitioned($order, 'pending_factory_review', 'accepted', $actor)
        );

        // No new notification must have been added.
        $this->assertCount(1, $manager->fresh()->notifications);
    }
}
