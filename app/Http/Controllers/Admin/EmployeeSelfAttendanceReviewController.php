<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSelfAttendanceRequest;
use App\Services\ActivityLogger;
use App\Services\EmployeeSelfAttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeSelfAttendanceReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ])['status'] ?? 'pending';
        $query = EmployeeSelfAttendanceRequest::query()->where('status', $status);

        if (! $request->user()->isAdmin() && ! $request->user()->can('employees.view_all')) {
            $locationId = $request->user()->primaryLocation()?->id;
            abort_unless($locationId, 403, 'لا يوجد فرع مرتبط بالمستخدم.');
            $query->where('location_id', $locationId);
        }

        return view('admin.attendance.self-requests', [
            'status' => $status,
            'requests' => $query->with(['employee:id,full_name,employee_number', 'location:id,name', 'reviewer:id,username'])
                ->latest('work_date')->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function approve(
        Request $request,
        EmployeeSelfAttendanceRequest $submission,
        EmployeeSelfAttendanceService $service
    ): RedirectResponse {
        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:1000']]);
        $reviewed = $service->review($submission, $request->user(), true, $data['decision_note'] ?? null);
        $this->logReview($request, $reviewed);

        return back()->with('success', 'تم اعتماد تسجيل الدوام وإضافته لسجل الحضور.');
    }

    public function reject(
        Request $request,
        EmployeeSelfAttendanceRequest $submission,
        EmployeeSelfAttendanceService $service
    ): RedirectResponse {
        $data = $request->validate(['decision_note' => ['required', 'string', 'max:1000']]);
        $reviewed = $service->review($submission, $request->user(), false, $data['decision_note']);
        $this->logReview($request, $reviewed);

        return back()->with('success', 'تم رفض طلب تسجيل الدوام مع توضيح السبب للموظف.');
    }

    private function logReview(Request $request, EmployeeSelfAttendanceRequest $submission): void
    {
        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'attendance.self.'.$submission->status,
            module: 'attendance',
            recordType: 'employee_self_attendance_requests',
            recordId: $submission->id,
            newValues: [
                'employee_id' => $submission->employee_id,
                'status' => $submission->status,
                'decision_note' => $submission->decision_note,
            ],
            metadata: ['location_id' => $submission->location_id],
            ipAddress: $request->ip()
        );
    }
}
