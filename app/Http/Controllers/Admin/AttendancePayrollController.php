<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use App\Services\AttendancePayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendancePayrollController extends Controller
{
    public function sync(
        Request $request,
        PayrollPeriod $period,
        AttendancePayrollService $attendancePayroll
    ): RedirectResponse {
        $result = $attendancePayroll->sync(
            $period,
            $request->user()
        );

        return back()->with(
            'success',
            'تمت مزامنة الحضور مع الرواتب: '
            . $result['employees']
            . ' موظف، '
            . $result['adjustments']
            . ' حركة راتب.'
        );
    }
}
