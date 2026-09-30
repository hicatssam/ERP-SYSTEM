<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeLeaveService
{
    public function create(Employee $employee, LeaveType $type, array $data, int $createdBy): EmployeeLeaveRequest
    {
        return DB::transaction(function () use ($employee, $type, $data, $createdBy): EmployeeLeaveRequest {
            // Serialize requests for one employee, including when no prior leave exists.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            if (! $type->is_active) {
                throw ValidationException::withMessages([
                    'leave_type_id' => 'نوع الإجازة غير متاح حاليًا.',
                ]);
            }

            $start = Carbon::parse($data['start_date'])->startOfDay();
            $end = Carbon::parse($data['end_date'])->startOfDay();

            $this->assertAvailable($employee, $type, $start, $end);

            return EmployeeLeaveRequest::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'total_days' => $start->diffInDays($end) + 1,
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
                'created_by' => $createdBy,
            ]);
        });
    }

    public function assertAvailable(
        Employee $employee,
        LeaveType $type,
        Carbon $start,
        Carbon $end,
        ?int $excludingId = null,
        bool $approval = false
    ): void {
        $statuses = $approval ? ['approved'] : ['pending', 'approved'];

        $overlap = EmployeeLeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', $statuses)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->when($excludingId, fn ($query) => $query->whereKeyNot($excludingId))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => 'للموظف طلب إجازة آخر يتداخل مع هذه الفترة.',
            ]);
        }

        if ($type->annual_days === null) {
            return;
        }

        for ($year = $start->year; $year <= $end->year; $year++) {
            $requested = $this->daysInYear($start, $end, $year);
            $used = $this->usedDays($employee->id, $type->id, $year, $statuses, $excludingId);

            if ($used + $requested > (float) $type->annual_days + 0.0001) {
                throw ValidationException::withMessages([
                    'end_date' => "الإجازة تتجاوز الرصيد المحدد لنوع الإجازة في سنة {$year}.",
                ]);
            }
        }
    }

    /** @return array<int, array{approved: int, pending: int, available: ?float}> */
    public function balances(Employee $employee, iterable $types, int $year): array
    {
        $balances = [];

        foreach ($types as $type) {
            $approved = $this->usedDays($employee->id, $type->id, $year, ['approved']);
            $pending = $this->usedDays($employee->id, $type->id, $year, ['pending']);

            $balances[$type->id] = [
                'approved' => $approved,
                'pending' => $pending,
                'available' => $type->annual_days === null
                    ? null
                    : max(0.0, (float) $type->annual_days - $approved - $pending),
            ];
        }

        return $balances;
    }

    private function usedDays(
        int $employeeId,
        int $typeId,
        int $year,
        array $statuses,
        ?int $excludingId = null
    ): int {
        $yearStart = "{$year}-01-01";
        $yearEnd = "{$year}-12-31";

        return EmployeeLeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $typeId)
            ->whereIn('status', $statuses)
            ->whereDate('start_date', '<=', $yearEnd)
            ->whereDate('end_date', '>=', $yearStart)
            ->when($excludingId, fn ($query) => $query->whereKeyNot($excludingId))
            ->get(['start_date', 'end_date'])
            ->sum(fn (EmployeeLeaveRequest $leave) => $this->daysInYear(
                $leave->start_date,
                $leave->end_date,
                $year
            ));
    }

    private function daysInYear(Carbon $start, Carbon $end, int $year): int
    {
        $from = $start->copy()->startOfDay()->max(Carbon::create($year, 1, 1)->startOfDay());
        $to = $end->copy()->startOfDay()->min(Carbon::create($year, 12, 31)->startOfDay());

        return (int) $from->diffInDays($to) + 1;
    }
}
