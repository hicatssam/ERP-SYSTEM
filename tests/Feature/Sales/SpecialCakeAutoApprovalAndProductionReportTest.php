<?php

namespace Tests\Feature\Sales;

use App\Enums\CakeOrderStatus;
use App\Enums\ShowroomCakeRequestStatus;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomCakeRequestItem;
use App\Models\SpecialCakeOrder;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SpecialCakes\SpecialCakeStatusTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SpecialCakeAutoApprovalAndProductionReportTest extends TestCase
{
    use RefreshDatabase;

    private Location $branch;
    private Location $otherBranch;
    private Location $factory;
    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->branch = $this->makeLocation(
            'Main Branch',
            'branch'
        );

        $this->otherBranch = $this->makeLocation(
            'Other Branch',
            'branch'
        );

        $this->factory = $this->makeLocation(
            'Main Factory',
            'factory'
        );

        $this->user = $this->makeAdmin(
            $this->branch
        );

        $this->customer = Customer::query()->create([
            'location_id' => $this->branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => 'Cake Customer',
            'phone' => '0599001111',
            'allow_credit' => false,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);
    }

    #[Test]
    public function new_special_cake_is_auto_approved_when_branding_setting_is_enabled(): void
    {
        SystemSetting::set(
            'special_cake_auto_approval',
            '1'
        );

        $order = $this->makeSpecialCakeOrder(
            CakeOrderStatus::Draft->value,
            'بلاطة',
            '40x60',
            'مستطيل'
        );

        app(
            SpecialCakeStatusTransitionService::class
        )->submitCreatedOrder(
            $order,
            $this->user
        );

        $order->refresh();

        $this->assertSame(
            CakeOrderStatus::InProgress,
            $order->status
        );

        $this->assertDatabaseHas(
            'cake_order_status_histories',
            [
                'special_cake_order_id' => $order->id,
                'from_status' => 'draft',
                'to_status' => 'in_progress',
                'changed_by' => $this->user->id,
                'note' => 'تمت الموافقة تلقائيًا من النظام وبدأ تنفيذ الطلب.',
            ]
        );

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' => 'cake_order.auto_approved',
                'module' => 'special_cake_orders',
                'record_id' => $order->id,
            ]
        );
    }

    #[Test]
    public function new_special_cake_waits_for_manual_review_when_auto_approval_is_disabled(): void
    {
        SystemSetting::set(
            'special_cake_auto_approval',
            '0'
        );

        $order = $this->makeSpecialCakeOrder(
            CakeOrderStatus::Draft->value,
            'شوكولاتة',
            'وسط',
            'دائري'
        );

        app(
            SpecialCakeStatusTransitionService::class
        )->submitCreatedOrder(
            $order,
            $this->user
        );

        $order->refresh();

        $this->assertSame(
            CakeOrderStatus::Pending,
            $order->status
        );

        $this->assertDatabaseHas(
            'cake_order_status_histories',
            [
                'special_cake_order_id' => $order->id,
                'from_status' => 'draft',
                'to_status' => 'pending',
                'changed_by' => $this->user->id,
            ]
        );
    }

    #[Test]
    public function unified_cake_production_report_aggregates_special_and_branch_quantities(): void
    {
        $productionDate = '2026-09-28';

        $this->makeSpecialCakeOrder(
            CakeOrderStatus::InProgress->value,
            'بلاطة',
            '40x60',
            'مستطيل',
            $productionDate
        );

        $this->makeSpecialCakeOrder(
            CakeOrderStatus::Ready->value,
            'بلاطة',
            '40x60',
            'مستطيل',
            $productionDate
        );

        // Must be excluded.
        $this->makeSpecialCakeOrder(
            CakeOrderStatus::Cancelled->value,
            'بلاطة',
            '40x60',
            'مستطيل',
            $productionDate
        );

        $showroom = $this->makeShowroomRequest(
            $this->branch,
            ShowroomCakeRequestStatus::Submitted,
            $productionDate
        );

        ShowroomCakeRequestItem::query()->create([
            'showroom_cake_request_id' => $showroom->id,
            'cake_type' => 'بلاطة',
            'cake_size' => '40x60',
            'shape' => 'مستطيل',
            'quantity' => 3,
        ]);

        ShowroomCakeRequestItem::query()->create([
            'showroom_cake_request_id' => $showroom->id,
            'cake_type' => 'كيك',
            'cake_size' => 'صغير',
            'shape' => 'دائري',
            'quantity' => 2,
        ]);

        // Rejected branch demand must not affect production totals.
        $rejected = $this->makeShowroomRequest(
            $this->branch,
            ShowroomCakeRequestStatus::Rejected,
            $productionDate
        );

        ShowroomCakeRequestItem::query()->create([
            'showroom_cake_request_id' => $rejected->id,
            'cake_type' => 'بلاطة',
            'cake_size' => '40x60',
            'shape' => 'مستطيل',
            'quantity' => 9,
        ]);

        // Same cake in another branch must be excluded when branch filter is used.
        $otherBranchRequest = $this->makeShowroomRequest(
            $this->otherBranch,
            ShowroomCakeRequestStatus::Submitted,
            $productionDate
        );

        ShowroomCakeRequestItem::query()->create([
            'showroom_cake_request_id' => $otherBranchRequest->id,
            'cake_type' => 'بلاطة',
            'cake_size' => '40x60',
            'shape' => 'مستطيل',
            'quantity' => 10,
        ]);

        $response = $this->actingAs($this->user)
            ->get(
                route(
                    'reports.show',
                    'cake-production'
                )
                . '?date_from='
                . $productionDate
                . '&date_to='
                . $productionDate
                . '&location_id='
                . $this->branch->id
            )
            ->assertOk();

        $data = $response->viewData('data');
        $rows = collect($data->items());

        $slab = $rows->first(
            fn ($row) =>
                $row->cake_type === 'بلاطة'
                && $row->cake_size === '40x60'
                && $row->shape === 'مستطيل'
        );

        $this->assertNotNull($slab);
        $this->assertSame(2, (int) $slab->special_quantity);
        $this->assertSame(3, (int) $slab->showroom_quantity);
        $this->assertSame(5, (int) $slab->total_quantity);

        $small = $rows->first(
            fn ($row) =>
                $row->cake_type === 'كيك'
                && $row->cake_size === 'صغير'
                && $row->shape === 'دائري'
        );

        $this->assertNotNull($small);
        $this->assertSame(0, (int) $small->special_quantity);
        $this->assertSame(2, (int) $small->showroom_quantity);
        $this->assertSame(2, (int) $small->total_quantity);

        $this->assertSame(
            [
                'إجمالي قطع الكيك' => 7,
                'طلبات الكيك الخاصة' => 2,
                'كيك الفروع' => 5,
                'تشكيلات الإنتاج' => 2,
            ],
            $response->viewData('summary')
        );

        $this->actingAs($this->user)
            ->getJson(
                route(
                    'reports.count',
                    'cake-production'
                )
                . '?date_from='
                . $productionDate
                . '&date_to='
                . $productionDate
                . '&location_id='
                . $this->branch->id
            )
            ->assertOk()
            ->assertJsonPath(
                'count',
                2
            );
    }

    private function makeLocation(
        string $name,
        string $type
    ): Location {
        return Location::query()->create([
            'name' => $name,
            'code' => 'CK-' . Str::upper(
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
                'CK-EMP-' . Str::upper(
                    Str::random(8)
                ),
            'full_name' => 'Cake Admin',
            'employment_status' => 'active',
        ]);

        $employee->locations()->attach(
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

        $permission = Permission::findOrCreate(
            'reports.view',
            'web'
        );

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }

    private function makeSpecialCakeOrder(
        string $status,
        string $cakeType,
        string $cakeSize,
        string $shape,
        string $requiredDate = '2026-09-28'
    ): SpecialCakeOrder {
        return SpecialCakeOrder::query()->create([
            'order_number' =>
                'CK-TEST-' . Str::upper(
                    Str::random(8)
                ),
            'customer_id' => $this->customer->id,
            'origin_branch_id' => $this->branch->id,
            'factory_location_id' => $this->factory->id,
            'required_date' => $requiredDate,
            'cake_type' => $cakeType,
            'cake_size' => $cakeSize,
            'shape' => $shape,
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

    private function makeShowroomRequest(
        Location $branch,
        ShowroomCakeRequestStatus $status,
        string $neededBy
    ): ShowroomCakeRequest {
        return ShowroomCakeRequest::query()->create([
            'request_number' =>
                'SCR-TEST-' . Str::upper(
                    Str::random(8)
                ),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $this->factory->id,
            'status' => $status,
            'needed_by' => $neededBy,
            'created_by' => $this->user->id,
        ]);
    }
}
