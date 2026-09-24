<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\AttendanceFeatureService;
use App\Services\AttendanceService;
use App\Services\FaceAttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly AttendanceFeatureService $features,
        private readonly FaceAttendanceService $faceAttendance
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $canViewAllLocations = $this->canViewAllLocations($user);

        $currentLocation = $canViewAllLocations
            ? null
            : $user->primaryLocation();

        abort_if(
            ! $canViewAllLocations && ! $currentLocation,
            403,
            'لا يوجد فرع مرتبط بالمستخدم الحالي.'
        );

        $date = $request->date('date')?->toDateString()
            ?? now()->toDateString();

        /*
        |--------------------------------------------------------------------------
        | الموظفون
        |--------------------------------------------------------------------------
        | Admin:
        |   كل الموظفين.
        |
        | Branch Manager:
        |   موظفو الفرع الأساسي للمستخدم فقط.
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
            ->where('employment_status', 'active')
            ->with([
                'faceProfile',
                'employeeLocations' => function ($query): void {
                    $query
                        ->where('is_primary', true)
                        ->whereNull('ended_at')
                        ->with('location:id,name');
                },
            ])
            ->orderBy('full_name')
            ->get();

        $employeeIds = $employees->pluck('id');

        /*
        |--------------------------------------------------------------------------
        | سجلات الحضور
        |--------------------------------------------------------------------------
        | لا نحمل أي سجل لموظف خارج نطاق الفرع.
        |--------------------------------------------------------------------------
        */
        $records = AttendanceRecord::query()
            ->with('shift')
            ->whereDate('work_date', $date)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        /*
        |--------------------------------------------------------------------------
        | الورديات المتاحة
        |--------------------------------------------------------------------------
        | Admin:
        |   كل الورديات الفعالة.
        |
        | Branch Manager:
        |   ورديات الفرع الحالي فقط.
        |--------------------------------------------------------------------------
        */
        $shifts = WorkShift::query()
            ->where('is_active', true)
            ->when(
                ! $canViewAllLocations,
                fn ($query) => $query->where(
                    'location_id',
                    $currentLocation->id
                )
            )
            ->with('location:id,name')
            ->orderBy('name')
            ->get();

        return view('admin.attendance.index', [
            'date' => $date,
            'employees' => $employees,
            'records' => $records,
            'shifts' => $shifts,

            'canViewAllLocations' => $canViewAllLocations,
            'currentLocation' => $currentLocation,
            'biometricAttendanceEnabled' =>
                $this->features->biometricEnabled(),
            'faceAttendanceConfigured' =>
                $this->faceAttendance->configured(),
            'faceAttendanceWebhookRequired' =>
                $this->faceAttendance->requiresWebhook(),
        ]);
    }

    public function store(
        Request $request,
        Employee $employee
    ): RedirectResponse {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | حماية الموظف
        |--------------------------------------------------------------------------
        | لا نعتمد فقط على Route Model Binding.
        | مدير الفرع لا يمكنه تغيير employee ID من الرابط.
        |--------------------------------------------------------------------------
        */
        $this->assertEmployeeAccessible(
            $user,
            $employee
        );

        $data = $request->validate([
            'work_date' => [
                'required',
                'date',
            ],

            'work_shift_id' => [
                'nullable',
                Rule::exists('work_shifts', 'id'),
            ],

            'status' => [
                'required',
                Rule::in([
                    'present',
                    'absent',
                    'leave',
                    'holiday',
                    'weekend',
                ]),
            ],

            'check_in_at' => [
                'nullable',
                'date',
            ],

            'check_out_at' => [
                'nullable',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | حماية الوردية
        |--------------------------------------------------------------------------
        */
        if (! empty($data['work_shift_id'])) {

            $shift = WorkShift::query()
                ->findOrFail(
                    $data['work_shift_id']
                );

            $this->assertShiftAccessible(
                $user,
                $shift
            );

            /*
            |--------------------------------------------------------------------------
            | التأكد أن الموظف والوردية تابعان لنفس الفرع
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
                            'لا يمكن تطبيق وردية تابعة لفرع آخر على هذا الموظف.',
                    ]);
                }
            }
        }

        $this->attendance->saveRecord(
            $employee,
            $data,
            $user
        );

        return redirect()
            ->route(
                'attendance.index',
                [
                    'date' => $data['work_date'],
                ]
            )
            ->with(
                'success',
                'تم حفظ سجل الحضور.'
            );
    }

    public function approve(
        Request $request,
        AttendanceRecord $record
    ): RedirectResponse {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | حماية اعتماد سجل الحضور
        |--------------------------------------------------------------------------
        */
        $employee = Employee::query()
            ->findOrFail(
                $record->employee_id
            );

        $this->assertEmployeeAccessible(
            $user,
            $employee
        );

        $this->attendance->approveRecord(
            $record,
            $user
        );

        return back()->with(
            'success',
            'تم اعتماد سجل الحضور.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Employee access
    |--------------------------------------------------------------------------
    */
    private function assertEmployeeAccessible(
        User $user,
        Employee $employee
    ): void {
        if ($this->canViewAllLocations($user)) {
            return;
        }

        $locationId =
            $user->primaryLocation()?->id;

        abort_unless(
            $locationId,
            403,
            'لا يوجد فرع مرتبط بالمستخدم.'
        );

        $allowed = Employee::query()
            ->whereKey($employee->id)
            ->whereHas(
                'employeeLocations',
                function ($query) use ($locationId): void {
                    $query
                        ->where(
                            'location_id',
                            $locationId
                        )
                        ->where(
                            'is_primary',
                            true
                        )
                        ->whereNull('ended_at');
                }
            )
            ->exists();

        abort_unless(
            $allowed,
            403,
            'لا يمكنك إدارة حضور موظف تابع لفرع آخر.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Shift access
    |--------------------------------------------------------------------------
    */
    private function assertShiftAccessible(
        User $user,
        WorkShift $shift
    ): void {
        if ($this->canViewAllLocations($user)) {
            return;
        }

        $locationId =
            $user->primaryLocation()?->id;

        abort_unless(
            $locationId
            && (int) $shift->location_id
                === (int) $locationId,
            403,
            'هذه الوردية لا تتبع فرعك.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Employee primary location
    |--------------------------------------------------------------------------
    */
    private function employeePrimaryLocationId(
        Employee $employee
    ): ?int {
        $locationId = $employee
            ->employeeLocations()
            ->where('is_primary', true)
            ->whereNull('ended_at')
            ->value('location_id');

        return $locationId
            ? (int) $locationId
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Global attendance scope
    |--------------------------------------------------------------------------
    | فقط الـAdmin يرى جميع الفروع.
    |--------------------------------------------------------------------------
    */
    private function canViewAllLocations(
        User $user
    ): bool {
        return method_exists($user, 'isAdmin')
            && $user->isAdmin();
    }
}