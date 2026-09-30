<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Admin\PayrollDocumentController;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollItem;
use App\Models\User;
use App\Services\AttendanceCorrectionService;
use App\Services\AttendanceService;
use App\Services\EmployeeLeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MyHrController extends Controller
{
    public function index(
        Request $request,
        AttendanceService $attendance,
        EmployeeLeaveService $leaves
    ): View {
        $employee = $this->employee($request->user());
        $types = LeaveType::query()->where('is_active', true)->orderBy('name')->get();

        return view('employee.my-hr.index', [
            'employee' => $employee,
            'shift' => $attendance->shiftFor($employee, now()->toDateString()),
            'records' => AttendanceRecord::query()->where('employee_id', $employee->id)
                ->latest('work_date')->limit(20)->get(),
            'leaveTypes' => $types,
            'balances' => $leaves->balances($employee, $types, now()->year),
            'leaveRequests' => EmployeeLeaveRequest::query()
                ->where('employee_id', $employee->id)->with('leaveType')
                ->latest()->limit(10)->get(),
            'corrections' => AttendanceCorrectionRequest::query()
                ->where('employee_id', $employee->id)->latest()->limit(10)->get(),
            'payslips' => PayrollItem::query()->where('employee_id', $employee->id)
                ->whereHas('period', fn ($query) => $query
                    ->whereIn('status', ['approved', 'paid', 'closed']))
                ->with('period')->latest('payroll_period_id')->limit(8)->get(),
        ]);
    }

    public function leave(Request $request, EmployeeLeaveService $leaves): RedirectResponse
    {
        $employee = $this->employee($request->user());
        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1500'],
        ]);
        $leaves->create($employee, LeaveType::query()->findOrFail($data['leave_type_id']), $data, $request->user()->id);

        return redirect()->route('my-hr.index')->with('success', 'تم إرسال طلب الإجازة للمراجعة.');
    }

    public function correction(
        Request $request,
        AttendanceCorrectionService $corrections
    ): RedirectResponse {
        $employee = $this->employee($request->user());
        $data = $request->validate([
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        if (empty($data['check_in_at']) && empty($data['check_out_at'])) {
            throw ValidationException::withMessages(['check_in_at' => 'أدخل وقت حضور أو انصراف.']);
        }

        $date = $data['work_date'];
        foreach (['check_in_at', 'check_out_at'] as $field) {
            if (empty($data[$field])) {
                continue;
            }
            $day = \Carbon\Carbon::parse($data[$field])->toDateString();
            $allowed = $field === 'check_out_at'
                ? [$date, \Carbon\Carbon::parse($date)->addDay()->toDateString()]
                : [$date];
            if (! in_array($day, $allowed, true)) {
                throw ValidationException::withMessages([
                    $field => 'يجب أن يتوافق وقت التصحيح مع يوم الدوام المختار.',
                ]);
            }
        }

        $corrections->create($employee, $request->user(), $data);

        return redirect()->route('my-hr.index')->with('success', 'تم إرسال طلب تصحيح الحضور للمراجعة.');
    }

    public function payslip(Request $request, PayrollItem $item, PayrollDocumentController $documents): View
    {
        $employee = $this->employee($request->user());

        abort_unless(
            (int) $item->employee_id === (int) $employee->id
                && in_array($item->period?->status, ['approved', 'paid', 'closed'], true),
            403
        );

        return $documents->payslip($item);
    }

    private function employee(User $user): \App\Models\Employee
    {
        $employee = $user->employee;
        abort_unless($employee && $employee->isActive(), 403, 'حسابك غير مرتبط بموظف نشط.');
        return $employee;
    }
}
