<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeSelfAttendanceRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeSelfAttendanceService
{
    public function __construct(
        private readonly AttendanceFeatureService $features,
        private readonly EmployeeLeaveService $leaves,
        private readonly AttendanceService $attendance
    ) {
    }

    public function punch(User $actor, string $action, ?string $ip, ?string $userAgent): EmployeeSelfAttendanceRequest
    {
        abort_unless($action === 'check_out' || $this->features->employeeSelfPunchEnabled(),
            403, 'التسجيل الذاتي غير مفعّل.');
        $employee = $actor->employee;
        abort_unless($employee && $employee->isActive(), 403, 'الحساب غير مرتبط بموظف نشط.');

        return DB::transaction(function () use ($actor, $employee, $action, $ip, $userAgent): EmployeeSelfAttendanceRequest {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $now = now();

            if ($action === 'check_out') {
                $request = EmployeeSelfAttendanceRequest::query()
                    ->where('employee_id', $employee->id)
                    ->where('status', 'pending')
                    ->whereNull('check_out_at')
                    ->whereDate('work_date', '>=', $now->copy()->subDay()->toDateString())
                    ->latest('work_date')->lockForUpdate()->first();

                if (! $request || $request->check_in_at->gt($now)
                    || $request->check_in_at->lt($now->copy()->subDay())) {
                    throw ValidationException::withMessages([
                        'attendance' => 'لا يوجد تسجيل حضور مفتوح خلال آخر 24 ساعة. راجع المسؤول لتصحيح الدوام.',
                    ]);
                }
                if ($request->check_in_at->copy()->addMinute()->gt($now)) {
                    throw ValidationException::withMessages(['attendance' => 'انتظر دقيقة بعد الحضور قبل تسجيل الانصراف.']);
                }

                $this->assertPayrollOpen($employee, $request->work_date->toDateString());
                $request->update([
                    'check_out_at' => $now,
                    'check_out_ip' => $ip,
                    'check_out_user_agent' => mb_substr((string) $userAgent, 0, 300),
                ]);

                return $request->fresh();
            }

            if ($action !== 'check_in') {
                throw ValidationException::withMessages(['action' => 'إجراء الدوام غير معروف.']);
            }

            $day = $now->toDateString();
            $this->assertPayrollOpen($employee, $day);
            if (EmployeeSelfAttendanceRequest::query()->where('employee_id', $employee->id)
                ->where('status', 'pending')->whereNull('check_out_at')
                ->where('check_in_at', '>=', $now->copy()->subDay())->exists()) {
                throw ValidationException::withMessages(['attendance' => 'لديك دوام مفتوح؛ سجّل الانصراف أولًا.']);
            }
            $assignment = $employee->employeeLocations()->with('location')
                ->where('is_primary', true)
                ->where(fn ($query) => $query->whereNull('started_at')->orWhereDate('started_at', '<=', $day))
                ->where(fn ($query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $day))
                ->orderByDesc('started_at')->first();
            abort_unless($assignment?->location?->is_active, 403, 'لا يوجد فرع نشط مرتبط بك اليوم.');

            if (EmployeeLeaveRequest::query()->where('employee_id', $employee->id)
                ->where('status', 'approved')->where('day_fraction', '>=', 1)
                ->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day)->exists()) {
                throw ValidationException::withMessages(['attendance' => 'لديك إجازة معتمدة لهذا اليوم. راجع المسؤول أولًا.']);
            }
            if (AttendanceRecord::query()->where('employee_id', $employee->id)
                ->whereDate('work_date', $day)->exists()) {
                throw ValidationException::withMessages(['attendance' => 'يوجد سجل حضور أو إجازة لهذا اليوم. راجعه في بوابتك.']);
            }
            if (EmployeeSelfAttendanceRequest::query()->where('employee_id', $employee->id)
                ->whereDate('work_date', $day)->exists()) {
                throw ValidationException::withMessages(['attendance' => 'سجلت دوام هذا اليوم بالفعل.']);
            }

            return EmployeeSelfAttendanceRequest::query()->create([
                'employee_id' => $employee->id,
                'location_id' => $assignment->location_id,
                'work_date' => $day,
                'check_in_at' => $now,
                'status' => 'pending',
                'requested_by' => $actor->id,
                'check_in_ip' => $ip,
                'check_in_user_agent' => mb_substr((string) $userAgent, 0, 300),
            ]);
        });
    }

    public function review(
        EmployeeSelfAttendanceRequest $submission,
        User $reviewer,
        bool $approved,
        ?string $note = null
    ): EmployeeSelfAttendanceRequest {
        abort_unless($reviewer->isAdmin() || $reviewer->can('employees.view_all')
            || (int) $reviewer->primaryLocation()?->id === (int) $submission->location_id,
            403, 'لا يمكنك مراجعة طلب تابع لفرع آخر.');

        return DB::transaction(function () use ($submission, $reviewer, $approved, $note): EmployeeSelfAttendanceRequest {
            $employee = Employee::query()->whereKey($submission->employee_id)->lockForUpdate()->firstOrFail();
            $submission = EmployeeSelfAttendanceRequest::query()->whereKey($submission->id)
                ->lockForUpdate()->firstOrFail();
            if ($submission->status !== 'pending') {
                throw ValidationException::withMessages(['attendance' => 'تمت معالجة طلب الدوام مسبقًا.']);
            }
            if ((int) $submission->requested_by === (int) $reviewer->id) {
                throw ValidationException::withMessages(['attendance' => 'لا يمكنك اعتماد أو رفض تسجيل دوامك بنفسك.']);
            }

            if ($approved) {
                if (! $submission->check_out_at) {
                    throw ValidationException::withMessages(['attendance' => 'ينتظر الطلب تسجيل الانصراف قبل الاعتماد.']);
                }
                $day = $submission->work_date->toDateString();
                $this->assertPayrollOpen($employee, $day);
                if (AttendanceRecord::query()->where('employee_id', $employee->id)
                    ->whereDate('work_date', $day)->exists()) {
                    throw ValidationException::withMessages([
                        'attendance' => 'ظهر سجل حضور آخر لهذا اليوم. راجعه قبل اعتماد التسجيل الذاتي.',
                    ]);
                }
                if (EmployeeLeaveRequest::query()->where('employee_id', $employee->id)
                    ->where('status', 'approved')->where('day_fraction', '>=', 1)
                    ->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day)->exists()) {
                    throw ValidationException::withMessages(['attendance' => 'يوجد إجازة معتمدة لهذا اليوم.']);
                }

                $record = $this->attendance->saveRecord($employee, [
                    'work_date' => $day,
                    'status' => 'present',
                    'check_in_at' => $submission->check_in_at->toDateTimeString(),
                    'check_out_at' => $submission->check_out_at->toDateTimeString(),
                    'source' => 'employee_self',
                    'verification_method' => 'self_reported',
                    'verification_metadata' => [
                        'self_attendance_request_id' => $submission->id,
                        'assigned_location_id' => $submission->location_id,
                    ],
                    'notes' => 'تسجيل ذاتي راجعه المسؤول؛ الطلب رقم '.$submission->id,
                ], $reviewer);
                $this->attendance->approveRecord($record, $reviewer);
            }

            $submission->update([
                'status' => $approved ? 'approved' : 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'decision_note' => $note,
            ]);

            return $submission->fresh();
        });
    }

    private function assertPayrollOpen(Employee $employee, string $day): void
    {
        $date = Carbon::parse($day)->startOfDay();
        $this->leaves->assertPayrollEditable($employee, $date, $date);
    }
}
