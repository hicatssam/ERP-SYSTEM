<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeCompensationProfile;
use App\Models\EmployeeDocument;
use App\Models\EmployeePayrollAdjustment;
use App\Models\EmployeeShiftAssignment;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkHoliday;
use App\Models\WorkShift;
use App\Services\AttendanceService;
use App\Services\AttendancePayrollService;
use App\Services\EmployeeLeaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HrExpansionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_documents_are_private_and_branch_scoped(): void
    {
        Storage::fake('local');
        $a = $this->branch('A');
        $b = $this->branch('B');
        $own = $this->employee($a, 'Worker A');
        $other = $this->employee($b, 'Secret Worker B');
        $manager = $this->user($a, ['hr.documents.view', 'hr.documents.manage']);

        $this->actingAs($manager)->post(route('hr.employees.documents.store', $own), [
            'title' => 'عقد عمل', 'kind' => 'contract',
            'file' => UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf'),
            'expires_on' => now()->addDays(5)->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $document = EmployeeDocument::query()->sole();
        Storage::disk('local')->assertExists($document->path);
        $this->assertStringStartsWith('employee-documents/'.$own->id.'/', $document->path);
        $this->actingAs($manager)->get(route('hr.employees.documents.download', [$own, $document]))
            ->assertOk();
        $this->actingAs($manager)->get(route('hr.employees.file', $other))->assertForbidden();
        $this->actingAs($manager)->get(route('hr.employees.documents.download', [$other, $document]))
            ->assertForbidden();

        $viewer = $this->user($a, ['hr.documents.view']);
        $this->actingAs($viewer)->post(route('hr.employees.documents.store', $own), [
            'title' => 'Denied', 'kind' => 'other',
            'file' => UploadedFile::fake()->create('denied.pdf', 20, 'application/pdf'),
        ])->assertForbidden();
    }

    #[Test]
    public function scheduled_leave_excludes_branch_holidays_and_preserves_reserved_balance(): void
    {
        $a = $this->branch('A');
        $worker = $this->employee($a, 'Scheduled Worker');
        $shift = WorkShift::query()->create([
            'code' => 'MON-FRI', 'name' => 'Weekday',
            'location_id' => $a->id, 'start_time' => '08:00',
            'end_time' => '16:00', 'work_days' => [1, 2, 3, 4, 5],
        ]);
        EmployeeShiftAssignment::query()->create([
            'employee_id' => $worker->id, 'work_shift_id' => $shift->id,
            'effective_from' => '2026-01-01', 'is_primary' => true,
        ]);
        WorkHoliday::query()->create([
            'location_id' => $a->id, 'holiday_date' => '2026-10-06',
            'name' => 'عطلة الفرع',
        ]);
        $type = LeaveType::query()->create([
            'code' => 'WEEKDAY', 'name' => 'Scheduled leave',
            'is_paid' => false, 'annual_days' => 2, 'is_active' => true,
            'count_basis' => 'scheduled',
        ]);
        $actor = $this->user($a, []);
        $service = app(EmployeeLeaveService::class);
        $leave = $service->create($worker, $type, [
            'start_date' => '2026-10-05', 'end_date' => '2026-10-07',
        ], $actor->id);

        $this->assertEquals(2, $leave->total_days);
        $this->assertSame(2, $leave->yearly_days[2026]);
        $this->assertEquals(0, $service->balances($worker, [$type], 2026)[$type->id]['available']);

        WorkHoliday::query()->delete();
        app(AttendanceService::class)->approveLeave($leave, $actor);
        $this->assertEquals(2, $leave->fresh()->total_days);
        $this->assertSame(2, AttendanceRecord::query()->where('employee_id', $worker->id)->count());
    }

    #[Test]
    public function payroll_pages_reports_documents_and_global_actions_respect_scope(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $own = $this->employee($a, 'Worker A');
        $private = $this->employee($b, 'Private Payroll B');
        $manager = $this->user($a, [
            'payroll.view', 'payroll.reports.view', 'payroll.documents.print',
            'payroll.manage', 'payroll.approve',
        ]);
        $period = PayrollPeriod::query()->create([
            'code' => 'HR-SCOPE-1', 'name' => 'October Pay',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
            'status' => 'calculated',
        ]);
        $mine = PayrollItem::query()->create([
            'payroll_period_id' => $period->id, 'employee_id' => $own->id,
            'base_salary' => 100, 'net_salary' => 100, 'status' => 'calculated',
        ]);
        $theirs = PayrollItem::query()->create([
            'payroll_period_id' => $period->id, 'employee_id' => $private->id,
            'base_salary' => 9000, 'net_salary' => 9000, 'status' => 'calculated',
        ]);

        $this->actingAs($manager)->get(route('payroll.show', $period))
            ->assertOk()->assertSee('Worker A')->assertDontSee('Private Payroll B')
            ->assertDontSee('9,000');
        $this->actingAs($manager)->get(route('payroll.reports.index'))
            ->assertOk()->assertSee('Worker A')->assertDontSee('Private Payroll B');
        $this->actingAs($manager)->get(route('payroll.payslip', $theirs))->assertForbidden();
        $this->actingAs($manager)->get(route('payroll.payslip', $mine))->assertOk();
        $this->actingAs($manager)->post(route('payroll.approve', $period))->assertForbidden();
        $this->assertSame('calculated', $period->fresh()->status);
    }

    #[Test]
    public function monthly_accrual_and_manual_carryover_are_limited_and_branch_scoped(): void
    {
        Carbon::setTestNow('2026-10-01 12:00:00');
        try {
            SystemSetting::set('attendance_enabled', 1);
            $a = $this->branch('A');
            $b = $this->branch('B');
            $worker = $this->employee($a, 'Earned Leave');
            $worker->update(['hire_date' => '2025-01-01']);
            $foreign = $this->employee($b, 'Other Branch');
            $manager = $this->user($a, ['attendance.leaves.manage']);
            $monthly = LeaveType::query()->create([
                'code' => 'MONTHLY-HR', 'name' => 'Monthly', 'is_paid' => true,
                'is_active' => true, 'annual_days' => 12, 'accrual_mode' => 'monthly',
            ]);
            $service = app(EmployeeLeaveService::class);
            $this->assertEquals(9, $service->balances($worker, [$monthly], 2026)[$monthly->id]['available']);

            $annual = LeaveType::query()->create([
                'code' => 'CARRY-HR', 'name' => 'Carry', 'is_paid' => true,
                'is_active' => true, 'annual_days' => 4, 'carryover_limit_days' => 2,
            ]);
            $service->grantCarryover($worker, $annual, 2026, 2, $manager->id, 'قرار إداري');
            $this->assertEquals(6, $service->balances($worker, [$annual], 2026)[$annual->id]['available']);
            $this->actingAs($manager)->post(route('attendance.leaves.carryover'), [
                'employee_id' => $foreign->id, 'leave_type_id' => $annual->id,
                'year' => 2026, 'days' => 1,
            ])->assertForbidden();
            $this->actingAs($manager)->post(route('attendance.leaves.carryover'), [
                'employee_id' => $worker->id, 'leave_type_id' => $annual->id,
                'year' => 2026, 'days' => 3,
            ])->assertSessionHasErrors('days');
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function approved_unpaid_half_day_preserves_worked_attendance_and_deducts_only_half_day(): void
    {
        $branch = $this->branch('A');
        $worker = $this->employee($branch, 'Half Day Worker');
        $actor = $this->user($branch, []);
        $type = LeaveType::query()->create([
            'code' => 'HALF-HR', 'name' => 'Half Day', 'is_paid' => false,
            'annual_days' => 2, 'is_active' => true,
        ]);
        EmployeeCompensationProfile::query()->create([
            'employee_id' => $worker->id, 'salary_basis' => 'monthly',
            'base_salary' => 2600, 'effective_from' => '2026-01-01', 'is_active' => true,
        ]);
        $shift = WorkShift::query()->create([
            'code' => 'HALF-SHIFT', 'name' => 'Half shift',
            'start_time' => '08:00', 'end_time' => '16:00', 'work_days' => [1, 2, 3, 4, 5],
        ]);
        EmployeeShiftAssignment::query()->create([
            'employee_id' => $worker->id, 'work_shift_id' => $shift->id,
            'effective_from' => '2026-01-01', 'is_primary' => true,
        ]);
        $day = '2026-10-05';
        AttendanceRecord::query()->create([
            'employee_id' => $worker->id, 'work_date' => $day,
            'status' => 'present', 'source' => 'manual',
            'check_in_at' => $day.' 12:00:00', 'late_minutes' => 60,
            'approved_at' => now(), 'approved_by' => $actor->id,
        ]);
        $leave = app(EmployeeLeaveService::class)->create($worker, $type, [
            'start_date' => $day, 'end_date' => $day, 'day_fraction' => 0.5,
            'half_day_slot' => 'first_half',
        ], $actor->id);
        app(AttendanceService::class)->approveLeave($leave, $actor);
        $this->assertEquals(0.5, $leave->fresh()->total_days);
        $this->assertSame('present', AttendanceRecord::query()->where('employee_id', $worker->id)->sole()->status);
        $type->update(['is_paid' => true]);
        $period = PayrollPeriod::query()->create([
            'code' => 'HALF-PAY', 'name' => 'Half Day Pay',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'draft',
        ]);
        app(AttendancePayrollService::class)->sync($period, $actor);
        $deduction = EmployeePayrollAdjustment::query()->sole();
        $this->assertEquals(50, $deduction->amount);
        $this->assertEquals(0.5, $deduction->metadata['unpaid_leave_days']);
    }

    private function branch(string $suffix): Location
    {
        return Location::query()->create([
            'name' => 'HR Branch '.$suffix,
            'code' => 'HR-EXP-'.$suffix.Str::upper(Str::random(4)),
            'type' => 'branch', 'is_active' => true,
        ]);
    }

    private function employee(Location $branch, string $name): Employee
    {
        $employee = Employee::query()->create([
            'employee_number' => 'HR-EXP-'.Str::upper(Str::random(8)),
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
            'employee_id' => $this->employee($branch, 'HR Manager')->id,
        ]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        return $user;
    }
}
