<?php

namespace App\Services;

use App\Models\AttendancePunch;
use App\Models\AttendanceRecord;
use App\Models\EmployeeBiometricMapping;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendancePunchProcessor
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly AttendanceFeatureService $features
    ) {
    }

    public function process(AttendancePunch $punch): string
    {
        $mapping = EmployeeBiometricMapping::query()
            ->with('employee')
            ->where('attendance_device_id', $punch->attendance_device_id)
            ->where('device_user_id', $punch->device_user_id)
            ->where('is_active', true)
            ->first();

        if (! $mapping) {
            $punch->update([
                'status' => 'unmapped',
                'error_message' => 'لا يوجد ربط بين رقم المستخدم في الجهاز وأحد الموظفين.',
            ]);

            return 'unmapped';
        }

        return DB::transaction(function () use ($punch, $mapping): string {
            [$workDate, $shift] = $this->resolveWorkDateAndShift(
                $mapping->employee,
                $punch->punch_at
            );

            $punch->update([
                'employee_biometric_mapping_id' => $mapping->id,
                'employee_id' => $mapping->employee_id,
                'work_date' => $workDate->toDateString(),
                'status' => 'pending',
                'error_message' => null,
            ]);

            $dayPunches = AttendancePunch::query()
                ->where('employee_id', $mapping->employee_id)
                ->whereDate('work_date', $workDate->toDateString())
                ->whereIn('status', ['pending', 'processed'])
                ->orderBy('punch_at')
                ->orderBy('id')
                ->get();

            $checkIn = $this->resolveCheckIn($dayPunches);
            $checkOut = $this->resolveCheckOut($dayPunches, $checkIn);

            [$scheduledStart, $scheduledEnd] =
                $this->scheduledWindow($shift, $workDate);

            $metrics = $this->metrics(
                $shift,
                $scheduledStart,
                $scheduledEnd,
                $checkIn,
                $checkOut
            );

            $requiresApproval =
                $this->features->deviceApprovalRequired();

            AttendanceRecord::query()->updateOrCreate(
                [
                    'employee_id' => $mapping->employee_id,
                    'work_date' => $workDate->toDateString(),
                ],
                [
                    'work_shift_id' => $shift?->id,
                    'scheduled_start_at' => $scheduledStart,
                    'scheduled_end_at' => $scheduledEnd,
                    'check_in_at' => $checkIn,
                    'check_out_at' => $checkOut,
                    'status' => 'present',
                    ...$metrics,
                    'source' => 'device',
                    'notes' => null,
                    'approved_by' => null,
                    'approved_at' => $requiresApproval
                        ? null
                        : now(),
                    'created_by' => null,
                ]
            );

            AttendancePunch::query()
                ->whereIn('id', $dayPunches->pluck('id'))
                ->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'error_message' => null,
                ]);

            return 'processed';
        });
    }

    private function resolveWorkDateAndShift(
        $employee,
        Carbon $punchAt
    ): array {
        $candidate = $punchAt->copy()->startOfDay();
        $previous = $candidate->copy()->subDay();

        $previousShift = $this->attendance->shiftFor(
            $employee,
            $previous
        );

        if (
            $previousShift
            && $this->crossesMidnight($previousShift)
        ) {
            $end = Carbon::parse(
                $candidate->toDateString()
                . ' '
                . $previousShift->end_time
            );

            if ($punchAt->lte($end)) {
                return [$previous, $previousShift];
            }
        }

        return [
            $candidate,
            $this->attendance->shiftFor($employee, $candidate),
        ];
    }

    private function crossesMidnight($shift): bool
    {
        return Carbon::parse($shift->end_time)
            ->lte(Carbon::parse($shift->start_time));
    }

    private function resolveCheckIn($punches): ?Carbon
    {
        $explicit = $punches
            ->where('punch_type', 'in')
            ->first();

        return ($explicit ?: $punches->first())
            ?->punch_at
            ?->copy();
    }

    private function resolveCheckOut(
        $punches,
        ?Carbon $checkIn
    ): ?Carbon {
        $explicit = $punches
            ->where('punch_type', 'out')
            ->last();

        $candidate = $explicit ?: $punches->last();

        if (! $candidate || ! $checkIn) {
            return null;
        }

        $time = $candidate->punch_at->copy();

        return $time->gt($checkIn)
            ? $time
            : null;
    }

    private function scheduledWindow(
        $shift,
        Carbon $workDate
    ): array {
        if (! $shift) {
            return [null, null];
        }

        $start = Carbon::parse(
            $workDate->toDateString()
            . ' '
            . $shift->start_time
        );

        $end = Carbon::parse(
            $workDate->toDateString()
            . ' '
            . $shift->end_time
        );

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function metrics(
        $shift,
        ?Carbon $scheduledStart,
        ?Carbon $scheduledEnd,
        ?Carbon $checkIn,
        ?Carbon $checkOut
    ): array {
        $worked = ($checkIn && $checkOut)
            ? max(0, $checkIn->diffInMinutes($checkOut))
            : 0;

        $late = 0;
        $early = 0;
        $overtime = 0;

        if ($scheduledStart && $checkIn) {
            $threshold = $scheduledStart
                ->copy()
                ->addMinutes((int) ($shift?->grace_minutes ?? 0));

            if ($checkIn->gt($threshold)) {
                $late = $threshold->diffInMinutes($checkIn);
            }
        }

        if (
            $scheduledEnd
            && $checkOut
            && $checkOut->lt($scheduledEnd)
        ) {
            $early = $checkOut->diffInMinutes($scheduledEnd);
        }

        if ($scheduledEnd && $checkOut) {
            $threshold = $scheduledEnd
                ->copy()
                ->addMinutes(
                    (int) ($shift?->overtime_after_minutes ?? 0)
                );

            if ($checkOut->gt($threshold)) {
                $overtime = $threshold->diffInMinutes($checkOut);
            }
        }

        $worked = max(
            0,
            $worked - (int) ($shift?->break_minutes ?? 0)
        );

        return [
            'worked_minutes' => $worked,
            'late_minutes' => $late,
            'early_leave_minutes' => $early,
            'overtime_minutes' => $overtime,
        ];
    }
}
