<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeOrgAssignment;
use App\Models\HrCostCenter;
use App\Models\HrDepartment;
use App\Models\HrPosition;
use App\Models\PayrollItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrOrganizationService
{
    public function assign(Employee $employee, array $data, User $actor): EmployeeOrgAssignment
    {
        return DB::transaction(function () use ($employee, $data, $actor): EmployeeOrgAssignment {
            $employee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $from = Carbon::parse($data['effective_from'])->startOfDay();
            if (! $employee->isActive()) {
                throw ValidationException::withMessages(['employee_id' => 'يمكن تكليف موظف نشط فقط.']);
            }
            if ($employee->hire_date && $from->lt($employee->hire_date)) {
                throw ValidationException::withMessages([
                    'effective_from' => 'تاريخ التكليف يسبق تاريخ تعيين الموظف.',
                ]);
            }

            $primary = $employee->employeeLocations()
                ->where('is_primary', true)->whereNull('ended_at')
                ->latest('started_at')->first();
            if ($primary?->started_at && $from->lt($primary->started_at)) {
                throw ValidationException::withMessages([
                    'effective_from' => 'تاريخ التكليف يسبق انتقال الموظف إلى فرعه الحالي.',
                ]);
            }
            $locationId = $primary?->location_id;

            $department = HrDepartment::query()->findOrFail($data['department_id']);
            $position = ! empty($data['position_id'])
                ? HrPosition::query()->findOrFail($data['position_id']) : null;
            $center = ! empty($data['cost_center_id'])
                ? HrCostCenter::query()->findOrFail($data['cost_center_id']) : null;
            $manager = ! empty($data['manager_employee_id'])
                ? Employee::query()->findOrFail($data['manager_employee_id']) : null;

            if (! $department->is_active || ($department->location_id !== null
                && (int) $department->location_id !== (int) $locationId)) {
                throw ValidationException::withMessages(['department_id' => 'القسم غير متاح في فرع الموظف.']);
            }
            if ($position && (! $position->is_active
                || (int) $position->department_id !== (int) $department->id)) {
                throw ValidationException::withMessages(['position_id' => 'المسمى الوظيفي لا يتبع القسم المختار.']);
            }
            if ($center && (! $center->is_active || ($center->location_id !== null
                && (int) $center->location_id !== (int) $locationId))) {
                throw ValidationException::withMessages(['cost_center_id' => 'مركز التكلفة غير متاح في فرع الموظف.']);
            }
            if ($manager && ($manager->id === $employee->id
                || ! $manager->isActive()
                || ! $manager->employeeLocations()->where('is_primary', true)
                    ->whereNull('ended_at')->where('location_id', $locationId)->exists())) {
                throw ValidationException::withMessages(['manager_employee_id' => 'اختر مديرًا آخر نشطًا في فرع الموظف.']);
            }
            $seen = [$employee->id => true];
            $next = $manager;
            while ($next) {
                if (isset($seen[$next->id])) {
                    throw ValidationException::withMessages([
                        'manager_employee_id' => 'تسلسل المدراء يحتوي على دائرة إشراف.',
                    ]);
                }
                $seen[$next->id] = true;
                $managerAssignment = $next->orgAssignments()
                    ->whereDate('effective_from', '<=', $from->toDateString())
                    ->where(fn ($query) => $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $from->toDateString()))
                    ->first();
                $next = $managerAssignment?->manager;
            }

            $lockedPayroll = PayrollItem::query()->where('employee_id', $employee->id)
                ->whereHas('period', fn ($query) => $query
                    ->whereIn('status', ['approved', 'paid', 'closed'])
                    ->whereDate('end_date', '>=', $from->toDateString()))
                ->exists();
            if ($lockedPayroll) {
                throw ValidationException::withMessages([
                    'effective_from' => 'تاريخ التكليف يتداخل مع دورة رواتب معتمدة لهذا الموظف. اختر تاريخًا بعد آخر دورة معتمدة.',
                ]);
            }

            $last = $employee->orgAssignments()->lockForUpdate()->first();
            if ($last && $last->effective_from->gte($from)) {
                throw ValidationException::withMessages([
                    'effective_from' => 'تاريخ التكليف الجديد يجب أن يأتي بعد آخر تكليف مسجل.',
                ]);
            }
            if ($last && (! $last->effective_to || $last->effective_to->gte($from))) {
                $last->update(['effective_to' => $from->copy()->subDay()->toDateString()]);
            }

            return $employee->orgAssignments()->create([
                'department_id' => $department->id,
                'position_id' => $position?->id,
                'cost_center_id' => $center?->id,
                'manager_employee_id' => $manager?->id,
                'location_id' => $locationId,
                'effective_from' => $from->toDateString(),
                'reason' => $data['reason'] ?? null,
                'assigned_by' => $actor->id,
            ]);
        });
    }
}
