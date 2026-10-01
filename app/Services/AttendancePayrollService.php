<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\EmployeeCompensationProfile;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeePayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AttendancePayrollService
{
    public function __construct(
        private readonly AttendanceFeatureService $features
    ) {
    }

    public function sync(PayrollPeriod $period, User $actor): array
    {
        if (! in_array($period->status, ['draft', 'calculated'], true)) {
            throw ValidationException::withMessages([
                'attendance' => 'مزامنة الحضور متاحة قبل اعتماد دورة الرواتب فقط.',
            ]);
        }

        return DB::transaction(function () use ($period, $actor): array {
            EmployeePayrollAdjustment::query()
                ->where('payroll_period_id', $period->id)
                ->where('source_type', 'attendance_summary')
                ->delete();

            $records = AttendanceRecord::query()
                ->whereDate('work_date', '>=', $period->start_date->toDateString())
                ->whereDate('work_date', '<=', $period->end_date->toDateString())
                ->whereNotNull('approved_at')
                ->get()
                ->groupBy('employee_id');

            // Half-day leave coexists with an actual worked shift. It has no
            // synthetic full-day attendance row; payroll counts its fraction
            // once and excludes covered late/early time from automatic fines.
            $partialLeaves = EmployeeLeaveRequest::query()->with('leaveType')
                ->where('status', 'approved')->where('day_fraction', '<', 1)
                ->whereDate('start_date', '<=', $period->end_date->toDateString())
                ->whereDate('end_date', '>=', $period->start_date->toDateString())
                ->get()->groupBy('employee_id');
            foreach ($partialLeaves->keys() as $employeeId) {
                if (! $records->has($employeeId)) {
                    $records->put($employeeId, collect());
                }
            }

            // Older approved leave records predate the payment snapshot on
            // attendance_records. Resolve those against exactly one request.
            $legacyLeaveRequests = EmployeeLeaveRequest::query()
                ->with('leaveType')
                ->whereIn('employee_id', $records->keys()->all())
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $period->end_date->toDateString())
                ->whereDate('end_date', '>=', $period->start_date->toDateString())
                ->get()
                ->groupBy('employee_id');

            $created = 0;
            $employees = 0;

            foreach ($records as $employeeId => $employeeRecords) {
                $employeePartial = $partialLeaves->get($employeeId, collect());
                $partialDates = $employeePartial
                    ->map(fn (EmployeeLeaveRequest $leave) => $leave->start_date->toDateString())->all();
                $uncoveredRecords = $employeeRecords
                    ->reject(fn (AttendanceRecord $record) =>
                        in_array($record->work_date->toDateString(), $partialDates, true));
                $profile = EmployeeCompensationProfile::query()
                    ->where('employee_id', $employeeId)
                    ->where('is_active', true)
                    ->whereDate('effective_from', '<=', $period->end_date)
                    ->where(function ($query) use ($period): void {
                        $query->whereNull('effective_to')
                            ->orWhereDate('effective_to', '>=', $period->start_date);
                    })
                    ->latest('effective_from')
                    ->first();

                if (! $profile) {
                    continue;
                }

                $employees++;

                $hourlyRate = $this->hourlyRate($profile);
                $dailyRate = $this->dailyRate($profile);

                $lateMinutes = (int) $uncoveredRecords->sum('late_minutes');
                $earlyMinutes = (int) $uncoveredRecords->sum('early_leave_minutes');
                $overtimeMinutes = (int) $employeeRecords->sum('overtime_minutes');
                $absenceDays = $employeeRecords->where('status', 'absent')
                    ->sum(function (AttendanceRecord $record) use ($employeePartial): float {
                        $paidHalf = $employeePartial->first(fn (EmployeeLeaveRequest $leave) =>
                            $leave->start_date->toDateString() === $record->work_date->toDateString()
                            && ($leave->is_paid_snapshot ?? $leave->leaveType?->is_paid) === true);

                        return $paidHalf ? 1 - (float) $paidHalf->day_fraction : 1;
                    });
                $unpaidLeaveDays = $employeeRecords
                    ->where('status', 'leave')
                    ->where('source', 'leave')
                    ->filter(fn (AttendanceRecord $record) => $this->isUnpaidLeave(
                        $record,
                        $legacyLeaveRequests->get($employeeId, collect())
                    ))
                    ->count() + $employeePartial
                    ->filter(fn (EmployeeLeaveRequest $leave) =>
                        ($leave->is_paid_snapshot ?? $leave->leaveType?->is_paid) === false
                        && ! $employeeRecords->contains(fn (AttendanceRecord $record) =>
                            $record->work_date->toDateString() === $leave->start_date->toDateString()
                            && $record->status === 'absent'
                            && $this->features->absenceDeductionEnabled()))
                    ->sum(fn (EmployeeLeaveRequest $leave) => (float) $leave->day_fraction);

                $deductionAmount = 0.0;

                if ($this->features->lateDeductionEnabled()) {
                    $deductionAmount += (($lateMinutes + $earlyMinutes) / 60) * $hourlyRate;
                }

                if ($this->features->absenceDeductionEnabled()) {
                    $deductionAmount += $absenceDays * $dailyRate;
                }

                $deductionAmount += $unpaidLeaveDays * $dailyRate;

                $overtimeMultiplier =
                    $this->features->overtimeMultiplier();

                $overtimeAmount =
                    $this->features->overtimeEnabled()
                        ? (
                            ($overtimeMinutes / 60)
                            * $hourlyRate
                            * $overtimeMultiplier
                        )
                        : 0.0;

                if ($deductionAmount > 0.0001) {
                    EmployeePayrollAdjustment::create([
                        'employee_id' => $employeeId,
                        'payroll_period_id' => $period->id,
                        'kind' => 'deduction',
                        'name' => 'خصم الحضور والإجازات غير المدفوعة',
                        'amount' => round($deductionAmount, 4),
                        'is_recurring' => false,
                        'status' => 'active',
                        'notes' => 'تم إنشاؤه تلقائيًا من الحضور المعتمد.',
                        'source_type' => 'attendance_summary',
                        'source_id' => $period->id,
                        'metadata' => [
                            'late_minutes' => $lateMinutes,
                            'early_leave_minutes' => $earlyMinutes,
                            'absence_days' => $absenceDays,
                            'unpaid_leave_days' => $unpaidLeaveDays,
                            'hourly_rate' => $hourlyRate,
                            'daily_rate' => $dailyRate,
                        ],
                        'created_by' => $actor->id,
                    ]);
                    $created++;
                }

                if ($overtimeAmount > 0.0001) {
                    EmployeePayrollAdjustment::create([
                        'employee_id' => $employeeId,
                        'payroll_period_id' => $period->id,
                        'kind' => 'bonus',
                        'name' => 'بدل ساعات إضافية',
                        'amount' => round($overtimeAmount, 4),
                        'is_recurring' => false,
                        'status' => 'active',
                        'notes' => 'تم إنشاؤه تلقائيًا من الحضور المعتمد.',
                        'source_type' => 'attendance_summary',
                        'source_id' => $period->id,
                        'metadata' => [
                            'overtime_minutes' => $overtimeMinutes,
                            'hourly_rate' => $hourlyRate,
                            'multiplier' => $overtimeMultiplier,
                        ],
                        'created_by' => $actor->id,
                    ]);
                    $created++;
                }
            }

            return [
                'employees' => $employees,
                'adjustments' => $created,
            ];
        });
    }

    private function isUnpaidLeave(AttendanceRecord $record, Collection $legacyRequests): bool
    {
        $metadata = $record->verification_metadata ?? [];

        if (array_key_exists('is_paid', $metadata)) {
            return $metadata['is_paid'] === false;
        }

        $matching = $legacyRequests->filter(fn (EmployeeLeaveRequest $leave) =>
            $leave->start_date->lte($record->work_date)
            && $leave->end_date->gte($record->work_date));

        return $matching->count() === 1
            && $matching->first()->leaveType?->is_paid === false;
    }

    private function hourlyRate(EmployeeCompensationProfile $profile): float
    {
        $salary = (float) $profile->base_salary;
        $hoursPerDay =
            $this->features->standardHoursPerDay();

        return match ($profile->salary_basis) {
            'hourly' => $salary,
            'daily' => $salary / $hoursPerDay,
            default => $salary / (
                $this->features->standardWorkDaysPerMonth()
                * $hoursPerDay
            ),
        };
    }

    private function dailyRate(EmployeeCompensationProfile $profile): float
    {
        $salary = (float) $profile->base_salary;

        return match ($profile->salary_basis) {
            'hourly' => $salary
                * $this->features->standardHoursPerDay(),
            'daily' => $salary,
            default => $salary
                / $this->features->standardWorkDaysPerMonth(),
        };
    }
}
