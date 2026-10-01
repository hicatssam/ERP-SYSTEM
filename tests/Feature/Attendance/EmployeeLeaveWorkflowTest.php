<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeShiftAssignment;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeLeaveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('attendance_enabled', 1);
    }

    #[Test]
    public function branch_manager_only_sees_and_manages_leave_for_own_staff(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $manager = $this->manager($a);
        $own = $this->employee($a, 'Own Leave Employee');
        $other = $this->employee($b, 'Private Leave Employee');
        $type = $this->type();

        $ownLeave = $this->leave($own, $type, '2026-05-04');
        $otherLeave = $this->leave($other, $type, '2026-05-05');

        $this->actingAs($manager)
            ->get(route('attendance.leaves.index'))
            ->assertOk()
            ->assertSee('Own Leave Employee')
            ->assertDontSee('Private Leave Employee')
            ->assertViewHas('totals', fn ($totals) => (int) $totals->sum('request_count') === 1);

        $this->actingAs($manager)
            ->get(route('attendance.leaves.index', ['employee_id' => $other->id]))
            ->assertNotFound();

        $this->actingAs($manager)
            ->post(route('attendance.leaves.store'), $this->payload($other, $type, '2026-05-07'))
            ->assertForbidden();
        $this->actingAs($manager)
            ->post(route('attendance.leaves.approve', $otherLeave))
            ->assertForbidden();
        $this->actingAs($manager)
            ->post(route('attendance.leaves.reject', $otherLeave))
            ->assertForbidden();

        $this->assertSame('pending', $otherLeave->fresh()->status);
        $this->assertSame('pending', $ownLeave->fresh()->status);
    }

    #[Test]
    public function requests_reserve_calendar_year_allowance_and_rejected_days_are_released(): void
    {
        $employee = $this->employee($this->branch('A'), 'Balance Employee');
        $manager = $this->manager($employee->primaryLocation());
        $type = $this->type(2);

        $this->actingAs($manager)
            ->post(route('attendance.leaves.store'), $this->payload($employee, $type, '2026-12-31', '2027-01-01'))
            ->assertSessionHasNoErrors();

        $this->actingAs($manager)
            ->post(route('attendance.leaves.store'), $this->payload($employee, $type, '2027-01-01'))
            ->assertSessionHasErrors('start_date');

        $this->actingAs($manager)
            ->post(route('attendance.leaves.store'), $this->payload($employee, $type, '2027-01-02'))
            ->assertSessionHasNoErrors();

        $this->actingAs($manager)
            ->post(route('attendance.leaves.store'), $this->payload($employee, $type, '2027-01-03'))
            ->assertSessionHasErrors('end_date');

        $this->actingAs($manager)
            ->get(route('attendance.leaves.index', ['employee_id' => $employee->id, 'year' => 2027]))
            ->assertOk()
            ->assertViewHas('balances', fn ($balances) => $balances[$type->id] === [
                'approved' => 0, 'pending' => 2, 'available' => 0.0,
            ]);

        $second = EmployeeLeaveRequest::query()->whereDate('start_date', '2027-01-02')->sole();
        $this->actingAs($manager)
            ->post(route('attendance.leaves.reject', $second))
            ->assertSessionHasNoErrors();
        $this->actingAs($manager)
            ->post(route('attendance.leaves.store'), $this->payload($employee, $type, '2027-01-03'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('employee_leave_requests', 3);
    }

    #[Test]
    public function approval_never_erases_a_recorded_shift_and_creates_leave_after_conflict_is_resolved(): void
    {
        $branch = $this->branch('A');
        $employee = $this->employee($branch, 'Shift Employee');
        $manager = $this->manager($branch);
        $type = $this->type();
        $leave = $this->leave($employee, $type, '2026-02-02');

        $shift = WorkShift::query()->create([
            'code' => 'TEST-SHIFT',
            'name' => 'Morning',
            'location_id' => $branch->id,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'work_days' => [1, 2, 3, 4, 5, 6, 7],
        ]);
        EmployeeShiftAssignment::query()->create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'effective_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        $record = AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-02-02',
            'status' => 'present',
            'source' => 'manual',
            'check_in_at' => '2026-02-02 08:00:00',
        ]);

        $this->actingAs($manager)
            ->post(route('attendance.leaves.approve', $leave))
            ->assertSessionHasErrors('leave');

        $this->assertSame('pending', $leave->fresh()->status);
        $this->assertSame('present', $record->fresh()->status);
        $this->assertNotNull($record->fresh()->check_in_at);

        $record->delete();
        $this->actingAs($manager)
            ->post(route('attendance.leaves.approve', $leave))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $employee->id,
            'status' => 'leave',
            'source' => 'leave',
        ]);

        $this->actingAs($manager)
            ->post(route('attendance.leaves.approve', $leave))
            ->assertSessionHasErrors('leave');
    }

    #[Test]
    public function filtered_statistics_cover_all_pages_without_leaking_other_branches(): void
    {
        $branch = $this->branch('A');
        $manager = $this->manager($branch);
        $employee = $this->employee($branch, 'Pagination Employee');
        $other = $this->employee($this->branch('B'), 'Hidden Employee');
        $type = $this->type();

        for ($day = 1; $day <= 31; $day++) {
            $this->leave($employee, $type, sprintf('2026-05-%02d', $day));
        }
        $this->leave($other, $type, '2026-06-01');

        $this->actingAs($manager)
            ->get(route('attendance.leaves.index', ['year' => 2026, 'status' => 'pending']))
            ->assertOk()
            ->assertViewHas('totals', fn ($totals) => (int) $totals->sum('request_count') === 31)
            ->assertViewHas('requests', fn ($requests) => $requests->total() === 31 && $requests->count() === 30);
    }

    #[Test]
    public function approval_updates_balance_and_counters_even_while_list_is_filtered_to_pending(): void
    {
        $branch = $this->branch('A');
        $employee = $this->employee($branch, 'Approved Worker');
        $manager = $this->manager($branch);
        $type = $this->type(3);

        $this->actingAs($manager)->post(route('attendance.leaves.store'),
            $this->payload($employee, $type, '2026-10-05'))
            ->assertSessionHasNoErrors();
        $leave = EmployeeLeaveRequest::query()->sole();

        $this->actingAs($manager)->post(route('attendance.leaves.approve', $leave))
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertNotNull($leave->fresh()->approved_at);

        $this->actingAs($manager)->get(route('attendance.leaves.index', [
            'employee_id' => $employee->id, 'year' => 2026, 'status' => 'pending',
        ]))->assertOk()
            ->assertViewHas('totals', fn ($totals) =>
                (int) ($totals->get('approved')?->request_count ?? 0) === 1
                && (int) ($totals->get('pending')?->request_count ?? 0) === 0)
            ->assertViewHas('requests', fn ($requests) => $requests->total() === 0)
            ->assertViewHas('balances', fn ($balances) => $balances[$type->id] === [
                'approved' => 1, 'pending' => 0, 'available' => 2.0,
            ]);
    }

    #[Test]
    public function year_filter_counts_only_days_in_that_year_and_rejection_releases_balance(): void
    {
        $employee = $this->employee($this->branch('A'), 'Cross-year Worker');
        $manager = $this->manager($employee->primaryLocation());
        $type = $this->type(5);
        $this->actingAs($manager)->post(route('attendance.leaves.store'),
            $this->payload($employee, $type, '2026-12-31', '2027-01-02'))
            ->assertSessionHasNoErrors();
        $leave = EmployeeLeaveRequest::query()->sole();
        $this->actingAs($manager)->post(route('attendance.leaves.approve', $leave))
            ->assertSessionHasNoErrors();
        $this->actingAs($manager)->get(route('attendance.leaves.index', [
            'employee_id' => $employee->id, 'year' => 2027,
        ]))->assertOk()->assertViewHas('totalLeaveDays', fn ($days) => $days === 2.0)
            ->assertViewHas('balances', fn ($balances) => $balances[$type->id]['approved'] === 2);

        $second = $this->leave($employee, $type, '2027-02-01');
        $this->actingAs($manager)->post(route('attendance.leaves.reject', $second))
            ->assertSessionHasNoErrors();
        $this->actingAs($manager)->get(route('attendance.leaves.index', [
            'employee_id' => $employee->id, 'year' => 2027, 'status' => 'pending',
        ]))->assertOk()->assertViewHas('totals', fn ($totals) =>
            (int) ($totals->get('approved')?->request_count ?? 0) === 1
            && (int) ($totals->get('rejected')?->request_count ?? 0) === 1)
            ->assertViewHas('balances', fn ($balances) => $balances[$type->id]['pending'] === 0);
    }

    #[Test]
    public function only_settings_managers_can_configure_leave_types_and_disabled_types_cannot_be_requested(): void
    {
        $branch = $this->branch('A');
        $employee = $this->employee($branch, 'Policy Employee');
        $manager = $this->manager($branch);

        $this->actingAs($manager)
            ->get(route('attendance.leave-types.index'))
            ->assertForbidden();

        $manager->givePermissionTo(Permission::findOrCreate('settings.manage', 'web'));

        $this->actingAs($manager)
            ->post(route('attendance.leave-types.store'), [
                'code' => 'annual',
                'name' => 'Annual',
                'annual_days' => 5,
                'is_paid' => 1,
                'is_active' => 1,
            ])->assertSessionHasNoErrors();

        $type = LeaveType::query()->sole();
        $this->assertSame('ANNUAL', $type->code);

        $this->actingAs($manager)
            ->put(route('attendance.leave-types.update', $type), [
                'code' => 'annual',
                'name' => 'Annual',
                'annual_days' => 5,
                'is_paid' => 1,
                'is_active' => 0,
            ])->assertSessionHasNoErrors();

        $this->assertFalse($type->fresh()->is_active);
        $this->actingAs($manager)
            ->post(route('attendance.leaves.store'), $this->payload($employee, $type, '2026-04-04'))
            ->assertSessionHasErrors('leave_type_id');
    }

    private function branch(string $suffix): Location
    {
        return Location::query()->create([
            'name' => 'Leave Branch ' . $suffix,
            'code' => 'LEAVE-' . $suffix . Str::upper(Str::random(4)),
            'type' => 'branch',
            'is_active' => true,
        ]);
    }

    private function employee(Location $branch, string $name): Employee
    {
        $employee = Employee::query()->create([
            'employee_number' => 'LEAVE-' . Str::upper(Str::random(8)),
            'full_name' => $name,
            'employment_status' => 'active',
        ]);

        $employee->locations()->attach($branch->id, ['is_primary' => true]);

        return $employee;
    }

    private function manager(Location $branch): User
    {
        $employee = $this->employee($branch, 'Leave Manager ' . Str::random(4));
        $user = User::factory()->create(['employee_id' => $employee->id]);

        foreach (['attendance.leaves.view', 'attendance.leaves.manage', 'attendance.leaves.approve'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function type(?int $annualDays = null): LeaveType
    {
        return LeaveType::query()->create([
            'code' => 'LEAVE-' . Str::upper(Str::random(5)),
            'name' => 'Annual Leave',
            'annual_days' => $annualDays,
            'is_active' => true,
        ]);
    }

    private function leave(Employee $employee, LeaveType $type, string $start): EmployeeLeaveRequest
    {
        return EmployeeLeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $start,
            'end_date' => $start,
            'total_days' => 1,
            'status' => 'pending',
        ]);
    }

    private function payload(Employee $employee, LeaveType $type, string $start, ?string $end = null): array
    {
        return [
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $start,
            'end_date' => $end ?? $start,
        ];
    }
}
