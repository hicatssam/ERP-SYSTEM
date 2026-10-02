<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeSelfAttendanceRequest;
use App\Models\EmployeeShiftAssignment;
use App\Models\HrDepartment;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HrIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-02 12:00:00');
        SystemSetting::set('attendance_enabled', 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function editing_and_moving_an_employee_preserves_branch_history_and_restricts_old_manager(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $worker = $this->employee($a, 'Transfer Worker');
        $first = $worker->employeeLocations()->sole();
        $admin = $this->user($a, ['employees.manage', 'employees.update', 'employees.view_all']);
        $oldManager = $this->user($a, ['employees.manage', 'employees.view', 'employee_ledger.view', 'attendance.view']);
        $newManager = $this->user($b, ['employees.manage', 'employees.view', 'employee_ledger.view', 'attendance.view']);

        $this->actingAs($admin)->put(route('employees.update', $worker), [
            'full_name' => 'Transfer Worker Updated', 'employment_status' => 'active',
            'primary_location_id' => $a->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($first->id, $worker->employeeLocations()->sole()->id);
        $this->assertNull($first->fresh()->ended_at);

        $this->actingAs($admin)->put(route('employees.update', $worker), [
            'full_name' => 'Transfer Worker Updated', 'employment_status' => 'active',
            'primary_location_id' => $b->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-01', $first->fresh()->ended_at->toDateString());
        $this->assertTrue($first->fresh()->is_primary);
        $this->assertSame(2, $worker->employeeLocations()->count());
        $this->assertSame($b->id, $worker->primaryLocation()->id);

        $this->actingAs($oldManager)->get(route('employees.index'))
            ->assertOk()->assertDontSee('Transfer Worker Updated');
        $this->actingAs($oldManager)->get(route('employees.show', $worker))->assertForbidden();
        $this->actingAs($oldManager)->get(route('payroll.employees.show', $worker))->assertForbidden();
        $this->actingAs($oldManager)->get(route('attendance.index'))
            ->assertOk()->assertDontSee('Transfer Worker Updated');
        $this->actingAs($newManager)->get(route('employees.show', $worker))->assertOk();
        $this->actingAs($newManager)->get(route('payroll.employees.show', $worker))->assertOk();
        $this->actingAs($newManager)->get(route('attendance.index'))
            ->assertOk()->assertSee('Transfer Worker Updated');

        Carbon::setTestNow('2026-10-03 12:00:00');
        $this->actingAs($admin)->put(route('employees.update', $worker), [
            'full_name' => 'Transfer Worker Updated', 'employment_status' => 'active',
            'primary_location_id' => $a->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, $worker->employeeLocations()->count());
        $this->assertSame('2026-10-02', $worker->employeeLocations()->where('location_id', $b->id)
            ->sole()->ended_at->toDateString());
        $this->assertSame($a->id, $worker->primaryLocation()->id);
    }

    #[Test]
    public function a_same_day_branch_correction_does_not_create_a_reversed_date_interval(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $c = $this->branch('C');
        $worker = $this->employee($a, 'Corrected Transfer');
        $admin = $this->user($a, ['employees.manage', 'employees.update', 'employees.view_all']);
        foreach ([$b, $c] as $branch) {
            $this->actingAs($admin)->put(route('employees.update', $worker), [
                'full_name' => $worker->full_name, 'employment_status' => 'active',
                'primary_location_id' => $branch->id,
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(2, $worker->employeeLocations()->count());
        $this->assertSame('2026-10-02', $worker->employeeLocations()
            ->where('location_id', $c->id)->sole()->started_at->toDateString());
        $this->assertNull($worker->employeeLocations()->where('location_id', $c->id)->sole()->ended_at);
        $this->assertSame($c->id, $worker->primaryLocation()->id);
    }

    #[Test]
    public function global_employee_directory_permission_does_not_grant_cross_branch_attendance_management(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $foreign = $this->employee($b, 'Restricted Shift Worker');
        $manager = $this->user($a, [
            'employees.view_all', 'attendance.view', 'attendance.manage',
            'attendance.shifts.manage',
        ]);
        $shift = $this->shift($a, 'LOCAL-MANAGER-SHIFT');

        $this->actingAs($manager)->get(route('attendance.index'))
            ->assertOk()->assertDontSee('Restricted Shift Worker');
        $this->actingAs($manager)->get(route('attendance.shifts.index'))
            ->assertOk()->assertDontSee('Restricted Shift Worker');
        $this->actingAs($manager)->post(route('attendance.store', $foreign), [
            'work_date' => '2026-10-02', 'status' => 'present',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('attendance.shifts.assign'), [
            'employee_id' => $foreign->id, 'work_shift_id' => $shift->id,
            'effective_from' => '2026-10-02',
        ])->assertNotFound();
    }

    #[Test]
    public function dated_shift_changes_keep_past_schedule_and_reject_overlaps_or_other_branches(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $worker = $this->employee($a, 'Shift Worker');
        $manager = $this->user($a, ['attendance.shifts.manage', 'attendance.view']);
        $firstShift = $this->shift($a, 'MORNING');
        $secondShift = $this->shift($a, 'EVENING');
        $foreignShift = $this->shift($b, 'FOREIGN');
        $old = EmployeeShiftAssignment::query()->create([
            'employee_id' => $worker->id, 'work_shift_id' => $firstShift->id,
            'effective_from' => '2026-01-01', 'is_primary' => true,
        ]);

        $this->actingAs($manager)->post(route('attendance.shifts.assign'), [
            'employee_id' => $worker->id, 'work_shift_id' => $secondShift->id,
            'effective_from' => '2026-11-01', 'effective_to' => '2026-11-30',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($old->fresh()->is_primary);
        $this->assertSame('2026-10-31', $old->fresh()->effective_to->toDateString());
        $this->assertSame($firstShift->id, app(AttendanceService::class)->shiftFor($worker, '2026-10-02')->id);
        $this->assertSame($secondShift->id, app(AttendanceService::class)->shiftFor($worker, '2026-11-02')->id);
        $this->assertNull(app(AttendanceService::class)->shiftFor($worker, '2026-12-02'));
        $this->actingAs($manager)->get(route('attendance.shifts.index'))
            ->assertOk()->assertSee('يبدأ لاحقًا');

        $this->actingAs($manager)->post(route('attendance.shifts.assign'), [
            'employee_id' => $worker->id, 'work_shift_id' => $firstShift->id,
            'effective_from' => '2026-10-15',
        ])->assertSessionHasErrors('effective_from');
        $this->actingAs($manager)->post(route('attendance.shifts.assign'), [
            'employee_id' => $worker->id, 'work_shift_id' => $foreignShift->id,
            'effective_from' => '2026-12-01',
        ])->assertNotFound();

        $worker->employeeLocations()->where('location_id', $a->id)->update(['ended_at' => '2026-12-31']);
        $worker->employeeLocations()->create([
            'location_id' => $b->id, 'is_primary' => true, 'started_at' => '2027-01-01',
        ]);
        $this->actingAs($manager)->post(route('attendance.shifts.assign'), [
            'employee_id' => $worker->id, 'work_shift_id' => $firstShift->id,
            'effective_from' => '2027-01-02',
        ])->assertSessionHasErrors('work_shift_id');
        $this->assertSame(2, EmployeeShiftAssignment::query()->where('employee_id', $worker->id)->count());
    }

    #[Test]
    public function payroll_approval_waits_for_leave_correction_and_self_attendance_decisions(): void
    {
        $branch = $this->branch('A');
        $worker = $this->employee($branch, 'Payroll Worker');
        $approver = $this->user($branch, ['payroll.approve', 'employees.view_all']);
        $period = PayrollPeriod::query()->create([
            'code' => 'HR-INTEGRITY-OCT', 'name' => 'October',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'calculated',
        ]);
        PayrollItem::query()->create([
            'payroll_period_id' => $period->id, 'employee_id' => $worker->id,
            'base_salary' => 1000, 'net_salary' => 1000, 'status' => 'calculated',
        ]);
        $type = LeaveType::query()->create(['code' => 'HR-PENDING', 'name' => 'Annual']);
        $leave = EmployeeLeaveRequest::query()->create([
            'employee_id' => $worker->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-10-07', 'end_date' => '2026-10-07',
            'total_days' => 1, 'status' => 'pending',
        ]);
        $correction = AttendanceCorrectionRequest::query()->create([
            'employee_id' => $worker->id, 'work_date' => '2026-10-02',
            'reason' => 'نسيت تسجيل الانصراف', 'requested_by' => $approver->id,
            'status' => 'pending',
        ]);
        $self = EmployeeSelfAttendanceRequest::query()->create([
            'employee_id' => $worker->id, 'location_id' => $branch->id,
            'work_date' => '2026-10-03', 'check_in_at' => '2026-10-03 08:00:00',
            'status' => 'pending',
        ]);

        $this->actingAs($approver)->post(route('payroll.approve', $period))
            ->assertSessionHasErrors('payroll');
        $this->assertSame('calculated', $period->fresh()->status);
        $leave->update(['status' => 'rejected']);
        $this->actingAs($approver)->post(route('payroll.approve', $period))
            ->assertSessionHasErrors('payroll');
        $correction->update(['status' => 'rejected']);
        $this->actingAs($approver)->post(route('payroll.approve', $period))
            ->assertSessionHasErrors('payroll');
        $self->update(['status' => 'rejected']);
        $this->actingAs($approver)->post(route('payroll.approve', $period))
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $period->fresh()->status);
    }

    #[Test]
    public function manual_attendance_cannot_replace_approved_leave_or_change_closed_payroll(): void
    {
        $branch = $this->branch('A');
        $worker = $this->employee($branch, 'Protected Attendance');
        $manager = $this->user($branch, ['attendance.manage', 'attendance.approve']);
        $type = LeaveType::query()->create(['code' => 'HR-APPROVED', 'name' => 'Paid']);
        EmployeeLeaveRequest::query()->create([
            'employee_id' => $worker->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-10-02', 'end_date' => '2026-10-02',
            'total_days' => 1, 'day_fraction' => 1, 'status' => 'approved',
        ]);
        $this->actingAs($manager)->post(route('attendance.store', $worker), [
            'work_date' => '2026-10-02', 'status' => 'present',
        ])->assertSessionHasErrors('work_date');
        $this->assertDatabaseCount('attendance_records', 0);

        $record = AttendanceRecord::query()->create([
            'employee_id' => $worker->id, 'work_date' => '2026-09-30',
            'status' => 'absent', 'source' => 'manual',
        ]);
        PayrollPeriod::query()->create([
            'code' => 'HR-LOCK-SEP', 'name' => 'September',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
            'status' => 'approved',
        ]);
        $this->actingAs($manager)->post(route('attendance.store', $worker), [
            'work_date' => '2026-09-30', 'status' => 'present',
        ])->assertSessionHasErrors('start_date');
        $this->actingAs($manager)->post(route('attendance.approve', $record))
            ->assertSessionHasErrors('start_date');
        $this->assertSame('absent', $record->fresh()->status);
        $this->assertNull($record->fresh()->approved_at);
    }

    #[Test]
    public function organizational_assignment_uses_the_branch_on_its_effective_date(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $worker = $this->employee($a, 'Future Transfer');
        $manager = $this->user($a, ['hr.organization.manage']);
        $admin = $this->user($a, ['hr.organization.manage', 'employees.view_all']);
        $worker->employeeLocations()->where('location_id', $a->id)
            ->update(['ended_at' => '2026-12-31']);
        $worker->employeeLocations()->create([
            'location_id' => $b->id, 'is_primary' => true,
            'started_at' => '2027-01-01',
        ]);
        $deptA = HrDepartment::query()->create([
            'code' => 'INT-ORG-A', 'name' => 'Old department', 'location_id' => $a->id,
        ]);
        $deptB = HrDepartment::query()->create([
            'code' => 'INT-ORG-B', 'name' => 'New department', 'location_id' => $b->id,
        ]);

        $this->actingAs($manager)->post(route('hr.assignments.store'), [
            'employee_id' => $worker->id, 'department_id' => $deptA->id,
            'effective_from' => '2026-10-02',
        ])->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('hr.assignments.store'), [
            'employee_id' => $worker->id, 'department_id' => $deptB->id,
            'effective_from' => '2027-01-01',
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('hr.assignments.store'), [
            'employee_id' => $worker->id, 'department_id' => $deptB->id,
            'effective_from' => '2027-01-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame($deptA->id, $worker->orgAssignments()->whereDate('effective_from', '2026-10-02')
            ->sole()->department_id);
        $this->assertSame($deptB->id, $worker->orgAssignments()->whereDate('effective_from', '2027-01-01')
            ->sole()->department_id);

        $oldShift = $this->shift($a, 'OLD-BRANCH-SHIFT');
        EmployeeShiftAssignment::query()->create([
            'employee_id' => $worker->id, 'work_shift_id' => $oldShift->id,
            'effective_from' => '2026-01-01', 'is_primary' => true,
        ]);
        $this->assertNull(app(AttendanceService::class)->shiftFor($worker, '2027-01-02'));
    }

    #[Test]
    public function hr_overview_hides_leave_and_review_details_without_their_permissions(): void
    {
        $branch = $this->branch('A');
        $worker = $this->employee($branch, 'Review Worker');
        $viewer = $this->user($branch, ['hr.dashboard.view']);
        $type = LeaveType::query()->create(['code' => 'OVERVIEW-LEAVE', 'name' => 'Annual']);
        EmployeeLeaveRequest::query()->create([
            'employee_id' => $worker->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-10-08', 'end_date' => '2026-10-08',
            'total_days' => 1, 'status' => 'pending',
        ]);
        AttendanceCorrectionRequest::query()->create([
            'employee_id' => $worker->id, 'work_date' => '2026-10-01',
            'requested_by' => $viewer->id, 'reason' => 'Forgot', 'status' => 'pending',
        ]);
        EmployeeSelfAttendanceRequest::query()->create([
            'employee_id' => $worker->id, 'location_id' => $branch->id,
            'work_date' => '2026-10-02', 'check_in_at' => now(), 'status' => 'pending',
        ]);

        $this->actingAs($viewer)->get(route('hr.dashboard'))
            ->assertOk()->assertDontSee('طلبات الإجازة')->assertDontSee('تصحيحات الحضور')
            ->assertDontSee('تسجيلات الدوام الذاتية')
            ->assertViewHas('leaveCount', 0)->assertViewHas('correctionCount', 0);
        $viewer->givePermissionTo(Permission::findOrCreate('attendance.leaves.view', 'web'));
        $viewer->givePermissionTo(Permission::findOrCreate('attendance.approve', 'web'));
        $this->actingAs($viewer)->get(route('hr.dashboard'))
            ->assertOk()->assertSee('طلبات الإجازة')->assertSee('تصحيحات الحضور')
            ->assertSee('تسجيلات الدوام الذاتية')
            ->assertViewHas('leaveCount', 1)->assertViewHas('correctionCount', 1);
    }

    private function branch(string $suffix): Location
    {
        return Location::query()->create([
            'name' => 'Integrity '.$suffix,
            'code' => 'INTEGRITY-'.$suffix.Str::upper(Str::random(5)),
            'type' => 'branch', 'is_active' => true,
        ]);
    }

    private function employee(Location $branch, string $name): Employee
    {
        $employee = Employee::query()->create([
            'employee_number' => 'INTEGRITY-'.Str::upper(Str::random(8)),
            'full_name' => $name, 'employment_status' => 'active',
        ]);
        $employee->employeeLocations()->create([
            'location_id' => $branch->id, 'is_primary' => true, 'started_at' => '2026-01-01',
        ]);
        return $employee;
    }

    private function shift(Location $branch, string $code): WorkShift
    {
        return WorkShift::query()->create([
            'code' => $code, 'name' => $code,
            'location_id' => $branch->id, 'start_time' => '08:00',
            'end_time' => '16:00', 'is_active' => true,
            'work_days' => [1, 2, 3, 4, 5, 6, 7],
        ]);
    }

    private function user(Location $branch, array $permissions): User
    {
        $user = User::factory()->create([
            'employee_id' => $this->employee($branch, 'Manager')->id,
        ]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        return $user;
    }
}
