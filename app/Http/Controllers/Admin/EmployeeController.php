<?php
namespace App\Http\Controllers\Admin;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmployeeRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\EmployeeLocation;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Notifications\EmployeeCreatedNotification;
use App\Notifications\UserCreatedNotification;
use App\Services\Notifications\NotificationDispatcher;

class EmployeeController extends Controller
{
    private const EMPLOYEE_NUMBER_PREFIX = 'EMP';
    private const EMPLOYEE_NUMBER_PADDING = 3;

    public function index(Request $request)
    {
        $user = $request->user();

        $this->requirePermission(
            $user,
            ['employees.view', 'employees.view_all']
        );

        $canViewAll = $this->canViewAllEmployees($user);

        $primaryLocation = $canViewAll
            ? null
            : $this->managedLocation($user);

        $employees = Employee::query()
            ->when(
                ! $canViewAll,
                function ($query) use ($primaryLocation) {
                    $query->whereHas(
                        'employeeLocations',
                        fn ($employeeLocations) => $employeeLocations
                            ->where(
                                'employee_locations.location_id',
                                $primaryLocation->id
                            )
                            ->where(
                                'employee_locations.is_primary',
                                true
                            )
                    );
                }
            )
            ->with([
                'user.roles',

                'employeeLocations' => function ($query) use (
                    $canViewAll,
                    $primaryLocation
                ) {
                    if (! $canViewAll) {
                        $query->where(
                            'location_id',
                            $primaryLocation->id
                        );
                    }

                    $query->with('location');
                },
            ])
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        // حتى تبقى صفحات Blade الحالية متوافقة.
        $isAdmin = $canViewAll;

        return view('admin.employees.index', compact(
            'employees',
            'isAdmin',
            'primaryLocation'
        ));
    }

    public function create(Request $request)
    {
        $user = $request->user();

        $this->requirePermission($user, ['employees.create']);

        $canViewAll = $this->canViewAllEmployees($user);

        $primaryLocation = $canViewAll
            ? null
            : $this->managedLocation($user);

        $locations = $canViewAll
            ? Location::active()
                ->orderBy('name')
                ->get()
            : Location::active()
                ->whereKey($primaryLocation->id)
                ->get();

        $isAdmin = $canViewAll;

        /*
        |--------------------------------------------------------------------------
        | معاينة رقم الموظف القادم
        |--------------------------------------------------------------------------
        |
        | للعرض فقط. عند الحفظ يتم حساب الرقم مرة أخرى داخل Transaction
        | مع lockForUpdate حتى لا نعتمد على قيمة الصفحة.
        |
        */
        $nextEmployeeNumber = $this->nextEmployeeNumber();

        return view('admin.employees.create', compact(
            'locations',
            'isAdmin',
            'primaryLocation',
            'nextEmployeeNumber'
        ));
    }

    public function store(StoreEmployeeRequest $request)
    {
        $this->requirePermission(
            $request->user(),
            ['employees.create']
        );

        $primaryLocationId = $this->resolvePrimaryLocationId($request);

        $employee = DB::transaction(function () use (
            $request,
            $primaryLocationId
        ) {
            /*
            |--------------------------------------------------------------------------
            | قفل تسلسل أرقام الموظفين
            |--------------------------------------------------------------------------
            |
            | لا نقبل employee_number من الـRequest.
            | نقرأ أعلى رقم موجود - بما في ذلك الموظفون المحذوفون Soft Delete -
            | ثم نولد الرقم التالي.
            |
            */
            $employeeNumber = $this->nextEmployeeNumber(lockForUpdate: true);

            $validated = $request->validated();

            unset(
                $validated['employee_number'],
                $validated['profile_image'],
                $validated['primary_location_id']
            );

            $validated['employee_number'] = $employeeNumber;

            if ($request->hasFile('profile_image')) {
                $validated['profile_image'] = $request
                    ->file('profile_image')
                    ->store('employees/profile-images', 'public');
            }

            $employee = Employee::create($validated);

            if ($primaryLocationId) {
                EmployeeLocation::create([
                    'employee_id' => $employee->id,
                    'location_id' => $primaryLocationId,
                    'is_primary' => true,
                    'started_at' => now()->toDateString(),
                ]);
            }

            return $employee;
        });

        NotificationDispatcher::notifyByPermissions(
            new EmployeeCreatedNotification($employee),
            ['employees.view', 'employees.view_all', 'employees.update'],
            $primaryLocationId,
            ['employees.view_all'],
            $request->user()->id,
        );

        return redirect()
            ->route('employees.show', $employee)
            ->with(
                'success',
                "تم إنشاء الموظف بنجاح برقم {$employee->employee_number}."
            );
    }

    public function show(Request $request, Employee $employee)
    {
        $user = $request->user();

        $this->requirePermission(
            $user,
            ['employees.view', 'employees.view_all']
        );

        $this->ensureEmployeeAccess($employee, $user);
        $this->loadEmployeeRelations($employee, $user);

        return view('admin.employees.show', compact('employee'));
    }

