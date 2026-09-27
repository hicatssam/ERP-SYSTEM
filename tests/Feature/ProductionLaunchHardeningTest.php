<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EmployeeLocation;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\User;
use App\Policies\InvoicePolicy;
use App\Services\SpecialCakes\SpecialCakeOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductionLaunchHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Location $branchA;
    private Location $branchB;
    private Location $factory;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->branchA = $this->location(
            'Branch A',
            'branch'
        );

        $this->branchB = $this->location(
            'Branch B',
            'branch'
        );

        $this->factory = $this->location(
            'Factory',
            'factory'
        );
    }

    #[Test]
    public function invoice_permission_does_not_cross_branch_boundaries(): void
    {
        $user = $this->userAt(
            $this->branchA
        );

        $permission =
            Permission::findOrCreate(
                'invoices.view',
                'web'
            );

        $user->givePermissionTo(
            $permission
        );

        $otherBranchInvoice =
            new Invoice([
                'location_id' =>
                    $this->branchB->id,
                'status' => 'active',
            ]);

        $ownBranchInvoice =
            new Invoice([
                'location_id' =>
                    $this->branchA->id,
                'status' => 'active',
            ]);

        $policy = new InvoicePolicy();

        $this->assertFalse(
            $policy->view(
                $user,
                $otherBranchInvoice
            )
        );

        $this->assertTrue(
            $policy->view(
                $user,
                $ownBranchInvoice
            )
        );
    }

    #[Test]
    public function invoice_cancel_permission_is_also_location_scoped(): void
    {
        $user = $this->userAt(
            $this->branchA
        );

        $user->givePermissionTo(
            Permission::findOrCreate(
                'invoices.cancel',
                'web'
            )
        );

        $invoice =
            new Invoice([
                'location_id' =>
                    $this->branchB->id,
                'status' => 'active',
            ]);

        $this->assertFalse(
            (new InvoicePolicy())
                ->cancel(
                    $user,
                    $invoice
                )
        );
    }

    #[Test]
    public function admin_attached_to_factory_must_choose_a_real_origin_branch_for_special_cake(): void
    {
        $admin = $this->userAt(
            $this->factory,
            'Admin'
        );

        $customer = $this->customerAt(
            $this->branchA
        );

        $service = app(
            SpecialCakeOrderService::class
        );

        $this->expectException(
            ValidationException::class
        );

        $service->createOrder(
            $this->cakePayload(
                $customer->id
            ),
            $admin
        );
    }

    #[Test]
    public function admin_selected_branch_is_persisted_as_special_cake_origin(): void
    {
        $admin = $this->userAt(
            $this->factory,
            'Admin'
        );

        $customer = $this->customerAt(
            $this->branchA
        );

        $payload = $this->cakePayload(
            $customer->id
        );

        $payload['origin_branch_id'] =
            $this->branchA->id;

        $order = app(
            SpecialCakeOrderService::class
        )->createOrder(
            $payload,
            $admin
        );

        $this->assertSame(
            $this->branchA->id,
            (int) $order->origin_branch_id
        );

        $this->assertSame(
            $this->factory->id,
            (int) $order->factory_location_id
        );

        $this->assertNotSame(
            (int) $order->origin_branch_id,
            (int) $order->factory_location_id
        );
    }

    #[Test]
    public function normal_branch_user_cannot_spoof_another_special_cake_origin(): void
    {
        $user = $this->userAt(
            $this->branchA
        );

        $customer = $this->customerAt(
            $this->branchA
        );

        $payload = $this->cakePayload(
            $customer->id
        );

        $payload['origin_branch_id'] =
            $this->branchB->id;

        $order = app(
            SpecialCakeOrderService::class
        )->createOrder(
            $payload,
            $user
        );

        $this->assertSame(
            $this->branchA->id,
            (int) $order->origin_branch_id
        );
    }

    #[Test]
    public function admin_special_cake_create_page_requires_branch_selection(): void
    {
        $admin = $this->userAt(
            $this->factory,
            'Admin'
        );

        $this->actingAs($admin)
            ->get(
                route(
                    'cake-orders.create'
                )
            )
            ->assertOk()
            ->assertSee(
                'الفرع صاحب الطلب'
            )
            ->assertSee(
                'originBranchSelect',
                false
            )
            ->assertSee(
                $this->branchA->name
            )
            ->assertSee(
                $this->branchB->name
            );
    }

    private function cakePayload(
        int $customerId
    ): array {
        return [
            'customer_id' => $customerId,
            'required_date' =>
                now()
                    ->addDays(2)
                    ->toDateString(),
            'required_time' => '16:00',
            'cake_type' => 'chocolate',
            'cake_size' => 'medium',
            'shape' => 'round',
            'total_price' => 100,
            'discount_type' => 'none',
            'discount_value' => 0,
            'payment_arrangement' =>
                'pay_on_pickup',
        ];
    }

    private function customerAt(
        Location $location
    ): Customer {
        return Customer::query()->create([
            'location_id' => $location->id,
            'customer_type' =>
                Customer::TYPE_INDIVIDUAL,
            'scope' =>
                Customer::SCOPE_BRANCH,
            'name' =>
                'Launch Customer '
                . Str::random(5),
            'phone' =>
                '059'
                . random_int(
                    1000000,
                    9999999
                ),
            'allow_credit' => false,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);
    }

    private function userAt(
        Location $location,
        ?string $role = null
    ): User {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' =>
                false,
        ]);

        EmployeeLocation::query()->create([
            'employee_id' =>
                $user->employee_id,
            'location_id' =>
                $location->id,
            'is_primary' => true,
            'started_at' =>
                now()->toDateString(),
        ]);

        if ($role) {
            $user->assignRole(
                Role::findOrCreate(
                    $role,
                    'web'
                )
            );
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }

    private function location(
        string $name,
        string $type
    ): Location {
        return Location::query()->create([
            'name' => $name,
            'code' =>
                'LAUNCH-'
                . Str::upper(
                    Str::random(8)
                ),
            'type' => $type,
            'is_active' => true,
        ]);
    }
}
