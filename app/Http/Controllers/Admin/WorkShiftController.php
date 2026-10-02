<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkShiftController extends Controller
{
    public function index(
        Request $request
    ): View {
        $user = $request->user();

        $canViewAllLocations =
            $this->canViewAllLocations($user);

        $currentLocation =
            $canViewAllLocations
                ? null
                : $user->primaryLocation();

        abort_if(
            ! $canViewAllLocations
            && ! $currentLocation,
            403,
            'لا يوجد فرع مرتبط بالمستخدم الحالي.'
        );

        /*
        |--------------------------------------------------------------------------
        | Employees
        |--------------------------------------------------------------------------
        | Admin:
        |   كل الموظفين.
        |
        | Branch Manager:
        |   موظفو فرعه فقط.
        |--------------------------------------------------------------------------
        */
        $employees = $this->scopedEmployees($user)
            ->where(
                'employment_status',
                'active'
            )
            ->with([
                'employeeLocations' =>
                    function ($query): void {
                        $query
                            ->where(
                                'is_primary',
                                true
                            )
                            ->where(fn ($dates) => $dates->whereNull('started_at')
                                ->orWhereDate('started_at', '<=', now()->toDateString()))
                            ->where(fn ($dates) => $dates->whereNull('ended_at')
                                ->orWhereDate('ended_at', '>=', now()->toDateString()))
                            ->with(
                                'location:id,name'
                            );
                    },
            ])
            ->orderBy('full_name')
            ->get();

        $employeeIds =
            $employees->pluck('id');

        /*
        |--------------------------------------------------------------------------
        | Work Shifts
        |--------------------------------------------------------------------------
        | Admin:
        |   كل الورديات.
        |
        | Branch Manager:
        |   ورديات الفرع الحالي فقط.
        |--------------------------------------------------------------------------
        */
        $shifts = WorkShift::query()
            ->with(
                'location:id,name'
            )
            ->when(
                ! $canViewAllLocations,
                fn ($query) =>
                    $query->where(
                        'location_id',
                        $currentLocation->id
                    )
            )
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Locations
        |--------------------------------------------------------------------------
        | Admin:
        |   كل المواقع.
        |
        | Branch Manager:
        |   فرعه فقط.
        |--------------------------------------------------------------------------
        */
        $locations = Location::query()
            ->when(
                ! $canViewAllLocations,
                fn ($query) =>
                    $query->whereKey(
                        $currentLocation->id
                    )
            )
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Current Assignments
        |--------------------------------------------------------------------------
        */
        $assignments =
            EmployeeShiftAssignment::query()
                ->with([
                    'employee.employeeLocations.location',
                    'employee.currentOrgAssignment.department',
                    'employee.currentOrgAssignment.position',
                    'shift.location',
                ])
                ->whereIn(
                    'employee_id',
                    $employeeIds
                )
                ->where(
                    'is_primary',
                    true
                )
                ->where(function ($query): void {
                    $query
                        ->whereNull(
                            'effective_to'
                        )
                        ->orWhereDate(
                            'effective_to',
                            '>=',
                            now()->toDateString()
                        );
                })
                ->when(
                    ! $canViewAllLocations,
                    function ($query) use ($currentLocation): void {
                        $query->whereHas(
                            'shift',
                            fn ($shiftQuery) =>
                                $shiftQuery->where(
                                    'location_id',
                                    $currentLocation->id
                                )
                        );
                    }
                )
                ->latest(
                    'effective_from'
                )
                ->get();

        return view(
            'admin.attendance.shifts',
            [
                'shifts' =>
                    $shifts,

                'employees' =>
                    $employees,

                'locations' =>
                    $locations,

                'assignments' =>
                    $assignments,

                'canViewAllLocations' =>
                    $canViewAllLocations,

                'currentLocation' =>
                    $currentLocation,
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $user =
            $request->user();

        $canViewAllLocations =
            $this->canViewAllLocations(
                $user
            );

        $currentLocation =
            $canViewAllLocations
                ? null
                : $user->primaryLocation();

        abort_if(
            ! $canViewAllLocations
            && ! $currentLocation,
            403,
            'لا يوجد فرع مرتبط بالمستخدم الحالي.'
        );

        $data =
            $request->validate([
                'code' => [
                    'required',
                    'string',
                    'max:40',

                    Rule::unique(
                        'work_shifts',
                        'code'
                    ),
                ],

                'name' => [
                    'required',
                    'string',
                    'max:190',
                ],

                'location_id' => [
                    'nullable',
                    Rule::exists(
                        'locations',
                        'id'
                    ),
                ],

                'start_time' => [
                    'required',
                    'date_format:H:i',
                ],

                'end_time' => [
                    'required',
                    'date_format:H:i',
                ],

                'break_minutes' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:600',
                ],

                'grace_minutes' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:180',
                ],

                'overtime_after_minutes' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:240',
                ],

                'work_days' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'work_days.*' => [
                    'integer',
                    'between:1,7',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | Location Enforcement
        |--------------------------------------------------------------------------
        | مدير الفرع لا يستطيع:
        |
        | - إنشاء وردية لفرع آخر
        | - إنشاء وردية لكل المواقع
        | - تغيير location_id عبر DevTools
        |--------------------------------------------------------------------------
        */
        if (! $canViewAllLocations) {

            $data['location_id'] =
                $currentLocation->id;

        } elseif (
            ! empty(
                $data['location_id']
            )
        ) {

            /*
             * Admin selected a specific location.
             */
            Location::query()
                ->findOrFail(
                    $data['location_id']
                );
        }

        WorkShift::create([
            ...$data,

            'is_active' => true,

            'created_by' =>
                $user->id,
        ]);

        return back()->with(
            'success',
            'تم إنشاء الوردية.'
        );
    }

    public function assign(
        Request $request
    ): RedirectResponse {
        $user =
            $request->user();

        $canViewAllLocations =
            $this->canViewAllLocations(
                $user
            );

        $data =
            $request->validate([
                'employee_id' => [
                    'required',

                    Rule::exists(
                        'employees',
                        'id'
                    ),
                ],

                'work_shift_id' => [
                    'required',

                    Rule::exists(
                        'work_shifts',
                        'id'
                    ),
                ],

                'effective_from' => ['required', 'date_format:Y-m-d'],

                'effective_to' => [
                    'nullable',
                    'date_format:Y-m-d',
                    'after_or_equal:effective_from',
                ],
            ]);

        DB::transaction(function () use ($user, $data, $canViewAllLocations): void {
            $employee = $this->scopedEmployees($user)
                ->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            if (! $employee->isActive()) {
                throw ValidationException::withMessages(['employee_id' => 'يمكن تعيين وردية لموظف نشط فقط.']);
            }

            $shift = WorkShift::query()
                ->when(! $canViewAllLocations, fn ($query) => $query
                    ->where('location_id', $user->primaryLocation()?->id))
                ->findOrFail($data['work_shift_id']);
            if (! $shift->is_active) {
                throw ValidationException::withMessages(['work_shift_id' => 'الوردية المختارة غير فعالة.']);
            }

            $from = Carbon::parse($data['effective_from']);
            if ($employee->hire_date && $from->lt($employee->hire_date)) {
                throw ValidationException::withMessages(['effective_from' => 'تاريخ الوردية يسبق تعيين الموظف.']);
            }
            $location = $employee->employeeLocations()->where('is_primary', true)
                ->where(fn ($query) => $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', $data['effective_from']))
                ->where(fn ($query) => $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $data['effective_from']))
                ->orderByDesc('started_at')->first();
            if (! $location) {
                throw ValidationException::withMessages(['employee_id' => 'لا يوجد فرع أساسي للموظف في تاريخ الوردية.']);
            }
            if ($shift->location_id && (int) $shift->location_id !== (int) $location->location_id) {
                throw ValidationException::withMessages(['work_shift_id' => 'لا يمكن ربط الموظف بوردية فرع مختلف في تاريخ سريانها.']);
            }
            if (! $canViewAllLocations && (int) $location->location_id !== (int) $user->primaryLocation()?->id) {
                throw ValidationException::withMessages(['work_shift_id' => 'لا يمكنك تعيين وردية بعد انتقال الموظف إلى فرع آخر.']);
            }

            $end = $data['effective_to'] ?? null;
            if ($location->ended_at && (! $end || $end > $location->ended_at->toDateString())) {
                $end = $location->ended_at->toDateString();
            }

            $last = EmployeeShiftAssignment::query()->where('employee_id', $employee->id)
                ->where('is_primary', true)->orderByDesc('effective_from')
                ->orderByDesc('id')->lockForUpdate()->first();
            if ($last && $last->effective_from->gte($from)) {
                throw ValidationException::withMessages(['effective_from' => 'تاريخ الوردية يجب أن يأتي بعد آخر تعيين مسجل.']);
            }
            if ($last && (! $last->effective_to || $last->effective_to->gte($from))) {
                // The preceding interval remains primary for its historical days.
                $last->update(['effective_to' => $from->copy()->subDay()->toDateString()]);
            }

            EmployeeShiftAssignment::query()->create([
                'employee_id' => $employee->id,
                'work_shift_id' => $shift->id,
                'effective_from' => $data['effective_from'],
                'effective_to' => $end,
                'is_primary' => true,
                'created_by' => $user->id,
            ]);
        });

        return back()->with(
            'success',
            'تم ربط الموظف بالوردية.'
        );
    }

    private function scopedEmployees(User $user): Builder
    {
        if ($this->canViewAllLocations($user)) {
            return Employee::query();
        }

        $locationId = $user->primaryLocation()?->id;
        abort_unless($locationId, 403, 'لا يوجد فرع مرتبط بالمستخدم.');
        $date = now()->toDateString();

        return Employee::query()->whereHas('employeeLocations', fn (Builder $query) => $query
            ->where('location_id', $locationId)->where('is_primary', true)
            ->where(fn (Builder $dates) => $dates->whereNull('started_at')
                ->orWhereDate('started_at', '<=', $date))
            ->where(fn (Builder $dates) => $dates->whereNull('ended_at')
                ->orWhereDate('ended_at', '>=', $date)));
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Scope
    |--------------------------------------------------------------------------
    */
    private function canViewAllLocations(
        User $user
    ): bool {
        return method_exists(
            $user,
            'isAdmin'
        )
            && $user->isAdmin();
    }
}
