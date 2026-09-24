<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function shiftFor(Employee $employee, Carbon|string $date): ?WorkShift
    {
        $date = Carbon::parse($date)->toDateString();

        return EmployeeShiftAssignment::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->where('is_primary', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->latest('effective_from')
            ->first()?->shift;
    }

    public function saveRecord(Employee $employee, array $data, User $actor): AttendanceRecord
    {
        $workDate = Carbon::parse($data['work_date'])->startOfDay();

        $shift = ! empty($data['work_shift_id'])
            ? WorkShift::query()->find($data['work_shift_id'])
            : $this->shiftFor($employee, $workDate);

        [$scheduledStart, $scheduledEnd] = $this->scheduledWindow($shift, $workDate);

        $checkIn = ! empty($data['check_in_at']) ? Carbon::parse($data['check_in_at']) : null;
        $checkOut = ! empty($data['check_out_at']) ? Carbon::parse($data['check_out_at']) : null;

        if ($checkIn && $checkOut && $checkOut->lt($checkIn)) {
            throw ValidationException::withMessages([
                'check_out_at' => 'وقت الانصراف يجب أن يكون بعد وقت الحضور.',
            ]);
        }

        $metrics = $this->metrics(
            $data['status'],
            $shift,
            $scheduledStart,
            $scheduledEnd,
            $checkIn,
            $checkOut
        );

        $values = [
            'work_shift_id' => $shift?->id,
            'scheduled_start_at' => $scheduledStart,
            'scheduled_end_at' => $scheduledEnd,
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'status' => $data['status'],
            ...$metrics,
            'source' => $data['source'] ?? 'manual',
            'verification_method' =>
                $data['verification_method'] ?? null,
            'verification_provider' =>
                $data['verification_provider'] ?? null,
            'verification_reference' =>
                $data['verification_reference'] ?? null,
            'verification_location_id' =>
                $data['verification_location_id'] ?? null,
            'verification_metadata' =>
                $data['verification_metadata'] ?? null,
            'notes' => $data['notes'] ?? null,
            'approved_by' => null,
            'approved_at' => null,
            'created_by' => $actor->id,
        ];

        /*
         * Do not use updateOrCreate() with work_date here.
         *
         * AttendanceRecord casts work_date as a date. SQLite serializes the
         * stored value as "Y-m-d 00:00:00", while updateOrCreate() looks up
         * the raw "Y-m-d" value. That mismatch can miss today's existing row
         * and then attempt a duplicate INSERT against the unique
         * (employee_id, work_date) key. MySQL is more forgiving, but keeping
         * one code path for both databases prevents a production/test drift.
         */
        return DB::transaction(
            fn (): AttendanceRecord => $this->persistDailyRecord(
                $employee->id,
                $workDate,
                $values
            )
        );
    }

    public function approveRecord(AttendanceRecord $record, User $actor): AttendanceRecord
    {
        $record->update([
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        return $record->fresh();
    }

    public function approveLeave(
        EmployeeLeaveRequest $leave,
        User $actor,
        ?string $decisionNote = null
    ): EmployeeLeaveRequest {
        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages([
                'leave' => 'تم اتخاذ قرار على طلب الإجازة مسبقًا.',
            ]);
        }

        return DB::transaction(function () use ($leave, $actor, $decisionNote): EmployeeLeaveRequest {
            $leave->loadMissing('employee', 'leaveType');

            $leave->update([
                'status' => 'approved',
                'decision_note' => $decisionNote,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $cursor = $leave->start_date->copy()->startOfDay();
            $end = $leave->end_date->copy()->startOfDay();

            while ($cursor->lte($end)) {
                $shift = $this->shiftFor($leave->employee, $cursor);

                if ($this->isScheduledWorkDay($shift, $cursor)) {
                    [$start, $finish] = $this->scheduledWindow($shift, $cursor);

                    $this->persistDailyRecord(
                        $leave->employee_id,
                        $cursor,
                        [
                            'work_shift_id' => $shift?->id,
                            'scheduled_start_at' => $start,
                            'scheduled_end_at' => $finish,
                            'status' => 'leave',
                            'worked_minutes' => 0,
                            'late_minutes' => 0,
                            'early_leave_minutes' => 0,
                            'overtime_minutes' => 0,
                            'source' => 'leave',
                            'verification_method' => null,
                            'verification_provider' => null,
                            'verification_reference' => null,
                            'verification_location_id' => null,
                            'verification_metadata' => null,
                            'notes' => $leave->leaveType->name,
                            'approved_by' => $actor->id,
                            'approved_at' => now(),
                            'created_by' => $actor->id,
                        ]
                    );
                }

                $cursor->addDay();
            }

            return $leave->fresh(['employee', 'leaveType']);
        });
    }

    public function rejectLeave(
        EmployeeLeaveRequest $leave,
        User $actor,
        ?string $decisionNote = null
    ): EmployeeLeaveRequest {
        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages([
                'leave' => 'تم اتخاذ قرار على طلب الإجازة مسبقًا.',
            ]);
        }

        $leave->update([
            'status' => 'rejected',
            'decision_note' => $decisionNote,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        return $leave->fresh();
    }

    public function isScheduledWorkDay(?WorkShift $shift, Carbon|string $date): bool
    {
        if (! $shift) {
            return false;
        }

        $days = $shift->work_days ?: [1, 2, 3, 4, 5, 6];

        return in_array(
            Carbon::parse($date)->isoWeekday(),
            array_map('intval', $days),
            true
        );
    }

    private function scheduledWindow(?WorkShift $shift, Carbon $workDate): array
    {
        if (! $shift) {
            return [null, null];
        }

        $start = Carbon::parse($workDate->toDateString() . ' ' . $shift->start_time);
        $end = Carbon::parse($workDate->toDateString() . ' ' . $shift->end_time);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function metrics(
        string $status,
        ?WorkShift $shift,
        ?Carbon $scheduledStart,
        ?Carbon $scheduledEnd,
        ?Carbon $checkIn,
        ?Carbon $checkOut
    ): array {
        if ($status !== 'present') {
            return [
                'worked_minutes' => 0,
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'overtime_minutes' => 0,
            ];
        }

        $worked = ($checkIn && $checkOut)
            ? max(0, $checkIn->diffInMinutes($checkOut))
            : 0;

        $late = 0;
        $early = 0;
        $overtime = 0;

        if ($scheduledStart && $checkIn) {
            $threshold = $scheduledStart->copy()->addMinutes((int) ($shift?->grace_minutes ?? 0));
            if ($checkIn->gt($threshold)) {
                $late = $threshold->diffInMinutes($checkIn);
            }
        }

        if ($scheduledEnd && $checkOut && $checkOut->lt($scheduledEnd)) {
            $early = $checkOut->diffInMinutes($scheduledEnd);
        }

        if ($scheduledEnd && $checkOut) {
            $threshold = $scheduledEnd->copy()
                ->addMinutes((int) ($shift?->overtime_after_minutes ?? 0));

            if ($checkOut->gt($threshold)) {
                $overtime = $threshold->diffInMinutes($checkOut);
            }
        }

        $worked = max(0, $worked - (int) ($shift?->break_minutes ?? 0));

        return [
            'worked_minutes' =>
                (int) floor($worked),
            'late_minutes' =>
                (int) floor($late),
            'early_leave_minutes' =>
                (int) floor($early),
            'overtime_minutes' =>
                (int) floor($overtime),
        ];
    }

    /**
     * Persist the single attendance row for an employee/day using a
     * date-aware lookup that behaves the same on MySQL and SQLite.
     */
    private function persistDailyRecord(
        int $employeeId,
        Carbon|string $workDate,
        array $values
    ): AttendanceRecord {
        $date = Carbon::parse(
            $workDate
        )->toDateString();

        $record = AttendanceRecord::query()
            ->where(
                'employee_id',
                $employeeId
            )
            ->whereDate(
                'work_date',
                $date
            )
            ->lockForUpdate()
            ->first();

        if ($record) {
            $record->fill($values);
            $record->save();

            return $record->fresh();
        }

        return AttendanceRecord::query()->create([
            'employee_id' => $employeeId,
            'work_date' => $date,
            ...$values,
        ]);
    }
}
