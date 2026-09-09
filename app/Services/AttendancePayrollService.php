<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\EmployeeCompensationProfile;
use App\Models\EmployeePayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
                ->whereBetween('work_date', [
                    $period->start_date->toDateString(),
                    $period->end_date->toDateString(),
                ])
                ->whereNotNull('approved_at')
                ->get()
                ->groupBy('employee_id');

            $created = 0;
            $employees = 0;

            foreach ($records as $employeeId => $employeeRecords) {
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

                $lateMinutes = (int) $employeeRecords->sum('late_minutes');
                $earlyMinutes = (int) $employeeRecords->sum('early_leave_minutes');
                $overtimeMinutes = (int) $employeeRecords->sum('overtime_minutes');
                $absenceDays = $employeeRecords->where('status', 'absent')->count();

                $deductionAmount = 0.0;

                if ($this->features->lateDeductionEnabled()) {
                    $deductionAmount += (($lateMinutes + $earlyMinutes) / 60) * $hourlyRate;
                }

                if ($this->features->absenceDeductionEnabled()) {
                    $deductionAmount += $absenceDays * $dailyRate;
                }

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
                        'name' => 'خصم الحضور والانضباط',
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
