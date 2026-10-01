<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveCarryover;
use App\Models\EmployeeShiftAssignment;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\WorkHoliday;
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
            $this->assertPayrollEditable($employee, $start, $end);
            $fraction = (float) ($data['day_fraction'] ?? 1);
            if (! in_array($fraction, [0.5, 1.0], true) || ($fraction === 0.5 && ! $start->equalTo($end))) {
                throw ValidationException::withMessages([
                    'day_fraction' => 'نصف اليوم متاح لإجازة بتاريخ واحد فقط.',
                ]);
            }
            if ($fraction === 0.5 && ! in_array($data['half_day_slot'] ?? null, ['first_half', 'second_half'], true)) {
                throw ValidationException::withMessages([
                    'half_day_slot' => 'اختر النصف الأول أو الثاني من الوردية.',
                ]);
            }
            if ($fraction === 0.5) {
                $assignment = EmployeeShiftAssignment::query()->with('shift')
                    ->where('employee_id', $employee->id)->where('is_primary', true)
                    ->whereDate('effective_from', '<=', $start->toDateString())
                    ->where(fn ($query) => $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $start->toDateString()))
                    ->latest('effective_from')->first();
                $workDays = $assignment?->shift?->work_days ?: [1, 2, 3, 4, 5, 6];
                if (! $assignment?->shift || ! in_array($start->isoWeekday(), array_map('intval', $workDays), true)) {
                    throw ValidationException::withMessages([
                        'start_date' => 'نصف اليوم يتطلب وردية عمل مجدولة في التاريخ المختار.',
                    ]);
                }
            }

            $countableDates = [];
            $yearlyDays = $this->yearlyDays($employee, $type, $start, $end, $countableDates);
            if ($fraction === 0.5) {
                $yearlyDays = array_map(fn ($days) => $days * 0.5, $yearlyDays);
            }
            $this->assertAvailable($employee, $type, $start, $end, reservedDays: $yearlyDays);

            return EmployeeLeaveRequest::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'total_days' => array_sum($yearlyDays),
                'yearly_days' => $yearlyDays,
                'countable_dates' => $type->count_basis === 'scheduled' ? $countableDates : null,
                'day_fraction' => $fraction,
                'half_day_slot' => $fraction === 0.5 ? $data['half_day_slot'] : null,
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
        bool $approval = false,
        ?array $reservedDays = null
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

        $reservedDays ??= $this->yearlyDays($employee, $type, $start, $end);
        for ($year = $start->year; $year <= $end->year; $year++) {
            $requested = (float) ($reservedDays[$year] ?? 0);
            $used = $this->usedDays($employee->id, $type->id, $year, $statuses, $excludingId);

            $asOf = $year < $end->year
                ? Carbon::create($year, 12, 31)->endOfDay()
                : $end;
            if ($used + $requested > $this->entitlement($employee, $type, $year, $asOf) + 0.0001) {
                throw ValidationException::withMessages([
                    'end_date' => "الإجازة تتجاوز الرصيد المحدد لنوع الإجازة في سنة {$year}.",
                ]);
            }
        }
    }

    /** @return array<int, array{approved: int|float, pending: int|float, available: ?float}> */
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
                    : max(0.0, $this->entitlement(
                        $employee, $type, $year,
                        $year === now()->year ? now() : Carbon::create($year, 12, 31)->endOfDay()
                    ) - $approved - $pending),
            ];
        }

        return $balances;
    }

    public function grantCarryover(
        Employee $employee,
        LeaveType $type,
        int $year,
        float $days,
        int $actorId,
        ?string $note = null
    ): EmployeeLeaveCarryover {
        return DB::transaction(function () use ($employee, $type, $year, $days, $actorId, $note) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $limit = (float) $type->carryover_limit_days;
            if ($type->annual_days === null || $limit <= 0 || $year <= 2000 || $year > now()->year) {
                throw ValidationException::withMessages([
                    'days' => 'هذا النوع لا يسمح بترحيل الرصيد.',
                ]);
            }
            $prior = $year - 1;
            $priorEarned = $this->baseEntitlement($employee, $type, $prior, Carbon::create($prior, 12, 31));
            $priorUsed = $this->usedDays($employee->id, $type->id, $prior, ['approved', 'pending']);
            $maximum = min($limit, max(0, $priorEarned - $priorUsed));
            if ($days < 0 || $days > $maximum + 0.0001) {
                throw ValidationException::withMessages([
                    'days' => 'عدد الأيام يتجاوز السقف أو الرصيد السنوي السابق المتبقي ('.$maximum.').',
                ]);
            }
            $currentUsed = $this->usedDays($employee->id, $type->id, $year, ['approved', 'pending']);
            $asOf = $year === now()->year ? now() : Carbon::create($year, 12, 31);
            if ($currentUsed > $this->baseEntitlement($employee, $type, $year, $asOf) + $days + 0.0001) {
                throw ValidationException::withMessages([
                    'days' => 'لا يمكن تخفيض الرصيد المرحّل دون تغطية الإجازات الحالية.',
                ]);
            }

            return EmployeeLeaveCarryover::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
                ['days' => $days, 'granted_by' => $actorId, 'note' => $note]
            );
        });
    }

    public function assertPayrollEditable(Employee $employee, Carbon $start, Carbon $end): void
    {
        $locationId = $employee->employeeLocations()
            ->where('is_primary', true)->whereNull('ended_at')->value('location_id');
        $locked = PayrollPeriod::query()->whereIn('status', ['approved', 'paid', 'closed'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->where(function ($query) use ($locationId): void {
                $query->whereNull('location_id');
                if ($locationId) {
                    $query->orWhere('location_id', $locationId);
                }
            })->exists();
        if ($locked) {
            throw ValidationException::withMessages([
                'start_date' => 'تتداخل الإجازة مع دورة رواتب معتمدة؛ لا يمكن تعديل أثر الحضور بعدها.',
            ]);
        }
    }

    private function entitlement(Employee $employee, LeaveType $type, int $year, Carbon $asOf): float
    {
        $carryover = (float) (EmployeeLeaveCarryover::query()
            ->where('employee_id', $employee->id)->where('leave_type_id', $type->id)
            ->where('year', $year)->value('days') ?? 0);

        return $this->baseEntitlement($employee, $type, $year, $asOf) + $carryover;
    }

    private function baseEntitlement(Employee $employee, LeaveType $type, int $year, Carbon $asOf): float
    {
        $annual = (float) $type->annual_days;
        if ($employee->hire_date && $employee->hire_date->year > $year) {
            return 0.0;
        }
        if ($type->accrual_mode !== 'monthly') {
            return $annual;
        }

        $earnedMonths = 0;
        for ($month = 1; $month <= 12; $month++) {
            $monthStart = Carbon::create($year, $month, 1)->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth();
            if ($monthEnd->lte($asOf) && (! $employee->hire_date || $employee->hire_date->lte($monthStart))) {
                $earnedMonths++;
            }
        }

        return round($annual * $earnedMonths / 12, 2);
    }

    private function usedDays(
        int $employeeId,
        int $typeId,
        int $year,
        array $statuses,
        ?int $excludingId = null
    ): int|float {
        $yearStart = "{$year}-01-01";
        $yearEnd = "{$year}-12-31";

        $total = EmployeeLeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $typeId)
            ->whereIn('status', $statuses)
            ->whereDate('start_date', '<=', $yearEnd)
            ->whereDate('end_date', '>=', $yearStart)
            ->when($excludingId, fn ($query) => $query->whereKeyNot($excludingId))
            ->get(['start_date', 'end_date', 'yearly_days'])
            ->sum(fn (EmployeeLeaveRequest $leave) => $leave->yearly_days !== null
                ? (float) ($leave->yearly_days[$year] ?? 0)
                : $this->daysInYear($leave->start_date, $leave->end_date, $year));

        return fmod((float) $total, 1.0) === 0.0 ? (int) $total : (float) $total;
    }

    /** @return array<int, int> */
    private function yearlyDays(
        Employee $employee,
        LeaveType $type,
        Carbon $start,
        Carbon $end,
        ?array &$countableDates = null
    ): array
    {
        if ($end->lt($start) || $start->diffInDays($end) > 366) {
            throw ValidationException::withMessages([
                'end_date' => 'يجب أن تكون نهاية الإجازة بعد بدايتها وألا تتجاوز سنة واحدة.',
            ]);
        }

        if ($type->count_basis !== 'scheduled') {
            $days = [];
            for ($year = $start->year; $year <= $end->year; $year++) {
                $days[$year] = $this->daysInYear($start, $end, $year);
            }
            return $days;
        }

        $assignments = EmployeeShiftAssignment::query()->with('shift')
            ->where('employee_id', $employee->id)->where('is_primary', true)
            ->whereDate('effective_from', '<=', $end->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $start->toDateString()))
            ->orderByDesc('effective_from')->get();
        $locations = $employee->employeeLocations()
            ->where(fn ($query) => $query->whereNull('started_at')
                ->orWhereDate('started_at', '<=', $end->toDateString()))
            ->where(fn ($query) => $query->whereNull('ended_at')
                ->orWhereDate('ended_at', '>=', $start->toDateString()))
            ->orderByDesc('started_at')->get();
        $holidays = WorkHoliday::query()
            ->whereDate('holiday_date', '>=', $start->toDateString())
            ->whereDate('holiday_date', '<=', $end->toDateString())->get();
        $days = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $day = $date->toDateString();
            $assignment = $assignments->first(fn ($item) =>
                $item->effective_from->toDateString() <= $day
                && (! $item->effective_to || $item->effective_to->toDateString() >= $day));
            if (! $assignment?->shift) {
                throw ValidationException::withMessages([
                    'start_date' => 'لا يوجد جدول دوام للموظف يغطي كامل الإجازة؛ اختر أيامًا تقويمية أو عيّن وردية أولًا.',
                ]);
            }
            $location = $locations->first(fn ($item) =>
                (! $item->started_at || $item->started_at->toDateString() <= $day)
                && (! $item->ended_at || $item->ended_at->toDateString() >= $day));
            $locationId = $location?->location_id;
            $isHoliday = $holidays->contains(fn (WorkHoliday $holiday) =>
                $holiday->holiday_date->toDateString() === $day
                && ($holiday->location_id === null || (int) $holiday->location_id === (int) $locationId));
            $workDays = $assignment->shift->work_days ?: [1, 2, 3, 4, 5, 6];
            if (! $isHoliday && in_array($date->isoWeekday(), array_map('intval', $workDays), true)) {
                $days[$date->year] = ($days[$date->year] ?? 0) + 1;
                $countableDates[] = $day;
            }
        }

        if (array_sum($days) === 0) {
            throw ValidationException::withMessages([
                'start_date' => 'المدة المختارة لا تحتوي على يوم دوام قابل للاحتساب.',
            ]);
        }

        return $days;
    }

    private function daysInYear(Carbon $start, Carbon $end, int $year): int
    {
        $from = $start->copy()->startOfDay()->max(Carbon::create($year, 1, 1)->startOfDay());
        $to = $end->copy()->startOfDay()->min(Carbon::create($year, 12, 31)->startOfDay());

        return (int) $from->diffInDays($to) + 1;
    }
}