    public function edit(Request $request, Employee $employee)
    {
        $user = $request->user();

        $this->requirePermission($user, ['employees.update']);
        $this->ensureEmployeeAccess($employee, $user);

        $canViewAll = $this->canViewAllEmployees($user);

        $primaryLocation = $canViewAll
            ? null
            : $this->managedLocation($user);

        $this->loadEmployeeRelations($employee, $user);

        $locations = $canViewAll
            ? Location::active()
                ->orderBy('name')
                ->get()
            : Location::active()
                ->whereKey($primaryLocation->id)
                ->get();

        $primaryLocationId = $employee->employeeLocations
            ->firstWhere('is_primary', true)
            ?->location_id;

        $isAdmin = $canViewAll;

        return view('admin.employees.edit', compact(
            'employee',
            'locations',
            'primaryLocationId',
            'isAdmin',
            'primaryLocation'
        ));
    }

    public function update(
        UpdateEmployeeRequest $request,
        Employee $employee
    ) {
        $user = $request->user();

        $this->requirePermission($user, ['employees.update']);
        $this->ensureEmployeeAccess($employee, $user);

        $primaryLocationId = $this->resolvePrimaryLocationId($request);

        DB::transaction(function () use (
            $request,
            $employee,
            $primaryLocationId
        ) {
            $validated = $request->validated();

            /*
            |--------------------------------------------------------------------------
            | حماية رقم الموظف
            |--------------------------------------------------------------------------
            |
            | حتى لو تم إرسال employee_number يدويًا من DevTools
            | لن يتم تعديل الرقم الحالي.
            |
            */
            unset(
                $validated['employee_number'],
                $validated['profile_image'],
                $validated['remove_profile_image'],
                $validated['primary_location_id']
            );

            if ($request->boolean('remove_profile_image')) {
                $this->deleteProfileImage($employee);
                $validated['profile_image'] = null;
            }

            if ($request->hasFile('profile_image')) {
                $this->deleteProfileImage($employee);

                $validated['profile_image'] = $request
                    ->file('profile_image')
                    ->store('employees/profile-images', 'public');
            }

            $employee->update($validated);

            $this->updatePrimaryLocation(
                $employee,
                $primaryLocationId
            );

            if (
                $employee->user &&
                array_key_exists('profile_image', $validated)
            ) {
                $employee->user->update([
                    'profile_image' => $validated['profile_image'],
                ]);
            }
        });

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', 'تم تحديث بيانات الموظف بنجاح.');
    }

    public function destroy(Request $request, Employee $employee)
    {
        $user = $request->user();

        $this->requirePermission($user, ['employees.delete']);
        $this->ensureEmployeeAccess($employee, $user);

        return back()->with(
            'error',
            'لا يمكن حذف سجل الموظف.'
        );
    }

    public function toggleStatus(Request $request, Employee $employee)
    {
        $user = $request->user();

        $this->requirePermission($user, ['employees.update']);
        $this->ensureEmployeeAccess($employee, $user);

        $newStatus = $employee->employment_status === EmploymentStatus::Active
            ? EmploymentStatus::Inactive
            : EmploymentStatus::Active;

        $employee->update([
            'employment_status' => $newStatus,
        ]);

        if ($employee->user) {
            $employee->user->update([
                'is_active' => $newStatus === EmploymentStatus::Active,
            ]);
        }

        return back()->with(
            'success',
            'تم تحديث حالة الموظف.'
        );
    }

