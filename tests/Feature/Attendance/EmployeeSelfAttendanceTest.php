<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeSelfAttendanceRequest;
use App\Models\Location;
use App\Models\PayrollPeriod;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeSelfAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('attendance_enabled', 1);
        SystemSetting::set('attendance_employee_self_punch_enabled', 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function employee_records_own_times_and_manager_approves_only_after_checkout(): void
    {
        Carbon::setTestNow('2026-10-02 08:05:00');
        $branch = $this->branch('A');
        $employee = $this->employee($branch, 'Shift Worker');
        $user = User::factory()->create(['employee_id' => $employee->id]);
        $manager = $this->manager($branch);

        $this->actingAs($user)->get(route('my-hr.index'))
            ->assertOk()->assertSee('حضرت للدوام الآن');
        $this->actingAs($user)->post(route('my-hr.punch'), [
            'action' => 'check_in', 'employee_id' => $manager->employee_id,
            'check_in_at' => '2026-10-02 06:00:00', 'location_id' => 999,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $submission = EmployeeSelfAttendanceRequest::query()->sole();
        $this->assertSame($employee->id, $submission->employee_id);
        $this->assertSame($branch->id, $submission->location_id);
        $this->assertSame('2026-10-02 08:05:00', $submission->check_in_at->toDateTimeString());
        $this->assertDatabaseCount('attendance_records', 0);
        $this->actingAs($user)->get(route('my-hr.index'))
            ->assertOk()->assertSee('تسجيل انصرافي الآن')->assertSee('حضور مسجل مبدئيًا');
        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_in'])
            ->assertSessionHasErrors('attendance');
        $this->actingAs($manager)->post(route('attendance.self-requests.approve', $submission))
            ->assertSessionHasErrors('attendance');

        Carbon::setTestNow('2026-10-02 16:15:00');
        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_out'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2026-10-02 16:15:00', $submission->fresh()->check_out_at->toDateTimeString());
        $this->assertDatabaseCount('attendance_records', 0);

        $this->actingAs($user)->post(route('attendance.self-requests.approve', $submission))
            ->assertForbidden();
        $this->actingAs($manager)->get(route('attendance.self-requests.index'))
            ->assertOk()->assertSee('Shift Worker')->assertSee('جاهز للمراجعة');
        $this->actingAs($manager)->post(route('attendance.self-requests.approve', $submission))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('approved', $submission->fresh()->status);
        $record = AttendanceRecord::query()->sole();
        $this->assertSame('employee_self', $record->source);
        $this->assertSame('self_reported', $record->verification_method);
        $this->assertSame($submission->id, data_get($record->verification_metadata, 'self_attendance_request_id'));
        $this->assertNotNull($record->approved_at);
        $this->assertSame($manager->id, $record->approved_by);
    }

    #[Test]
    public function other_branch_cannot_read_or_approve_and_rejection_requires_a_reason(): void
    {
        Carbon::setTestNow('2026-10-02 09:00:00');
        $a = $this->branch('A');
        $b = $this->branch('B');
        $worker = $this->employee($a, 'Private Worker');
        $user = User::factory()->create(['employee_id' => $worker->id]);
        $foreignManager = $this->manager($b);
        $manager = $this->manager($a);
        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_in'])
            ->assertSessionHasNoErrors();
        $submission = EmployeeSelfAttendanceRequest::query()->sole();
        $this->actingAs($foreignManager)->get(route('attendance.self-requests.index'))
            ->assertOk()->assertDontSee('Private Worker');
        $this->actingAs($foreignManager)->post(route('attendance.self-requests.approve', $submission))
            ->assertForbidden();
        $this->actingAs($manager)->post(route('attendance.self-requests.reject', $submission))
            ->assertSessionHasErrors('decision_note');
        $this->actingAs($manager)->post(route('attendance.self-requests.reject', $submission), [
            'decision_note' => 'يرجى مراجعة الفرع قبل اعتماد التسجيل.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $submission->fresh()->status);
        $this->actingAs($user)->get(route('my-hr.index'))
            ->assertOk()->assertSee('يرجى مراجعة الفرع قبل اعتماد التسجيل.');
        $this->assertDatabaseCount('attendance_records', 0);
    }

    #[Test]
    public function disabled_or_conflicting_records_and_locked_payroll_block_self_registration(): void
    {
        Carbon::setTestNow('2026-10-02 09:00:00');
        $branch = $this->branch('A');
        $employee = $this->employee($branch, 'Blocked Worker');
        $user = User::factory()->create(['employee_id' => $employee->id]);
        SystemSetting::set('attendance_employee_self_punch_enabled', 0);
        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_in'])
            ->assertForbidden();
        SystemSetting::set('attendance_employee_self_punch_enabled', 1);
        AttendanceRecord::query()->create([
            'employee_id' => $employee->id, 'work_date' => '2026-10-02',
            'status' => 'present', 'source' => 'face', 'check_in_at' => now(),
        ]);
        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_in'])
            ->assertSessionHasErrors('attendance');
        AttendanceRecord::query()->delete();
        PayrollPeriod::query()->create([
            'code' => 'LOCK-SELF', 'name' => 'Locked',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
            'status' => 'approved', 'location_id' => $branch->id,
        ]);
        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_in'])
            ->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('employee_self_attendance_requests', 0);
    }

    #[Test]
    public function overnight_checkout_keeps_the_original_work_day(): void
    {
        Carbon::setTestNow('2026-10-02 23:50:00');
        $branch = $this->branch('N');
        $employee = $this->employee($branch, 'Night Worker');
        $user = User::factory()->create(['employee_id' => $employee->id]);
        $manager = $this->manager($branch);
        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_in'])
            ->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-10-03 07:10:00');
        $this->actingAs($user)->get(route('my-hr.index'))->assertSee('تسجيل انصرافي الآن');
        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_out'])
            ->assertSessionHasNoErrors();
        $submission = EmployeeSelfAttendanceRequest::query()->sole();
        $this->assertSame('2026-10-02', $submission->work_date->toDateString());
        $this->actingAs($manager)->post(route('attendance.self-requests.approve', $submission))
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-10-02', AttendanceRecord::query()->sole()->work_date->toDateString());
    }

    #[Test]
    public function hr_overview_includes_todays_self_presence_with_a_future_dated_branch_end(): void
    {
        Carbon::setTestNow('2026-10-02 10:00:00');
        $a = $this->branch('A');
        $b = $this->branch('B');
        $worker = $this->employee($a, 'Dated Branch Worker');
        $worker->employeeLocations()->update([
            'started_at' => '2026-10-01', 'ended_at' => '2026-10-03',
        ]);
        $user = User::factory()->create(['employee_id' => $worker->id]);
        $manager = $this->manager($a);
        $foreign = $this->manager($b);
        $manager->givePermissionTo(Permission::findOrCreate('hr.dashboard.view', 'web'));
        $foreign->givePermissionTo(Permission::findOrCreate('hr.dashboard.view', 'web'));

        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_in'])
            ->assertSessionHasNoErrors();
        $this->actingAs($manager)->get(route('hr.dashboard'))
            ->assertOk()->assertSee('Dated Branch Worker')->assertSee('على رأس العمل الآن');
        $this->actingAs($manager)->get(route('attendance.self-requests.index'))
            ->assertOk()->assertSee('Dated Branch Worker');
        $this->actingAs($foreign)->get(route('hr.dashboard'))
            ->assertOk()->assertDontSee('Dated Branch Worker');
    }

    #[Test]
    public function settings_control_manual_portal_punch_and_face_cannot_mix_with_a_pending_claim(): void
    {
        Carbon::setTestNow('2026-10-02 08:00:00');
        $branch = $this->branch('A');
        $employee = $this->employee($branch, 'Settings Worker');
        $user = User::factory()->create(['employee_id' => $employee->id]);
        $manager = $this->manager($branch);
        $manager->givePermissionTo(Permission::findOrCreate('settings.manage', 'web'));
        $values = [
            'attendance_enabled' => 1,
            'attendance_biometric_enabled' => 1,
            'attendance_employee_face_punch_enabled' => 1,
            'attendance_employee_self_punch_enabled' => 1,
            'payroll_standard_work_days_per_month' => 26,
            'payroll_standard_hours_per_day' => 8,
            'payroll_overtime_multiplier' => 1.5,
        ];
        $this->actingAs($manager)->put(route('settings.attendance-payroll.update'), $values)
            ->assertSessionHasNoErrors();
        $this->assertTrue(app(\App\Services\AttendanceFeatureService::class)->employeeSelfPunchEnabled());

        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_in'])
            ->assertSessionHasNoErrors();
        config([
            'attendance-face.compreface.base_url' => 'http://compreface.test',
            'attendance-face.compreface.api_key' => 'test-key',
        ]);
        $this->actingAs($user)->postJson(route('my-hr.face.challenge'))
            ->assertStatus(409);

        unset($values['attendance_employee_self_punch_enabled']);
        $this->actingAs($manager)->put(route('settings.attendance-payroll.update'), $values)
            ->assertSessionHasNoErrors();
        $this->assertFalse(app(\App\Services\AttendanceFeatureService::class)->employeeSelfPunchEnabled());
        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_out'])
            ->assertSessionHasErrors('attendance');
        Carbon::setTestNow('2026-10-02 08:02:00');
        $this->actingAs($user)->get(route('my-hr.index'))
            ->assertOk()->assertSee('تسجيل انصرافي الآن');
        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_out'])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('my-hr.punch'), ['action' => 'check_in'])
            ->assertForbidden();
    }

    private function branch(string $suffix): Location
    {
        return Location::query()->create([
            'code' => 'SELF-'.$suffix.Str::upper(Str::random(4)),
            'name' => 'Self Branch '.$suffix,
            'type' => 'branch', 'is_active' => true,
        ]);
    }

    private function employee(Location $location, string $name): Employee
    {
        $employee = Employee::query()->create([
            'employee_number' => 'SELF-'.Str::upper(Str::random(8)),
            'full_name' => $name, 'employment_status' => 'active',
        ]);
        $employee->locations()->attach($location->id, ['is_primary' => true]);

        return $employee;
    }

    private function manager(Location $location): User
    {
        $employee = $this->employee($location, 'Reviewer');
        $manager = User::factory()->create(['employee_id' => $employee->id]);
        $manager->givePermissionTo(Permission::findOrCreate('attendance.approve', 'web'));

        return $manager;
    }
}
