<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('attendance_enabled', 1);
    }

    #[Test]
    public function employee_can_only_see_own_approved_pay_and_submit_own_leave(): void
    {
        $branch = $this->branch('A');
        $worker = $this->employee($branch, 'Portal Worker');
        $another = $this->employee($branch, 'Private Worker');
        $user = User::factory()->create(['employee_id' => $worker->id]);
        $type = LeaveType::query()->create([
            'code' => 'PORTAL', 'name' => 'Leave', 'is_paid' => true, 'is_active' => true,
        ]);
        $period = PayrollPeriod::query()->create([
            'code' => 'PORTAL-PAY', 'name' => 'Portal Pay',
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
            'status' => 'approved',
        ]);
        $myPay = PayrollItem::query()->create([
            'employee_id' => $worker->id, 'payroll_period_id' => $period->id,
            'net_salary' => 1200, 'status' => 'approved',
        ]);
        $privatePay = PayrollItem::query()->create([
            'employee_id' => $another->id, 'payroll_period_id' => $period->id,
            'net_salary' => 99000, 'status' => 'approved',
        ]);
        $draft = PayrollPeriod::query()->create([
            'code' => 'PORTAL-DRAFT', 'name' => 'Draft',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => 'draft',
        ]);
        $draftPay = PayrollItem::query()->create([
            'employee_id' => $worker->id, 'payroll_period_id' => $draft->id,
            'net_salary' => 3456, 'status' => 'calculated',
        ]);

        $this->actingAs($user)->get(route('my-hr.index'))
            ->assertOk()->assertSee('Portal Worker')
            ->assertDontSee('Private Worker')->assertDontSee('99,000')
            ->assertDontSee('3,456');
        $this->actingAs($user)->get(route('my-hr.payslips.show', $myPay))->assertOk();
        $this->actingAs($user)->get(route('my-hr.payslips.show', $privatePay))->assertForbidden();
        $this->actingAs($user)->get(route('my-hr.payslips.show', $draftPay))->assertForbidden();

        $this->actingAs($user)->post(route('my-hr.leaves.store'), [
            'employee_id' => $another->id,
            'leave_type_id' => $type->id,
            'start_date' => now()->addDays(4)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $leave = EmployeeLeaveRequest::query()->sole();
        $this->assertSame($worker->id, $leave->employee_id);
        $this->assertSame('pending', $leave->status);
    }

    #[Test]
    public function correction_requires_review_with_branch_scope_and_preserves_original_attendance(): void
    {
        $branchA = $this->branch('A');
        $branchB = $this->branch('B');
        $worker = $this->employee($branchA, 'Worker A');
        $otherWorker = $this->employee($branchB, 'Worker B');
        $manager = User::factory()->create(['employee_id' => $this->employee($branchA, 'Manager A')->id]);
        $manager->givePermissionTo(Permission::findOrCreate('attendance.approve', 'web'));
        $user = User::factory()->create(['employee_id' => $worker->id]);
        $date = now()->subDays(3)->toDateString();
        $old = AttendanceRecord::query()->create([
            'employee_id' => $worker->id, 'work_date' => $date,
            'status' => 'absent', 'source' => 'face',
            'verification_method' => 'face',
            'verification_reference' => 'old-scan-1',
            'verification_metadata' => ['camera' => 'front'],
        ]);

        $this->actingAs($user)->post(route('my-hr.corrections.store'), [
            'employee_id' => $otherWorker->id,
            'work_date' => $date,
            'check_in_at' => $date.' 08:00:00',
            'check_out_at' => $date.' 16:00:00',
            'reason' => 'تعطل جهاز البصمة',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $correction = AttendanceCorrectionRequest::query()->sole();
        $this->assertSame($worker->id, $correction->employee_id);
        $this->assertSame('absent', $old->fresh()->status);

        $this->actingAs($user)
            ->post(route('attendance.corrections.approve', $correction))->assertForbidden();

        $this->actingAs($manager)->post(route('attendance.corrections.approve', $correction))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('approved', $correction->fresh()->status);
        $this->assertSame('absent', $correction->fresh()->original_snapshot['status']);
        $this->assertSame('old-scan-1', $correction->fresh()->original_snapshot['verification_reference']);
        $this->assertSame(['camera' => 'front'], $correction->fresh()->original_snapshot['verification_metadata']);
        $this->assertSame('present', $old->fresh()->status);
        $this->assertSame('correction', $old->fresh()->source);
        $this->assertNotNull($old->fresh()->approved_at);

        $this->actingAs($manager)->post(route('attendance.corrections.approve', $correction))
            ->assertSessionHasErrors('correction');

        $foreign = AttendanceCorrectionRequest::query()->create([
            'employee_id' => $otherWorker->id, 'requested_by' => $user->id,
            'work_date' => $date, 'reason' => 'Other', 'status' => 'pending',
            'requested_check_in_at' => $date.' 09:00:00',
        ]);
        $this->actingAs($manager)->get(route('attendance.corrections.index'))->assertDontSee('Worker B');
        $this->actingAs($manager)->post(route('attendance.corrections.approve', $foreign))->assertForbidden();
    }

    #[Test]
    public function correction_needs_a_reason_to_reject_and_cannot_use_future_times(): void
    {
        $branch = $this->branch('A');
        $employee = $this->employee($branch, 'Worker for correction');
        $user = User::factory()->create(['employee_id' => $employee->id]);
        $manager = User::factory()->create(['employee_id' => $this->employee($branch, 'Reviewer')->id]);
        $manager->givePermissionTo(Permission::findOrCreate('attendance.approve', 'web'));
        $day = now()->subDays(1)->toDateString();

        $this->actingAs($user)->post(route('my-hr.corrections.store'), [
            'work_date' => now()->toDateString(),
            'check_in_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'reason' => 'تعديل وقت',
        ])->assertSessionHasErrors('check_in_at');

        $this->actingAs($user)->post(route('my-hr.corrections.store'), [
            'work_date' => $day,
            'check_in_at' => $day.' 08:00:00',
            'reason' => 'تعطل جهاز الحضور',
        ])->assertSessionHasNoErrors();
        $correction = AttendanceCorrectionRequest::query()->sole();
        $this->actingAs($manager)->post(route('attendance.corrections.reject', $correction))
            ->assertSessionHasErrors('decision_note');
        $this->assertSame('pending', $correction->fresh()->status);
        $this->actingAs($manager)->post(route('attendance.corrections.reject', $correction), [
            'decision_note' => 'البصمة الموجودة صحيحة',
        ])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $correction->fresh()->status);
        $this->assertSame('البصمة الموجودة صحيحة', $correction->fresh()->decision_note);
    }

    #[Test]
    public function correction_cannot_modify_an_approved_pay_period(): void
    {
        $branch = $this->branch('A');
        $employee = $this->employee($branch, 'Pay Locked Worker');
        $user = User::factory()->create(['employee_id' => $employee->id]);
        $date = now()->subDays(2)->toDateString();
        PayrollPeriod::query()->create([
            'code' => 'LOCKED-PAY', 'name' => 'Locked Pay',
            'start_date' => now()->subDays(4)->toDateString(),
            'end_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        $this->actingAs($user)->post(route('my-hr.corrections.store'), [
            'work_date' => $date,
            'check_in_at' => $date.' 08:00:00',
            'reason' => 'خطأ في البصمة',
        ])->assertSessionHasErrors('work_date');

        $this->assertDatabaseCount('attendance_correction_requests', 0);
    }

    private function branch(string $code): Location
    {
        return Location::query()->create([
            'name' => 'HR Branch '.$code, 'code' => 'HR-'.$code.Str::upper(Str::random(5)),
            'type' => 'branch', 'is_active' => true,
        ]);
    }

    private function employee(Location $branch, string $name): Employee
    {
        $employee = Employee::query()->create([
            'employee_number' => 'HR-'.Str::upper(Str::random(9)),
            'full_name' => $name, 'employment_status' => 'active',
        ]);
        $employee->locations()->attach($branch->id, ['is_primary' => true]);
        return $employee;
    }
}
