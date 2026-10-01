<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceCorrectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ])['status'] ?? 'pending';

        $requests = AttendanceCorrectionRequest::query()
                ->whereIn('employee_id', $this->accessibleEmployees($request->user())->select('employees.id'))
                ->where('status', $status)
                ->with(['employee', 'requester.employee', 'reviewer'])
                ->latest('work_date')->latest('id')->paginate(25)->withQueryString();
        $records = $requests->count() === 0 ? collect() : AttendanceRecord::query()
            ->where(function (Builder $query) use ($requests): void {
                foreach ($requests as $correction) {
                    $query->orWhere(fn (Builder $day) => $day
                        ->where('employee_id', $correction->employee_id)
                        ->whereDate('work_date', $correction->work_date->toDateString()));
                }
            })->get()->keyBy(fn ($record) =>
                $record->employee_id.'|'.$record->work_date->toDateString());

        return view('admin.attendance.corrections', [
            'requests' => $requests,
            'records' => $records,
            'status' => $status,
        ]);
    }

    public function approve(
        Request $request,
        AttendanceCorrectionRequest $correction,
        AttendanceCorrectionService $service
    ): RedirectResponse {
        $this->assertAccessible($request->user(), $correction);
        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:1000']]);
        $service->review($correction, $request->user(), true, $data['decision_note'] ?? null);

        return back()->with('success', 'تم اعتماد التصحيح وتحديث الحضور.');
    }

    public function reject(
        Request $request,
        AttendanceCorrectionRequest $correction,
        AttendanceCorrectionService $service
    ): RedirectResponse {
        $this->assertAccessible($request->user(), $correction);
        $data = $request->validate(['decision_note' => ['required', 'string', 'max:1000']]);
        $service->review($correction, $request->user(), false, $data['decision_note'] ?? null);

        return back()->with('success', 'تم رفض طلب التصحيح.');
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

    private function assertAccessible(User $user, AttendanceCorrectionRequest $correction): void
    {
        abort_unless(
            $this->accessibleEmployees($user)->whereKey($correction->employee_id)->exists(),
            403,
            'لا يمكنك مراجعة طلب تصحيح تابع لفرع آخر.'
        );
    }
}
