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
                    'source' => $existing->source,
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
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)->exists();

        if ($approvedLeave) {
            throw ValidationException::withMessages([
                'work_date' => 'يوجد طلب إجازة معتمد لهذا اليوم. راجع الإجازة قبل تصحيح الحضور.',
            ]);
        }

        $locationId = $employee->employeeLocations()
            ->where('is_primary', true)->whereNull('ended_at')->value('location_id');

        $closedPeriod = PayrollPeriod::query()
            ->whereIn('status', ['approved', 'paid', 'closed'])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($query) use ($locationId): void {
                $query->whereNull('location_id');
                if ($locationId) {
                    $query->orWhere('location_id', $locationId);
                }
            })->exists();

        if ($closedPeriod) {
            throw ValidationException::withMessages([
                'work_date' => 'دورة الرواتب لهذا اليوم معتمدة؛ لا يمكن تعديل الحضور بعدها.',
            ]);
        }
    }
}
