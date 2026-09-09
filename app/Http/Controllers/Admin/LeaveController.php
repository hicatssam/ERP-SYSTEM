<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveType;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance
    ) {
    }

    public function index(): View
    {
        return view('admin.attendance.leaves', [
            'requests' => EmployeeLeaveRequest::query()
                ->with(['employee', 'leaveType'])
                ->latest('start_date')
                ->latest('id')
                ->paginate(30),
            'employees' => Employee::query()->orderBy('full_name')->get(),
            'leaveTypes' => LeaveType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')],
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1500'],
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        EmployeeLeaveRequest::create([
            ...$data,
            'total_days' => $start->diffInDays($end) + 1,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'تم تسجيل طلب الإجازة.');
    }

    public function approve(
        Request $request,
        EmployeeLeaveRequest $leave
    ): RedirectResponse {
        $data = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->attendance->approveLeave(
            $leave,
            $request->user(),
            $data['decision_note'] ?? null
        );

        return back()->with(
            'success',
            'تم اعتماد الإجازة وتحديث سجلات الحضور.'
        );
    }

    public function reject(
        Request $request,
        EmployeeLeaveRequest $leave
    ): RedirectResponse {
        $data = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->attendance->rejectLeave(
            $leave,
            $request->user(),
            $data['decision_note'] ?? null
        );

        return back()->with('success', 'تم رفض طلب الإجازة.');
    }
}
