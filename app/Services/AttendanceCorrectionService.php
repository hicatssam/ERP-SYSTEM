<?php

namespace App\Services;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionService
{
    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    public function create(Employee $employee, User $actor, array $data): AttendanceCorrectionRequest
    {
        return DB::transaction(function () use ($employee, $actor, $data): AttendanceCorrectionRequest {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $date = $data['work_date'];
            $this->assertEditable($employee, $date);

            if (AttendanceCorrectionRequest::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $date)
                ->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'work_date' => 'يوجد طلب تصحيح قيد المراجعة لنفس اليوم.',
                ]);
            }

            return AttendanceCorrectionRequest::query()->create([
                'employee_id' => $employee->id,
                'requested_by' => $actor->id,
                'work_date' => $date,
                'requested_check_in_at' => $data['check_in_at'] ?? null,
                'requested_check_out_at' => $data['check_out_at'] ?? null,
                'reason' => $data['reason'],
                'status' => 'pending',
            ]);
        });
    }

    public function review(
        AttendanceCorrectionRequest $request,
        User $actor,
        bool $approved,
        ?string $note = null
    ): AttendanceCorrectionRequest {
        return DB::transaction(function () use ($request, $actor, $approved, $note): AttendanceCorrectionRequest {
            $employee = Employee::query()->whereKey($request->employee_id)->lockForUpdate()->firstOrFail();
            $request = AttendanceCorrectionRequest::query()
                ->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['correction' => 'تمت معالجة طلب التصحيح مسبقًا.']);
            }
            if ($request->requested_by === $actor->id) {
                throw ValidationException::withMessages(['correction' => 'لا يمكنك اعتماد طلب التصحيح الذي قدمته بنفسك.']);
            }

            $snapshot = null;
            if ($approved) {
                $date = $request->work_date->toDateString();
                $this->assertEditable($employee, $date);

                $existing = AttendanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('work_date', $date)->lockForUpdate()->first();

                $checkIn = $request->requested_check_in_at ?? $existing?->check_in_at;
                $checkOut = $request->requested_check_out_at ?? $existing?->check_out_at;

                if (! $checkIn && ! $checkOut) {
                    throw ValidationException::withMessages(['correction' => 'يجب تحديد وقت حضور أو انصراف.']);
                }
                if ($checkIn && $checkOut && $checkOut->lt($checkIn)) {
                    throw ValidationException::withMessages(['correction' => 'وقت الانصراف يسبق وقت الحضور.']);
                }

                $snapshot = $existing ? [
                    'status' => $existing->status,
                    'check_in_at' => $existing->check_in_at?->toDateTimeString(),
                    'check_out_at' => $existing->check_out_at?->toDateTimeString(),
                    'work_shift_id' => $existing->work_shift_id,
                    'scheduled_start_at' => $existing->scheduled_start_at?->toDateTimeString(),
                    'scheduled_end_at' => $existing->scheduled_end_at?->toDateTimeString(),
                    'worked_minutes' => $existing->worked_minutes,
                    'late_minutes' => $existing->late_minutes,
                    'early_leave_minutes' => $existing->early_leave_minutes,
                    'overtime_minutes' => $existing->overtime_minutes,
                    'source' => $existing->source,
                    'verification_method' => $existing->verification_method,
                    'verification_provider' => $existing->verification_provider,
                    'verification_reference' => $existing->verification_reference,
                    'verification_location_id' => $existing->verification_location_id,
                    'verification_metadata' => $existing->verification_metadata,
                    'notes' => $existing->notes,
                    'created_by' => $existing->created_by,
                    'approved_by' => $existing->approved_by,
                    'approved_at' => $existing->approved_at?->toDateTimeString(),
                ] : null;

                $record = $this->attendance->saveRecord($employee, [
                    'work_date' => $date,
                    'status' => 'present',
                    'check_in_at' => $checkIn?->toDateTimeString(),
                    'check_out_at' => $checkOut?->toDateTimeString(),
                    'notes' => 'تصحيح حضور معتمد بطلب رقم '.$request->id,
                    'source' => 'correction',
                ], $actor);
                $this->attendance->approveRecord($record, $actor);
            }

            $request->update([
                'status' => $approved ? 'approved' : 'rejected',
                'original_snapshot' => $snapshot,
                'decision_note' => $note,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    private function assertEditable(Employee $employee, string $date): void
    {
        $approvedLeave = EmployeeLeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('day_fraction', '>=', 1)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)->exists();

        if ($approvedLeave) {
            throw ValidationException::withMessages([
                'work_date' => 'يوجد طلب إجازة معتمد لهذا اليوم. راجع الإجازة قبل تصحيح الحضور.',
            ]);
        }

        $locationIds = $employee->employeeLocations()->where('is_primary', true)
            ->where(fn ($query) => $query->whereNull('started_at')
                ->orWhereDate('started_at', '<=', $date))
            ->where(fn ($query) => $query->whereNull('ended_at')
                ->orWhereDate('ended_at', '>=', $date))
            ->pluck('location_id');

        $closedPeriod = PayrollPeriod::query()
            ->whereIn('status', ['approved', 'paid', 'closed'])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($query) use ($locationIds): void {
                $query->whereNull('location_id');
                if ($locationIds->isNotEmpty()) {
                    $query->orWhereIn('location_id', $locationIds);
                }
            })->exists();

        if ($closedPeriod) {
            throw ValidationException::withMessages([
                'work_date' => 'دورة الرواتب لهذا اليوم معتمدة؛ لا يمكن تعديل الحضور بعدها.',
            ]);
        }
    }
}
