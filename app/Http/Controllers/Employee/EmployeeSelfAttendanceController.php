<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\EmployeeSelfAttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeSelfAttendanceController extends Controller
{
    public function punch(Request $request, EmployeeSelfAttendanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['check_in', 'check_out'])],
        ]);

        $submission = $service->punch(
            $request->user(), $data['action'], $request->ip(), $request->userAgent()
        );

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'attendance.self.'.$data['action'],
            module: 'attendance',
            recordType: 'employee_self_attendance_requests',
            recordId: $submission->id,
            newValues: [
                'employee_id' => $submission->employee_id,
                'work_date' => $submission->work_date->toDateString(),
                'check_in_at' => $submission->check_in_at->toDateTimeString(),
                'check_out_at' => $submission->check_out_at?->toDateTimeString(),
            ],
            metadata: ['location_id' => $submission->location_id],
            ipAddress: $request->ip()
        );

        return redirect()->route('my-hr.index')->with('success',
            $data['action'] === 'check_in'
                ? 'تم تسجيل وقت حضورك. سجّل الانصراف عند نهاية الدوام؛ سيُراجع المسؤول الطلب بعدها.'
                : 'تم تسجيل وقت الانصراف. طلب الدوام جاهز لمراجعة المسؤول.'
        );
    }
}