    public function createUser(
        Request $request,
        Employee $employee
    ) {
        $user = $request->user();

        // إنشاء حساب نظام مستقل عن صلاحية تعديل الموظف.
        $this->requirePermission($user, ['users.manage']);
        $this->ensureEmployeeAccess($employee, $user);

        if ($employee->user) {
            return back()->with(
                'error',
                'هذا الموظف لديه حساب نظام بالفعل.'
            );
        }

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:4',
                'max:50',
                'unique:users,username',
            ],
            'role' => [
                'required',
                'exists:roles,name',
            ],
        ]);

        $tempPassword = Str::random(12) . '!1A';

        $newUser = User::create([
            'employee_id' => $employee->id,
            'username' => $validated['username'],
            'email' => $employee->email
                ?? $validated['username'] . '@dahabsweets.local',
            'profile_image' => $employee->profile_image,
            'password' => Hash::make($tempPassword),
            'is_active' => $employee->isActive(),
            'must_change_password' => true,
        ]);

        $newUser->assignRole($validated['role']);

        NotificationDispatcher::notifyByPermissions(
            new UserCreatedNotification($newUser->load('employee')),
            ['users.manage', 'employees.view_all'],
            $employee->primaryLocation()?->id,
            ['users.manage'],
            $user->id,
        );

        return back()->with(
            'success',
            "تم إنشاء الحساب. كلمة المرور المؤقتة: {$tempPassword}"
        );
    }

    /**
     * إنشاء رقم الموظف التالي حسب أعلى رقم موجود.
     *
     * الأمثلة الحالية في المشروع:
     * EMP-001, EMP-002, ... EMP-010
     *
     * النتيجة التالية ستكون:
     * EMP-011
     *
     * لا نعتمد على id لأن المطلوب تسلسل employee_number نفسه.
     * نستخدم withTrashed حتى لا نعيد استخدام رقم موظف حُذف سابقًا.
     */
    private function nextEmployeeNumber(bool $lockForUpdate = false): string
    {
        $query = Employee::withTrashed()
            ->where(
                'employee_number',
                'like',
                self::EMPLOYEE_NUMBER_PREFIX . '-%'
            );

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $numbers = $query
            ->pluck('employee_number');

        $maxNumericPart = 0;

        foreach ($numbers as $employeeNumber) {
            if (
                ! is_string($employeeNumber) ||
                ! preg_match(
                    '/^'
                    . preg_quote(self::EMPLOYEE_NUMBER_PREFIX, '/')
                    . '-(\d+)$/i',
                    trim($employeeNumber),
                    $matches
                )
            ) {
                continue;
            }

            $numericPart = (int) $matches[1];

            if ($numericPart > $maxNumericPart) {
                $maxNumericPart = $numericPart;
            }
        }

        $nextNumericPart = $maxNumericPart + 1;

        return self::EMPLOYEE_NUMBER_PREFIX
            . '-'
            . str_pad(
                (string) $nextNumericPart,
                self::EMPLOYEE_NUMBER_PADDING,
                '0',
                STR_PAD_LEFT
            );
    }

    private function canViewAllEmployees(User $user): bool
    {
        return $user->isAdmin()
            || $user->can('employees.view_all');
    }

    /**
     * موقع المستخدم الرئيسي الفعال.
     * يدعم الفرع والمصنع حتى لا نمنع مدير المصنع من موظفي المصنع.
     */
    private function managedLocation(User $user): Location
    {
        $primaryLocation = $user->primaryLocation();

        $isActive =
            $primaryLocation &&
            Location::active()
                ->whereKey($primaryLocation->id)
                ->exists();

        abort_unless(
            $isActive,
            403,
            'لا يوجد موقع رئيسي فعال مرتبط بحسابك.'
        );

        return $primaryLocation;
    }

    private function resolvePrimaryLocationId(Request $request): ?int
    {
        $user = $request->user();

        if (! $this->canViewAllEmployees($user)) {
            return (int) $this->managedLocation($user)->id;
        }

        if (! $request->filled('primary_location_id')) {
            return null;
        }

        $location = Location::active()
            ->find($request->integer('primary_location_id'));

        if (! $location) {
            throw ValidationException::withMessages([
                'primary_location_id' =>
                    'يجب اختيار موقع صحيح وفعال.',
            ]);
        }

        return (int) $location->id;
    }

    private function ensureEmployeeAccess(
        Employee $employee,
        User $user
    ): void {
        if ($this->canViewAllEmployees($user)) {
            return;
        }

        $location = $this->managedLocation($user);

        $canAccess = $employee->employeeLocations()
            ->where('location_id', $location->id)
            ->where('is_primary', true)
            ->exists();

        abort_unless(
            $canAccess,
            403,
            'لا يمكنك الوصول إلى موظف تابع لموقع آخر.'
        );
    }

    private function loadEmployeeRelations(
        Employee $employee,
        User $user
    ): void {
        $canViewAll = $this->canViewAllEmployees($user);

        $primaryLocation = $canViewAll
            ? null
            : $this->managedLocation($user);

        $employee->load([
            'user.roles',

            'employeeLocations' => function ($query) use (
                $canViewAll,
                $primaryLocation
            ) {
                if (! $canViewAll) {
                    $query->where(
                        'location_id',
                        $primaryLocation->id
                    );
                }

                $query->with('location');
            },
        ]);
    }

    private function deleteProfileImage(Employee $employee): void
    {
        if (
            $employee->profile_image &&
            Storage::disk('public')->exists($employee->profile_image)
        ) {
            Storage::disk('public')->delete($employee->profile_image);
        }
    }

    private function updatePrimaryLocation(
        Employee $employee,
        mixed $locationId
    ): void {
        $employee->employeeLocations()
            ->where('is_primary', true)
            ->update([
                'is_primary' => false,
                'ended_at' => now()->toDateString(),
            ]);

        if (empty($locationId)) {
            return;
        }

        $employeeLocation = EmployeeLocation::query()
            ->where('employee_id', $employee->id)
            ->where('location_id', $locationId)
            ->first();

        if ($employeeLocation) {
            $employeeLocation->update([
                'is_primary' => true,
                'started_at' => $employeeLocation->started_at
                    ?? now()->toDateString(),
                'ended_at' => null,
            ]);

            return;
        }

        EmployeeLocation::create([
            'employee_id' => $employee->id,
            'location_id' => $locationId,
            'is_primary' => true,
            'started_at' => now()->toDateString(),
            'ended_at' => null,
        ]);
    }

    /**
     * @param array<int, string> $permissions
     */
    private function requirePermission(
        User $user,
        array $permissions
    ): void {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        abort(
            403,
            'ليس لديك صلاحية لتنفيذ هذه العملية.'
        );
    }
}
