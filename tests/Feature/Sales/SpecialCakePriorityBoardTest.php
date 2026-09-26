<?php

namespace Tests\Feature\Sales;

use App\Enums\CakeOrderStatus;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Location;
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

class SpecialCakePriorityBoardTest extends TestCase
{
    use RefreshDatabase;

    private Location $branch;
    private Location $factory;
    private User $user;
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
            'Priority Branch',
            'branch'
        );

        $this->factory = $this->makeLocation(
            'Priority Factory',
            'factory'
        );

        $this->user = $this->makeAdmin(
            $this->branch
        );

        $this->customer = Customer::query()->create([
            'location_id' => $this->branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => 'Priority Customer',
            'phone' => '0599550001',
            'allow_credit' => false,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function todays_active_orders_are_grouped_by_time_and_nearest_slot_is_first(): void
    {
        $first = $this->makeOrder(
            'CK-1600-A',
            '2026-09-26',
            '16:00',
            CakeOrderStatus::InProgress
        );

        $second = $this->makeOrder(
            'CK-1600-B',
            '2026-09-26',
            '16:00',
            CakeOrderStatus::Ready
        );

        $third = $this->makeOrder(
            'CK-1700',
            '2026-09-26',
            '17:00',
            CakeOrderStatus::Pending
        );

        $this->makeOrder(
            'CK-DONE',
            '2026-09-26',
            '15:00',
            CakeOrderStatus::Completed
        );

        $response = $this->actingAs(
            $this->user
        )
            ->get(
                route('cake-orders.index')
            )
            ->assertOk();

        $groups = $response->viewData(
            'priorityGroups'
        );

        $this->assertSame(
            ['16:00', '17:00'],
            $groups
                ->keys()
                ->values()
                ->all()
        );

        $this->assertSame(
            [$first->id, $second->id],
            $groups
                ->get('16:00')
                ->pluck('id')
                ->all()
        );

        $this->assertSame(
            [$third->id],
            $groups
                ->get('17:00')
                ->pluck('id')
                ->all()
        );

        $response
            ->assertSee('أولوية تسليم اليوم')
            ->assertSee('متبقي 2 س')
            ->assertSee('CK-1600-A')
            ->assertSee('CK-1600-B')
            ->assertSee('CK-1700');
    }

    #[Test]
    public function seven_day_plan_counts_only_active_orders_day_by_day(): void
    {
        $this->makeOrder(
            'CK-TODAY',
            '2026-09-26',
            '16:00',
            CakeOrderStatus::InProgress
        );

        $this->makeOrder(
            'CK-TOMORROW-A',
            '2026-09-27',
            '11:00',
            CakeOrderStatus::Pending
        );

        $this->makeOrder(
            'CK-TOMORROW-B',
            '2026-09-27',
            '14:00',
            CakeOrderStatus::Ready
        );

        $this->makeOrder(
            'CK-DONE',
            '2026-09-27',
            '09:00',
            CakeOrderStatus::Completed
        );

        $response = $this->actingAs(
            $this->user
        )
            ->get(
                route('cake-orders.index')
            )
            ->assertOk();

        $plan = $response
            ->viewData('dailyPlan')
            ->keyBy('date');

        $this->assertSame(
            1,
            $plan['2026-09-26']['count']
        );

        $this->assertSame(
            2,
            $plan['2026-09-27']['count']
        );

        $summary = $response->viewData(
            'summary'
        );

        $this->assertSame(
            1,
            $summary['due_today']
        );

        $this->assertSame(
            2,
            $summary['due_tomorrow']
        );

        $response
            ->assertSee('طلبات بكرة')
            ->assertSee('تقرير إنتاج بكرة');
    }

    private function makeLocation(
        string $name,
        string $type
    ): Location {
        return Location::query()->create([
            'name' => $name,
            'code' =>
                'PR-' . Str::upper(
                    Str::random(8)
                ),
            'type' => $type,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(
        Location $location
    ): User {
        $employee = Employee::query()->create([
            'employee_number' =>
                'PR-EMP-' . Str::upper(
                    Str::random(8)
                ),
            'full_name' => 'Priority Admin',
            'employment_status' => 'active',
        ]);

        $employee
            ->locations()
            ->attach(
                $location->id,
                ['is_primary' => true]
            );

        $user = User::factory()->create([
            'employee_id' => $employee->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $role = Role::findOrCreate(
            'Admin',
            'web'
        );

        foreach ([
            'cake_orders.view',
            'reports.view',
        ] as $permissionName) {
            $role->givePermissionTo(
                Permission::findOrCreate(
                    $permissionName,
                    'web'
                )
            );
        }

        $user->assignRole($role);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }

    private function makeOrder(
        string $number,
        string $date,
        string $time,
        CakeOrderStatus $status
    ): SpecialCakeOrder {
        return SpecialCakeOrder::query()->create([
            'order_number' => $number,
            'customer_id' => $this->customer->id,
            'origin_branch_id' => $this->branch->id,
            'factory_location_id' => $this->factory->id,
            'required_date' => $date,
            'required_time' => $time,
            'cake_type' => 'chocolate',
            'cake_size' => 'medium',
            'total_price' => 100,
            'discount_type' => 'none',
            'discount_value' => 0,
            'discount_amount' => 0,
            'net_price' => 100,
            'status' => $status,
            'payment_status' => 'payment_pending',
            'payment_arrangement' => 'pay_on_pickup',
            'image_cover_type' => 'none',
            'created_by' => $this->user->id,
        ]);
    }
}
