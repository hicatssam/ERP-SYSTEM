<?php

namespace Tests\Feature\Sales;

use App\Enums\CakeOrderStatus;
use App\Enums\ShowroomCakeRequestStatus;
use App\Enums\ShowroomSweetsRequestStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Location;
use App\Models\LocationPaymentMethod;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomCakeRequestItem;
use App\Models\ShowroomSweetsRequest;
use App\Models\ShowroomSweetsRequestItem;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CakeUrgencyAndCustomerReservationTest extends TestCase
{
    use RefreshDatabase;

    private Location $branch;
    private Location $otherBranch;
    private Location $factory;
    private User $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::parse(
                '2026-09-26 14:00:00',
                config('app.timezone')
            )
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->branch = $this->makeLocation(
            'Branch A',
            'branch'
        );

        $this->otherBranch = $this->makeLocation(
            'Branch B',
            'branch'
        );

        $this->factory = $this->makeLocation(
            'Main Factory',
            'factory'
        );

        $this->admin = $this->makeAdmin(
            $this->branch
        );

        $this->customer = Customer::query()
            ->create([
                'location_id' =>
                    $this->branch->id,
                'customer_type' =>
                    Customer::TYPE_INDIVIDUAL,
                'scope' =>
                    Customer::SCOPE_BRANCH,
                'name' =>
                    'Urgent Cake Customer',
                'phone' =>
                    '0599555010',
                'allow_credit' => false,
                'billing_cycle' =>
                    'immediate',
                'payment_terms_days' => 0,
            ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function normal_special_cake_cannot_be_created_for_today(): void
    {
        $paymentMethod =
            $this->makeCashPaymentMethod();

        $this->actingAs($this->admin)
            ->post(
                route(
                    'cake-orders.store'
                ),
                $this->specialCakeStorePayload(
                    $paymentMethod,
                    [
                        'required_date' =>
                            today()->toDateString(),
                        'required_time' =>
                            '16:00',
                    ]
                )
            )
            ->assertSessionHasErrors(
                'required_date'
            );

        $this->assertDatabaseMissing(
            'special_cake_orders',
            [
                'customer_id' =>
                    $this->customer->id,
                'required_date' =>
                    today()->toDateString(),
            ]
        );
    }

    #[Test]
    public function urgent_special_cake_can_be_created_for_later_today(): void
    {
        $paymentMethod =
            $this->makeCashPaymentMethod();

        $response =
            $this->actingAs($this->admin)
                ->post(
                    route(
                        'cake-orders.store'
                    ),
                    $this->specialCakeStorePayload(
                        $paymentMethod,
                        [
                            'required_date' =>
                                today()->toDateString(),
                            'required_time' =>
                                '16:00',
                            'is_urgent' => 1,
                            'urgent_reason' =>
                                'مناسبة مفاجئة والعميل يحتاج الطلب اليوم.',
                        ]
                    )
                );

        $order = SpecialCakeOrder::query()
            ->latest('id')
            ->first();

        $this->assertNotNull($order);

        $response->assertRedirect(
            route(
                'cake-orders.show',
                $order
            )
        );

        $this->assertTrue(
            (bool) $order->is_urgent
        );

        $this->assertSame(
            today()->toDateString(),
            $order
                ->required_date
                ->toDateString()
        );

        $this->assertSame(
            'مناسبة مفاجئة والعميل يحتاج الطلب اليوم.',
            $order->urgent_reason
        );
    }

    #[Test]
    public function normal_special_cake_cannot_be_rescheduled_for_today(): void
    {
        $order = $this->makeSpecialCakeOrder();

        $this->actingAs($this->admin)
            ->put(
                route(
                    'cake-orders.update',
                    $order
                ),
                [
                    'required_date' =>
                        today()->toDateString(),
                    'required_time' =>
                        '16:00',
                    'total_price' => 150,
                ]
            )
            ->assertSessionHasErrors(
                'required_date'
            );

        $this->assertSame(
            today()
                ->addDay()
                ->toDateString(),
            $order
                ->fresh()
                ->required_date
                ->toDateString()
        );

        $this->assertFalse(
            (bool) $order
                ->fresh()
                ->is_urgent
        );
    }

    #[Test]
    public function same_day_special_cake_requires_urgent_reason_and_future_time(): void
    {
        $order = $this->makeSpecialCakeOrder();

        $this->actingAs($this->admin)
            ->put(
                route(
                    'cake-orders.update',
                    $order
                ),
                [
                    'required_date' =>
                        today()->toDateString(),
                    'required_time' =>
                        '16:00',
                    'is_urgent' => 1,
                    'total_price' => 150,
                ]
            )
            ->assertSessionHasErrors(
                'urgent_reason'
            );

        $this->actingAs($this->admin)
            ->put(
                route(
                    'cake-orders.update',
                    $order
                ),
                [
                    'required_date' =>
                        today()->toDateString(),
                    'required_time' =>
                        '13:30',
                    'is_urgent' => 1,
                    'urgent_reason' =>
                        'مناسبة مفاجئة',
                    'total_price' => 150,
                ]
            )
            ->assertSessionHasErrors(
                'required_time'
            );
    }

    #[Test]
    public function urgent_special_cake_can_be_scheduled_for_later_today(): void
    {
        $order = $this->makeSpecialCakeOrder();

        $this->actingAs($this->admin)
            ->put(
                route(
                    'cake-orders.update',
                    $order
                ),
                [
                    'required_date' =>
                        today()->toDateString(),
                    'required_time' =>
                        '16:00',
                    'is_urgent' => 1,
                    'urgent_reason' =>
                        'مناسبة مفاجئة والعميل يحتاج الكيك اليوم.',
                    'cake_type' =>
                        'chocolate',
                    'cake_size' =>
                        'medium',
                    'total_price' => 150,
                ]
            )
            ->assertRedirect(
                route(
                    'cake-orders.show',
                    $order
                )
            );

        $order->refresh();

        $this->assertTrue(
            (bool) $order->is_urgent
        );

        $this->assertSame(
            today()->toDateString(),
            $order
                ->required_date
                ->toDateString()
        );

        $this->assertSame(
            '16:00',
            substr(
                (string) $order->required_time,
                0,
                5
            )
        );

        $this->assertSame(
            'مناسبة مفاجئة والعميل يحتاج الكيك اليوم.',
            $order->urgent_reason
        );
    }

    #[Test]
    public function branch_cake_reservation_can_be_updated_after_request_creation(): void
    {
        $branchUser = $this->makeUser(
            $this->branch,
            [
                'showroom_cake_requests.view',
                'showroom_cake_requests.create',
            ]
        );

        $request = $this->makeCakeRequest();

        $item = ShowroomCakeRequestItem::query()
            ->create([
                'showroom_cake_request_id' =>
                    $request->id,
                'cake_type' => 'fruit',
                'cake_size' => 'medium',
                'shape' => 'round',
                'quantity' => 150,
                'reserved_quantity' => 0,
            ]);

        $this->actingAs($branchUser)
            ->patch(
                route(
                    'showroom-cake-requests.items.reservation',
                    [
                        $request,
                        $item,
                    ]
                ),
                [
                    'reserved_quantity' => 5,
                    'reservation_notes' =>
                        '3 لمحمد، 2 لسارة',
                ]
            )
            ->assertRedirect();

        $item->refresh();

        $this->assertSame(
            5,
            (int) $item->reserved_quantity
        );

        $this->assertSame(
            145,
            $item->availableQuantity()
        );

        $this->assertSame(
            '3 لمحمد، 2 لسارة',
            $item->reservation_notes
        );
    }

    #[Test]
    public function branch_cake_reservation_cannot_exceed_requested_quantity(): void
    {
        $branchUser = $this->makeUser(
            $this->branch,
            [
                'showroom_cake_requests.view',
                'showroom_cake_requests.create',
            ]
        );

        $request = $this->makeCakeRequest();

        $item = ShowroomCakeRequestItem::query()
            ->create([
                'showroom_cake_request_id' =>
                    $request->id,
                'cake_type' => 'fruit',
                'quantity' => 150,
                'reserved_quantity' => 5,
            ]);

        $this->actingAs($branchUser)
            ->patch(
                route(
                    'showroom-cake-requests.items.reservation',
                    [
                        $request,
                        $item,
                    ]
                ),
                [
                    'reserved_quantity' => 151,
                ]
            )
            ->assertSessionHasErrors(
                'reserved_quantity'
            );

        $this->assertSame(
            5,
            (int) $item
                ->fresh()
                ->reserved_quantity
        );
    }

    #[Test]
    public function branch_sweets_reservation_tracks_reserved_and_available_quantities(): void
    {
        $branchUser = $this->makeUser(
            $this->branch,
            [
                'showroom_sweets_requests.view',
                'showroom_sweets_requests.create',
            ]
        );

        $request = $this->makeSweetsRequest();

        $category = Category::query()
            ->create([
                'name' => 'Sweets',
                'name_ar' => 'حلويات',
                'slug' => 'sweets-test',
                'is_active' => true,
            ]);

        $product = Product::query()
            ->create([
                'category_id' =>
                    $category->id,
                'name' => 'Kolaj',
                'name_ar' => 'كلاج',
                'sku' =>
                    'KOL-TEST',
                'unit' => 'صدر',
                'base_selling_price' =>
                    0,
                'product_type' =>
                    'standard',
                'is_active' => true,
                'tracks_batch' => false,
                'tracks_expiry' => false,
            ]);

        $item = ShowroomSweetsRequestItem::query()
            ->create([
                'showroom_sweets_request_id' =>
                    $request->id,
                'product_id' =>
                    $product->id,
                'product_name_snapshot' =>
                    'كلاج',
                'quantity' => 150,
                'reserved_quantity' => 0,
                'requested_unit' =>
                    'صدر',
            ]);

        $this->actingAs($branchUser)
            ->patch(
                route(
                    'showroom-sweets-requests.items.reservation',
                    [
                        $request,
                        $item,
                    ]
                ),
                [
                    'reserved_quantity' => 5,
                    'reservation_notes' =>
                        '5 صدور محجوزة لطلبات عملاء',
                ]
            )
            ->assertRedirect();

        $item->refresh();

        $this->assertSame(
            5.0,
            (float) $item->reserved_quantity
        );

        $this->assertSame(
            145.0,
            $item->availableQuantity()
        );
    }

    #[Test]
    public function another_branch_cannot_modify_customer_reservations(): void
    {
        $wrongBranchUser = $this->makeUser(
            $this->otherBranch,
            [
                'showroom_cake_requests.view',
                'showroom_cake_requests.create',
            ]
        );

        $request = $this->makeCakeRequest();

        $item = ShowroomCakeRequestItem::query()
            ->create([
                'showroom_cake_request_id' =>
                    $request->id,
                'cake_type' => 'chocolate',
                'quantity' => 20,
                'reserved_quantity' => 2,
            ]);

        $this->actingAs($wrongBranchUser)
            ->patch(
                route(
                    'showroom-cake-requests.items.reservation',
                    [
                        $request,
                        $item,
                    ]
                ),
                [
                    'reserved_quantity' => 8,
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            2,
            (int) $item
                ->fresh()
                ->reserved_quantity
        );
    }

    private function makeCashPaymentMethod(): PaymentMethod
    {
        $method = PaymentMethod::query()
            ->create([
                'name' => 'Cash',
                'name_ar' => 'نقدي',
                'code' =>
                    'cash-'
                    . Str::lower(
                        Str::random(6)
                    ),
                'type' => 'cash',
                'requires_verification' =>
                    false,
                'requires_reference' =>
                    false,
                'is_active' => true,
                'sort_order' => 1,
            ]);

        LocationPaymentMethod::query()
            ->create([
                'location_id' =>
                    $this->branch->id,
                'payment_method_id' =>
                    $method->id,
                'is_active' => true,
            ]);

        return $method;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function specialCakeStorePayload(
        PaymentMethod $paymentMethod,
        array $overrides = []
    ): array {
        return array_merge(
            [
                'customer_id' =>
                    $this->customer->id,
                'required_date' =>
                    today()
                        ->addDay()
                        ->toDateString(),
                'required_time' =>
                    '16:00',
                'cake_type' =>
                    'chocolate',
                'shape' =>
                    'round',
                'cake_size' =>
                    'medium',
                'total_price' =>
                    150,
                'payment_arrangement' =>
                    'pay_on_pickup',
                'payment_method_id' =>
                    $paymentMethod->id,
                'image_cover_type' =>
                    'none',
                'discount_type' =>
                    'none',
                'discount_value' =>
                    0,
            ],
            $overrides
        );
    }

    private function makeSpecialCakeOrder(): SpecialCakeOrder
    {
        return SpecialCakeOrder::query()
            ->create([
                'order_number' =>
                    'CK-URG-'
                    . Str::upper(
                        Str::random(7)
                    ),
                'customer_id' =>
                    $this->customer->id,
                'origin_branch_id' =>
                    $this->branch->id,
                'factory_location_id' =>
                    $this->factory->id,
                'required_date' =>
                    today()
                        ->addDay()
                        ->toDateString(),
                'required_time' =>
                    '16:00',
                'cake_type' =>
                    'chocolate',
                'cake_size' =>
                    'medium',
                'total_price' => 150,
                'discount_type' =>
                    'none',
                'discount_value' => 0,
                'discount_amount' => 0,
                'net_price' => 150,
                'status' =>
                    CakeOrderStatus::Pending,
                'payment_status' =>
                    'payment_pending',
                'payment_arrangement' =>
                    'pay_on_pickup',
                'image_cover_type' =>
                    'none',
                'is_urgent' => false,
                'created_by' =>
                    $this->admin->id,
            ]);
    }

    private function makeCakeRequest(): ShowroomCakeRequest
    {
        return ShowroomCakeRequest::query()
            ->create([
                'request_number' =>
                    'SCR-RES-'
                    . Str::upper(
                        Str::random(7)
                    ),
                'requesting_location_id' =>
                    $this->branch->id,
                'factory_location_id' =>
                    $this->factory->id,
                'status' =>
                    ShowroomCakeRequestStatus::Pending,
                'needed_by' =>
                    today()
                        ->addDays(2)
                        ->toDateString(),
                'created_by' =>
                    $this->admin->id,
                'submitted_at' =>
                    now(),
            ]);
    }

    private function makeSweetsRequest(): ShowroomSweetsRequest
    {
        return ShowroomSweetsRequest::query()
            ->create([
                'request_number' =>
                    'SSR-RES-'
                    . Str::upper(
                        Str::random(7)
                    ),
                'requesting_location_id' =>
                    $this->branch->id,
                'factory_location_id' =>
                    $this->factory->id,
                'status' =>
                    ShowroomSweetsRequestStatus::Pending,
                'needed_by' =>
                    today()
                        ->addDays(2)
                        ->toDateString(),
                'created_by' =>
                    $this->admin->id,
                'submitted_at' =>
                    now(),
            ]);
    }

    private function makeLocation(
        string $name,
        string $type
    ): Location {
        return Location::query()
            ->create([
                'name' => $name,
                'code' =>
                    'RES-'
                    . Str::upper(
                        Str::random(8)
                    ),
                'type' => $type,
                'is_active' => true,
            ]);
    }

    private function makeUser(
        Location $location,
        array $permissions = []
    ): User {
        $employee = Employee::query()
            ->create([
                'employee_number' =>
                    'RES-EMP-'
                    . Str::upper(
                        Str::random(8)
                    ),
                'full_name' =>
                    'Reservation User',
                'employment_status' =>
                    'active',
            ]);

        $employee
            ->locations()
            ->attach(
                $location->id,
                [
                    'is_primary' => true,
                ]
            );

        $user = User::factory()
            ->create([
                'employee_id' =>
                    $employee->id,
                'is_active' => true,
                'must_change_password' =>
                    false,
            ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(
                Permission::findOrCreate(
                    $permission,
                    'web'
                )
            );
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }

    private function makeAdmin(
        Location $location
    ): User {
        $user = $this->makeUser(
            $location
        );

        $user->assignRole(
            Role::findOrCreate(
                'Admin',
                'web'
            )
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }
}
