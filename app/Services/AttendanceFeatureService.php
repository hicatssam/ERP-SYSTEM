<?php

namespace App\Services;

use App\Models\SystemSetting;

class AttendanceFeatureService
{
    public function attendanceEnabled(): bool
    {
        return (bool) SystemSetting::get(
            'attendance_enabled',
            true
        );
    }

    public function biometricEnabled(): bool
    {
        return $this->attendanceEnabled()
            && (bool) SystemSetting::get(
                'attendance_biometric_enabled',
                false
            );
    }

    public function deviceApprovalRequired(): bool
    {
        return (bool) SystemSetting::get(
            'attendance_device_require_approval',
            true
        );
    }

    public function lateDeductionEnabled(): bool
    {
        return (bool) SystemSetting::get(
            'payroll_late_deduction_enabled',
            true
        );
    }

    public function absenceDeductionEnabled(): bool
    {
        return (bool) SystemSetting::get(
            'payroll_absence_deduction_enabled',
            true
        );
    }

    public function overtimeEnabled(): bool
    {
        return (bool) SystemSetting::get(
            'payroll_overtime_enabled',
            true
        );
    }

    public function standardWorkDaysPerMonth(): float
    {
        return max(
            1,
            (float) SystemSetting::get(
                'payroll_standard_work_days_per_month',
                26
            )
        );
    }

    public function standardHoursPerDay(): float
    {
        return max(
            1,
            (float) SystemSetting::get(
                'payroll_standard_hours_per_day',
                8
            )
        );
    }

    public function overtimeMultiplier(): float
    {
        return max(
            0,
            (float) SystemSetting::get(
                'payroll_overtime_multiplier',
                1.5
            )
        );
    }

    public function settings(): array
    {
        return [
            'attendance_enabled' => $this->attendanceEnabled(),
            'attendance_biometric_enabled' => $this->biometricEnabled(),
            'attendance_device_require_approval' => $this->deviceApprovalRequired(),
            'payroll_late_deduction_enabled' => $this->lateDeductionEnabled(),
            'payroll_absence_deduction_enabled' => $this->absenceDeductionEnabled(),
            'payroll_overtime_enabled' => $this->overtimeEnabled(),
            'payroll_standard_work_days_per_month' => $this->standardWorkDaysPerMonth(),
            'payroll_standard_hours_per_day' => $this->standardHoursPerDay(),
            'payroll_overtime_multiplier' => $this->overtimeMultiplier(),
        ];
    }
}
