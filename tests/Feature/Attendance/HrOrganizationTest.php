<?php

namespace Tests\Feature\Attendance;

use App\Models\Employee;
use App\Models\EmployeeCompensationProfile;
use App\Models\HrCostCenter;
use App\Models\HrDepartment;
use App\Models\HrPosition;
use App\Models\Location;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\HrOrganizationService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HrOrganizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function department_assignment_history_closes_previous_interval_and_blocks_cross_branch_data(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $worker = $this->employee($a, 'A Worker');
        $other = $this->employee($b, 'Secret B Worker');
        $manager = $this->user($a, ['hr.organization.view', 'hr.organization.manage']);
        $department = HrDepartment::query()->create([
            'code' => 'A-SALES', 'name' => 'مبيعات الفرع أ', 'location_id' => $a->id,
        ]);
        $foreign = HrDepartment::query()->create([
            'code' => 'B-SECRET', 'name' => 'قسم سري ب', 'location_id' => $b->id,
        ]);
        $position = HrPosition::query()->create([
            'code' => 'A-CASHIER', 'name' => 'كاشير', 'department_id' => $department->id,
        ]);
        $center = HrCostCenter::query()->create([
            'code' => 'A-CENTER', 'name' => 'صندوق أ', 'location_id' => $a->id,
        ]);

        $this->actingAs($manager)->get(route('hr.organization.index'))
            ->assertOk()->assertSee('مبيعات الفرع أ')->assertDontSee('قسم سري ب')
            ->assertDontSee('Secret B Worker')->assertSee('id="orgAssignment"', false);

        $payload = [
            'employee_id' => $worker->id, 'department_id' => $department->id,
            'position_id' => $position->id, 'cost_center_id' => $center->id,
            'effective_from' => '2026-02-01', 'reason' => 'تعيين أول',
        ];
        $this->actingAs($manager)->post(route('hr.assignments.store'), [
            ...$payload, 'employee_id' => $other->id,
        ])->assertNotFound();
        $this->actingAs($manager)->post(route('hr.assignments.store'), [
            ...$payload, 'department_id' => $foreign->id,
        ])->assertSessionHasErrors('department_id');
        $this->actingAs($manager)->post(route('hr.departments.store'), [
            'code' => 'GLOBAL-TRY', 'name' => 'Global', 'location_id' => $b->id,
        ])->assertForbidden();

        $this->actingAs($manager)->post(route('hr.assignments.store'), $payload)
            ->assertSessionHasNoErrors();
        $first = $worker->orgAssignments()->first();
        $this->assertNull($first->effective_to);
        $this->actingAs($manager)->post(route('hr.assignments.store'), [
            ...$payload, 'effective_from' => '2026-06-01', 'reason' => 'ترقية',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-05-31', $first->fresh()->effective_to->toDateString());
        $this->assertSame(2, $worker->orgAssignments()->count());
        $this->actingAs($manager)->post(route('hr.assignments.store'), $payload)
            ->assertSessionHasErrors('effective_from');

        $csv = $this->actingAs($manager)->get(route('hr.organization.csv', [
            'date' => '2026-03-01',
        ]))->assertOk()->streamedContent();
        $this->assertStringContainsString('A Worker', $csv);
        $this->assertStringNotContainsString('Secret B Worker', $csv);
    }

    #[Test]
    public function payroll_saves_org_snapshot_and_cost_report_respects_branch_scope(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $worker = $this->employee($a, 'Payroll Worker');
        $other = $this->employee($b, 'Hidden Payroll Worker');
        $manager = $this->user($a, ['hr.organization.view', 'hr.organization.manage', 'payroll.reports.view']);
        $department = HrDepartment::query()->create(['code' => 'PAY-A', 'name' => 'الإنتاج', 'location_id' => $a->id]);
        $otherDepartment = HrDepartment::query()->create(['code' => 'PAY-B', 'name' => 'القسم الجديد', 'location_id' => $a->id]);
        $center = HrCostCenter::query()->create(['code' => 'PAY-C', 'name' => 'إنتاج الفرع', 'location_id' => $a->id]);
        $foreignDepartment = HrDepartment::query()->create(['code' => 'PAY-X', 'name' => 'Private B', 'location_id' => $b->id]);
        $service = app(HrOrganizationService::class);
        $original = $service->assign($worker, [
            'department_id' => $department->id, 'cost_center_id' => $center->id,
            'effective_from' => '2026-01-01',
        ], $manager);
        $service->assign($other, [
            'department_id' => $foreignDepartment->id, 'effective_from' => '2026-01-01',
        ], $this->user($b, ['hr.organization.manage']));
        EmployeeCompensationProfile::query()->create([
            'employee_id' => $worker->id, 'salary_basis' => 'monthly',
            'base_salary' => 2500, 'effective_from' => '2026-01-01', 'is_active' => true,
        ]);
        $period = PayrollPeriod::query()->create([
            'code' => 'ORG-PAY-1', 'name' => 'October',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'draft',
        ]);
        $item = app(PayrollService::class)->calculateEmployee($period, $worker);
        $this->assertSame($original->id, $item->org_assignment_id);
        $service->assign($worker, [
            'department_id' => $otherDepartment->id, 'effective_from' => '2026-11-01',
        ], $manager);
        $this->assertSame($original->id, $item->fresh()->org_assignment_id);

        $this->actingAs($manager)->get(route('payroll.reports.index', [
            'department_id' => $department->id, 'cost_center_id' => $center->id,
        ]))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1)
            ->assertViewHas('costCenterSummary', fn ($groups) =>
                $groups->count() === 1 && (float) $groups->first()->net === 2500.0);
        $this->actingAs($manager)->get(route('payroll.reports.index', [
            'department_id' => $foreignDepartment->id,
        ]))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 0);
    }

    private function branch(string $suffix): Location
    {
        return Location::query()->create([
            'name' => 'Org Branch '.$suffix,
            'code' => 'ORG-'.$suffix.Str::upper(Str::random(5)),
            'type' => 'branch', 'is_active' => true,
        ]);
    }

    private function employee(Location $branch, string $name): Employee
    {
        $employee = Employee::query()->create([
            'employee_number' => 'ORG-'.Str::upper(Str::random(8)),
            'full_name' => $name, 'employment_status' => 'active',
        ]);
        $employee->locations()->attach($branch->id, [
            'is_primary' => true, 'started_at' => '2026-01-01',
        ]);
        return $employee;
    }

    private function user(Location $branch, array $permissions): User
    {
        $user = User::factory()->create([
            'employee_id' => $this->employee($branch, 'Org Manager')->id,
        ]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        return $user;
    }
}
