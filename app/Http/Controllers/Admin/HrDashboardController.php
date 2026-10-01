<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeHrProfile;
use App\Models\EmployeeLeaveRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $staff = Employee::query()->accessibleBy($request->user());
        $ids = (clone $staff)->select('employees.id');
        $today = now()->toDateString();
        $deadline = now()->addDays(30)->toDateString();

        $leave = EmployeeLeaveRequest::query()->whereIn('employee_id', clone $ids)
            ->where('status', 'pending');
        $corrections = AttendanceCorrectionRequest::query()->whereIn('employee_id', clone $ids)
            ->where('status', 'pending');
        $absences = AttendanceRecord::query()->whereIn('employee_id', clone $ids)
            ->whereDate('work_date', $today)->where('status', 'absent');

        $documents = EmployeeDocument::query()->whereIn('employee_id', clone $ids)
            ->whereNotNull('expires_on')->whereDate('expires_on', '<=', $deadline);
        $contracts = EmployeeHrProfile::query()->whereIn('employee_id', clone $ids)
            ->whereNotNull('contract_ends_on')->whereDate('contract_ends_on', '<=', $deadline);

        return view('admin.hr.dashboard', [
            'employeeCount' => (clone $staff)->count(),
            'leaveCount' => (clone $leave)->count(),
            'correctionCount' => (clone $corrections)->count(),
            'absenceCount' => (clone $absences)->count(),
            'leaves' => (clone $leave)->with('employee')->oldest()->limit(10)->get(),
            'corrections' => (clone $corrections)->with('employee')->oldest()->limit(10)->get(),
            'documents' => $request->user()->can('hr.documents.view')
                ? $documents->with('employee')->orderBy('expires_on')->limit(10)->get()
                : collect(),
            'contracts' => $request->user()->can('hr.documents.view')
                ? $contracts->with('employee')->orderBy('contract_ends_on')->limit(10)->get()
                : collect(),
        ]);
    }
}
