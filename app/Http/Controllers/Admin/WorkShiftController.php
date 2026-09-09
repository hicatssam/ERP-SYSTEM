<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $employees = Employee::query()
            ->when(
                ! $canViewAllLocations,
                function ($query) use ($currentLocation): void {
                    $query->whereHas(
                        'employeeLocations',
                        function ($locationQuery) use ($currentLocation): void {
                            $locationQuery
                                ->where(
                                    'location_id',
                                    $currentLocation->id
                                )
                                ->where(
                                    'is_primary',
                                    true
                                )
                                ->whereNull('ended_at');
                        }
                    );
                }
            )
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
                            ->whereNull('ended_at')
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

                'effective_from' => [
                    'required',
                    'date',
                ],

                'effective_to' => [
                    'nullable',
                    'date',
                    'after_or_equal:effective_from',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | Employee Scope
        |--------------------------------------------------------------------------
        */
        $employee =
            Employee::query()
                ->when(
                    ! $canViewAllLocations,
                    function ($query) use ($user): void {

                        $locationId =
                            $user
                                ->primaryLocation()
                                ?->id;

                        abort_unless(
                            $locationId,
                            403,
                            'لا يوجد فرع مرتبط بالمستخدم.'
                        );

                        $query->whereHas(
                            'employeeLocations',
                            function ($locationQuery) use ($locationId): void {
                                $locationQuery
                                    ->where(
                                        'location_id',
                                        $locationId
                                    )
                                    ->where(
                                        'is_primary',
                                        true
                                    )
                                    ->whereNull(
                                        'ended_at'
                                    );
                            }
                        );
                    }
                )
                ->findOrFail(
                    $data['employee_id']
                );

        /*
        |--------------------------------------------------------------------------
        | Shift Scope
        |--------------------------------------------------------------------------
        */
        $shift =
            WorkShift::query()
                ->when(
                    ! $canViewAllLocations,
                    function ($query) use ($user): void {

                        $locationId =
                            $user
                                ->primaryLocation()
                                ?->id;

                        $query->where(
                            'location_id',
                            $locationId
                        );
                    }
                )
                ->findOrFail(
                    $data['work_shift_id']
                );

        /*
        |--------------------------------------------------------------------------
        | Employee / Shift Location Match
        |--------------------------------------------------------------------------
        */
        if ($shift->location_id) {

            $employeeLocationId =
                $this->employeePrimaryLocationId(
                    $employee
                );

            if (
                (int) $employeeLocationId
                !== (int) $shift->location_id
            ) {
                throw ValidationException::withMessages([
                    'work_shift_id' =>
                        'لا يمكن ربط الموظف بورديّة تابعة لفرع مختلف.',
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Close old primary assignment
        |--------------------------------------------------------------------------
        */
        EmployeeShiftAssignment::query()
            ->where(
                'employee_id',
                $employee->id
            )
            ->where(
                'is_primary',
                true
            )
            ->where(
                function ($query) use ($data): void {
                    $query
                        ->whereNull(
                            'effective_to'
                        )
                        ->orWhereDate(
                            'effective_to',
                            '>=',
                            $data[
                                'effective_from'
                            ]
                        );
                }
            )
            ->update([
                'effective_to' =>
                    Carbon::parse(
                        $data[
                            'effective_from'
                        ]
                    )
                    ->subDay()
                    ->toDateString(),

                'is_primary' =>
                    false,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Create assignment
        |--------------------------------------------------------------------------
        */
        EmployeeShiftAssignment::create([
            'employee_id' =>
                $employee->id,

            'work_shift_id' =>
                $shift->id,

            'effective_from' =>
                $data[
                    'effective_from'
                ],

            'effective_to' =>
                $data[
                    'effective_to'
                ] ?? null,

            'is_primary' =>
                true,

            'created_by' =>
                $user->id,
        ]);

        return back()->with(
            'success',
            'تم ربط الموظف بالوردية.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Employee Primary Location
    |--------------------------------------------------------------------------
    */
    private function employeePrimaryLocationId(
        Employee $employee
    ): ?int {
        $locationId =
            $employee
                ->employeeLocations()
                ->where(
                    'is_primary',
                    true
                )
                ->whereNull(
                    'ended_at'
                )
                ->value(
                    'location_id'
                );

        return $locationId
            ? (int) $locationId
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Scope
    |--------------------------------------------------------------------------
    | فقط Admin يرى جميع الفروع والورديات.
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