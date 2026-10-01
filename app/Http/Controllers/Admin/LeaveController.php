<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveCarryover;
use App\Models\LeaveType;
use App\Services\AttendanceService;
use App\Services\EmployeeLeaveService;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly EmployeeLeaveService $leaves
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'leave_type_id' => ['nullable', 'integer', Rule::exists('leave_types', 'id')],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $user = $request->user();
        $year = (int) ($filters['year'] ?? now()->year);
        $accessibleEmployees = $this->accessibleEmployees($user);
        $selectedEmployee = isset($filters['employee_id'])
            ? (clone $accessibleEmployees)->findOrFail($filters['employee_id'])
            : null;

        $scope = EmployeeLeaveRequest::query()
            ->whereIn('employee_id', (clone $accessibleEmployees)->select('employees.id'))
            ->when($selectedEmployee, fn (Builder $q) => $q->where('employee_id', $selectedEmployee->id))
            ->when($filters['leave_type_id'] ?? null, fn (Builder $q, $id) => $q->where('leave_type_id', $id))
            ->when($filters['year'] ?? null, fn (Builder $q) => $q
                ->whereDate('start_date', '<=', "{$year}-12-31")
                ->whereDate('end_date', '>=', "{$year}-01-01"));

        // The status filter changes the list, not the counters. Otherwise an
        // approved request disappears from the pending list while the approved
        // counter stays at zero until the filter is cleared.
        $totals = (clone $scope)
            ->selectRaw('status, COUNT(*) as request_count, COALESCE(SUM(total_days), 0) as days')
            ->groupBy('status')
            ->get()
            ->keyBy('status');
        $totalLeaveDays = (float) $totals->sum('days');
        if (isset($filters['year'])) {
            $totalLeaveDays = 0.0;
            (clone $scope)->chunkById(500, function ($leaves) use (&$totalLeaveDays, $year): void {
                foreach ($leaves as $leave) {
                    $totalLeaveDays += $leave->daysForYear($year);
                }
            });
        }
        $query = (clone $scope)
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status));

        $leaveTypes = LeaveType::query()->orderBy('name')->get();

        return view('admin.attendance.leaves', [
            'requests' => $query
                ->with(['employee', 'leaveType'])
                ->latest('start_date')
                ->latest('id')
                ->paginate(30)->withQueryString(),
            'employees' => (clone $accessibleEmployees)->orderBy('full_name')->get(),
            'leaveTypes' => $leaveTypes,
            'totals' => $totals,
            'totalLeaveDays' => $totalLeaveDays,
            'filters' => $filters,
            'selectedEmployee' => $selectedEmployee,
            'balanceYear' => $year,
            'balances' => $selectedEmployee
                ? $this->leaves->balances($selectedEmployee, $leaveTypes, $year)
                : [],
            'carryovers' => $selectedEmployee
                ? EmployeeLeaveCarryover::query()->where('employee_id', $selectedEmployee->id)
                    ->where('year', $year)->get()->keyBy('leave_type_id')
                : collect(),
        ]);
    }

    public function carryover(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')],
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')],
            'year' => ['required', 'integer', 'between:2001,2100'],
            'days' => ['required', 'numeric', 'min:0', 'max:366'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $employee = $this->accessibleEmployees($request->user())->find($data['employee_id']);
        abort_unless($employee, 403, 'لا يمكنك إدارة رصيد موظف في فرع آخر.');

        $this->leaves->grantCarryover(
            $employee, LeaveType::query()->findOrFail($data['leave_type_id']),
            (int) $data['year'], (float) $data['days'],
            $request->user()->id, $data['note'] ?? null
        );

        return redirect()->route('attendance.leaves.index', [
            'employee_id' => $employee->id, 'year' => $data['year'],
        ])->with('success', 'تم حفظ الرصيد المرحّل للموظف.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')],
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1500'],
            'day_fraction' => ['nullable', Rule::in(['1', '0.5'])],
            'half_day_slot' => ['nullable', Rule::in(['first_half', 'second_half'])],
        ]);

        $employee = $this->accessibleEmployees($request->user())
            ->find($data['employee_id']);

        abort_unless($employee, 403, 'لا يمكنك إدارة إجازات هذا الموظف.');

        $this->leaves->create(
            $employee,
            LeaveType::query()->findOrFail($data['leave_type_id']),
            $data,
            $request->user()->id
        );

        return back()->with('success', 'تم تسجيل طلب الإجازة.');
    }

    public function approve(
        Request $request,
        EmployeeLeaveRequest $leave
    ): RedirectResponse {
        $this->assertAccessible($request->user(), $leave);

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
        $this->assertAccessible($request->user(), $leave);

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

    private function accessibleEmployees(User $user): Builder
    {
        if ($user->isAdmin() || $user->can('employees.view_all')) {
            return Employee::query();
        }

        $locationId = $user->primaryLocation()?->id;

        abort_unless($locationId, 403, 'لا يوجد فرع مرتبط بالمستخدم.');

        return Employee::query()->whereHas('employeeLocations', fn (Builder $query) => $query
            ->where('location_id', $locationId)
            ->where('is_primary', true)
            ->whereNull('ended_at'));
    }

    private function assertAccessible(User $user, EmployeeLeaveRequest $leave): void
    {
        abort_unless(
            $this->accessibleEmployees($user)->whereKey($leave->employee_id)->exists(),
            403,
            'لا يمكنك إدارة إجازات هذا الموظف.'
        );
    }
}
