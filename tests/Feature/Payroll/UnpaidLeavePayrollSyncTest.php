<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeCompensationProfile;
use App\Models\EmployeePayrollAdjustment;
use App\Models\EmployeeShiftAssignment;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\AttendancePayrollService;
use App\Services\AttendanceService;
use App\Services\EmployeeLeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnpaidLeavePayrollSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unpaid_leave_deducts_workdays_at_the_period_end_and_paid_leave_stays_paid(): void
    {
        $actor = User::factory()->create();
        $period = PayrollPeriod::query()->create([
            'code' => 'PAYROLL-2026-03',
            'name' => 'March 2026',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'status' => 'draft',
        ]);
        $unpaid = LeaveType::query()->create([
            'code' => 'UNPAID', 'name' => 'Unpaid', 'is_paid' => false, 'is_active' => true,
        ]);
        $paid = LeaveType::query()->create([
            'code' => 'PAID', 'name' => 'Paid', 'is_paid' => true, 'is_active' => true,
        ]);

        $unpaidEmployee = $this->employeeWithShift('Unpaid Employee');
        $paidEmployee = $this->employeeWithShift('Paid Employee');

        $unpaidLeave = app(EmployeeLeaveService::class)->create($unpaidEmployee, $unpaid, [
            'start_date' => '2026-03-30', 'end_date' => '2026-03-31',
        ], $actor->id);
        $paidLeave = app(EmployeeLeaveService::class)->create($paidEmployee, $paid, [
            'start_date' => '2026-03-31', 'end_date' => '2026-03-31',
        ], $actor->id);

        app(AttendanceService::class)->approveLeave($unpaidLeave, $actor);
        app(AttendanceService::class)->approveLeave($paidLeave, $actor);

        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $unpaidEmployee->id,
            'status' => 'leave',
            'source' => 'leave',
        ]);

        // Policy changes affect future approvals; the posted attendance keeps
        // the paid/unpaid decision taken when each leave was approved.
        $unpaid->update(['is_paid' => true]);
        $paid->update(['is_paid' => false]);

        $result = app(AttendancePayrollService::class)->sync($period, $actor);
        $this->assertSame(['employees' => 2, 'adjustments' => 1], $result);

        $deduction = EmployeePayrollAdjustment::query()->sole();
        $this->assertSame($unpaidEmployee->id, $deduction->employee_id);
        $this->assertSame(200.0, (float) $deduction->amount);
        $this->assertSame(2, $deduction->metadata['unpaid_leave_days']);

        app(AttendancePayrollService::class)->sync($period, $actor);
        $this->assertDatabaseCount('employee_payroll_adjustments', 1);
        $this->assertSame(200.0, (float) EmployeePayrollAdjustment::query()->sole()->amount);

        // Older rows without a snapshot can still be recognized from one
        // unambiguous approved leave request.
        AttendanceRecord::query()
            ->where('employee_id', $unpaidEmployee->id)
            ->update(['verification_metadata' => null]);
        $unpaid->update(['is_paid' => false]);
        app(AttendancePayrollService::class)->sync($period, $actor);
        $this->assertSame(200.0, (float) EmployeePayrollAdjustment::query()->sole()->amount);
    }

    private function employeeWithShift(string $name): Employee
    {
        $employee = Employee::query()->create([
            'employee_number' => strtoupper(str_replace(' ', '-', $name)),
            'full_name' => $name,
            'employment_status' => 'active',
        ]);

        EmployeeCompensationProfile::query()->create([
            'employee_id' => $employee->id,
            'salary_basis' => 'monthly',
            'base_salary' => 2600,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $shift = WorkShift::query()->create([
            'code' => $employee->employee_number,
            'name' => $name . ' shift',
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

        return $employee;
    }
}
