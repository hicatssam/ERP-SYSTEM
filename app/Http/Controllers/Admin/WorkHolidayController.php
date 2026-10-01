<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\WorkHoliday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkHolidayController extends Controller
{
    public function index(): View
    {
        return view('admin.attendance.holidays', [
            'holidays' => WorkHoliday::query()->with('location')
                ->orderByDesc('holiday_date')->paginate(40),
            'locations' => Location::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:190'],
        ]);

        $locationId = $data['location_id'] ?? null;
        if (WorkHoliday::query()->whereDate('holiday_date', $data['holiday_date'])
            ->where('location_id', $locationId)->exists()) {
            throw ValidationException::withMessages([
                'holiday_date' => 'هذه العطلة مسجلة لهذا النطاق بالفعل.',
            ]);
        }

        WorkHoliday::query()->create($data);

        return back()->with('success', 'تم حفظ العطلة.');
    }

    public function destroy(WorkHoliday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('success', 'تم حذف العطلة. الطلبات المعتمدة السابقة محفوظة كما هي.');
    }
}
