<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeOrgAssignment;
use App\Models\HrCostCenter;
use App\Models\HrDepartment;
use App\Models\HrPosition;
use App\Models\Location;
use App\Models\User;
use App\Services\HrOrganizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrOrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $locations = $this->locations($user);
        $departments = $this->departments($user)->with(['parent', 'location'])
            ->orderBy('name')->get();
        $positions = HrPosition::query()->whereIn('department_id', $departments->pluck('id'))
            ->with('department')->orderBy('name')->get();
        $centers = $this->centers($user)->with('location')->orderBy('name')->get();
        $search = trim((string) $request->query('q', ''));
        $employees = Employee::query()->accessibleBy($user)
            ->when($search !== '', fn (Builder $query) => $query
                ->where('full_name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%'))
            ->with([
            'currentOrgAssignment.department', 'currentOrgAssignment.position',
            'currentOrgAssignment.costCenter', 'currentOrgAssignment.manager',
        ])->orderBy('full_name')->paginate(30)->withQueryString();
        $ids = Employee::query()->accessibleBy($user)->select('employees.id');
        $today = now()->toDateString();
        $active = EmployeeOrgAssignment::query()->whereIn('employee_id', $ids)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $today));

        return view('admin.hr.organization', [
            'search' => $search,
            'locations' => $locations,
            'departments' => $departments,
            'positions' => $positions,
            'centers' => $centers,
            'employees' => $employees,
            'staffOptions' => Employee::query()->accessibleBy($user)
                ->where('employment_status', 'active')
                ->with(['employeeLocations' => fn ($query) => $query
                    ->where('is_primary', true)
                    ->where(fn ($dates) => $dates->whereNull('started_at')
                        ->orWhereDate('started_at', '<=', $today))
                    ->where(fn ($dates) => $dates->whereNull('ended_at')
                        ->orWhereDate('ended_at', '>=', $today))])
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number']),
            'departmentCounts' => (clone $active)->selectRaw('department_id, COUNT(*) as total')
                ->groupBy('department_id')->pluck('total', 'department_id'),
            'centerCounts' => (clone $active)->selectRaw('cost_center_id, COUNT(*) as total')
                ->groupBy('cost_center_id')->pluck('total', 'cost_center_id'),
            'unassignedCount' => Employee::query()->accessibleBy($user)->count()
                - (clone $active)->count(),
        ]);
    }

    public function department(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:190'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists('hr_departments', 'id')],
        ]);
        $code = Str::upper(trim($data['code']));
        validator(['code' => $code], ['code' => Rule::unique('hr_departments', 'code')])->validate();
        $locationId = $this->chosenLocation($request->user(), $data['location_id'] ?? null);
        if (! empty($data['parent_id'])) {
            $parent = $this->departments($request->user())->findOrFail($data['parent_id']);
            if ($parent->location_id !== null
                && (int) $parent->location_id !== (int) $locationId) {
                throw ValidationException::withMessages(['parent_id' => 'القسم الأعلى يتبع فرعًا آخر.']);
            }
        }

        HrDepartment::query()->create([
            'code' => $code, 'name' => trim($data['name']),
            'location_id' => $locationId, 'parent_id' => $data['parent_id'] ?? null,
        ]);
        return back()->with('success', 'تمت إضافة القسم.');
    }

    public function position(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:190'],
            'department_id' => ['required', 'integer', Rule::exists('hr_departments', 'id')],
            'grade' => ['nullable', 'string', 'max:40'],
        ]);
        $department = $this->departments($request->user())->findOrFail($data['department_id']);
        if (! $department->is_active) {
            throw ValidationException::withMessages(['department_id' => 'القسم غير نشط.']);
        }
        $code = Str::upper(trim($data['code']));
        validator(['code' => $code], ['code' => Rule::unique('hr_positions', 'code')])->validate();
        HrPosition::query()->create([
            'code' => $code, 'name' => trim($data['name']),
            'department_id' => $department->id, 'grade' => $data['grade'] ?? null,
        ]);
        return back()->with('success', 'تمت إضافة المسمى الوظيفي.');
    }

    public function center(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:190'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
        ]);
        $code = Str::upper(trim($data['code']));
        validator(['code' => $code], ['code' => Rule::unique('hr_cost_centers', 'code')])->validate();
        HrCostCenter::query()->create([
            'code' => $code, 'name' => trim($data['name']),
            'location_id' => $this->chosenLocation($request->user(), $data['location_id'] ?? null),
        ]);
        return back()->with('success', 'تمت إضافة مركز التكلفة.');
    }

    public function assign(Request $request, HrOrganizationService $service): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'department_id' => ['required', 'integer', Rule::exists('hr_departments', 'id')],
            'position_id' => ['nullable', 'integer', Rule::exists('hr_positions', 'id')],
            'cost_center_id' => ['nullable', 'integer', Rule::exists('hr_cost_centers', 'id')],
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $employee = Employee::query()->accessibleBy($request->user())->findOrFail($data['employee_id']);
        $service->assign($employee, $data, $request->user());

        return back()->with('success', 'تم حفظ التكليف الوظيفي وتاريخه.');
    }

    public function csv(Request $request): StreamedResponse
    {
        $date = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ])['date'] ?? now()->toDateString();
        $ids = Employee::query()->accessibleBy($request->user())->select('employees.id');

        return response()->streamDownload(function () use ($ids, $date): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['رقم الموظف', 'الموظف', 'الفرع', 'القسم', 'الوظيفة', 'الدرجة', 'مركز التكلفة', 'المدير المباشر', 'من', 'إلى']);
            Employee::query()->whereIn('id', $ids)
                ->with(['employeeLocations.location', 'orgAssignments' => fn ($query) => $query
                    ->whereDate('effective_from', '<=', $date)
                    ->where(fn ($scope) => $scope->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $date))
                    ->with(['department', 'position', 'costCenter', 'manager'])])
                ->orderBy('id')->chunkById(500, function ($employees) use ($output): void {
                    foreach ($employees as $employee) {
                        $assignment = $employee->orgAssignments->first();
                        $cell = static function (?string $value): string {
                            $value ??= '';
                            return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
                        };
                        fputcsv($output, [
                            $cell($employee->employee_number), $cell($employee->full_name),
                            $cell($employee->employeeLocations->first(fn ($location) =>
                                $location->is_primary && ! $location->ended_at)?->location?->name),
                            $cell($assignment?->department?->name),
                            $cell($assignment?->position?->name),
                            $cell($assignment?->position?->grade),
                            $cell($assignment?->costCenter?->name),
                            $cell($assignment?->manager?->full_name),
                            $assignment?->effective_from?->toDateString(),
                            $assignment?->effective_to?->toDateString(),
                        ]);
                    }
                });
            fclose($output);
        }, 'hr-organization-'.$date.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function departments(User $user): Builder
    {
        $locationId = $user->primaryLocation()?->id;
        return HrDepartment::query()->when(! $this->global($user),
            fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                ->whereNull('location_id')->orWhere('location_id', $locationId)));
    }

    private function centers(User $user): Builder
    {
        $locationId = $user->primaryLocation()?->id;
        return HrCostCenter::query()->when(! $this->global($user),
            fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                ->whereNull('location_id')->orWhere('location_id', $locationId)));
    }

    private function locations(User $user)
    {
        return Location::query()->when(! $this->global($user),
            fn (Builder $query) => $query->whereKey($user->primaryLocation()?->id ?? 0))
            ->orderBy('name')->get();
    }

    private function chosenLocation(User $user, ?int $requested): ?int
    {
        if ($this->global($user)) {
            if ($requested) {
                Location::query()->findOrFail($requested);
            }
            return $requested;
        }
        $locationId = $user->primaryLocation()?->id;
        abort_unless($locationId && (! $requested || $requested === $locationId), 403);
        return $locationId;
    }

    private function global(User $user): bool
    {
        return $user->isAdmin() || $user->can('employees.view_all');
    }
}
