<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AttendanceFeatureService;
use App\Services\FaceAttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendancePayrollSettingsController extends Controller
{
    public function __construct(
        private readonly AttendanceFeatureService $features,
        private readonly FaceAttendanceService $faceAttendance
    ) {
    }

    public function edit(): View
    {
        return view(
            'admin.settings.attendance-payroll',
            [
                'settings' => $this->features->settings(),
                'faceAttendance' => [
                    'configured' =>
                        $this->faceAttendance->configured(),
                    'provider' =>
                        $this->faceAttendance->provider(),
                    'base_url' =>
                        $this->faceAttendance->baseUrl(),
                    'similarity_threshold' =>
                        $this->faceAttendance
                            ->similarityThreshold(),
                    'connection_test_url' =>
                        route(
                            'attendance.face.connection-test'
                        ),
                ],
            ]
        );
    }

    public function update(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'payroll_standard_work_days_per_month' => [
                'required',
                'numeric',
                'min:1',
                'max:31',
            ],
            'payroll_standard_hours_per_day' => [
                'required',
                'numeric',
                'min:1',
                'max:24',
            ],
            'payroll_overtime_multiplier' => [
                'required',
                'numeric',
                'min:0',
                'max:10',
            ],
        ]);

        $values = [
            'attendance_enabled' =>
                $request->boolean('attendance_enabled'),
            'attendance_biometric_enabled' =>
                $request->boolean('attendance_biometric_enabled'),
            'attendance_device_require_approval' =>
                $request->boolean('attendance_device_require_approval'),
            'payroll_late_deduction_enabled' =>
                $request->boolean('payroll_late_deduction_enabled'),
            'payroll_absence_deduction_enabled' =>
                $request->boolean('payroll_absence_deduction_enabled'),
            'payroll_overtime_enabled' =>
                $request->boolean('payroll_overtime_enabled'),
            'payroll_standard_work_days_per_month' =>
                $data['payroll_standard_work_days_per_month'],
            'payroll_standard_hours_per_day' =>
                $data['payroll_standard_hours_per_day'],
            'payroll_overtime_multiplier' =>
                $data['payroll_overtime_multiplier'],
        ];

        /*
         * إذا تم إيقاف الحضور بالكامل، نوقف البصمة منطقيًا أيضًا.
         * لا نحذف الأجهزة أو السجلات، فقط نوقف استخدامها.
         */
        if (! $values['attendance_enabled']) {
            $values['attendance_biometric_enabled'] = false;
        }

        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                SystemSetting::set(
                    $key,
                    is_bool($value)
                        ? ($value ? 1 : 0)
                        : $value
                );
            }
        });

        SystemSetting::flushCache();

        return redirect()
            ->route('settings.attendance-payroll.edit')
            ->with(
                'success',
                'تم حفظ إعدادات الحضور والرواتب.'
            );
    }
}
