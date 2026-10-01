<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.attendance.leave-types', [
            'leaveTypes' => LeaveType::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        LeaveType::query()->create($data);

        return back()->with('success', 'تم إضافة نوع الإجازة.');
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $data = $this->validated($request, $leaveType);
        $leaveType->update($data);

        return back()->with('success', 'تم تحديث نوع الإجازة.');
    }

    private function validated(Request $request, ?LeaveType $leaveType = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
            'count_basis' => $request->input('count_basis', $leaveType?->count_basis ?? 'calendar'),
            'accrual_mode' => $request->input('accrual_mode', $leaveType?->accrual_mode ?? 'annual'),
            'carryover_limit_days' => $request->input('carryover_limit_days', $leaveType?->carryover_limit_days ?? 0),
        ]);

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('leave_types', 'code')->ignore($leaveType?->id),
            ],
            'name' => ['required', 'string', 'max:190'],
            'annual_days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'notes' => ['nullable', 'string', 'max:1500'],
            'is_paid' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'count_basis' => ['required', Rule::in(['calendar', 'scheduled'])],
            'accrual_mode' => ['required', Rule::in(['annual', 'monthly'])],
            'carryover_limit_days' => ['required', 'numeric', 'min:0', 'max:366'],
        ]);

        return $data;
    }
}
