<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PayrollAccess
{
    public function global(User $user): bool
    {
        return $user->isAdmin() || $user->can('employees.view_all')
            || $user->can('financial.global.view');
    }

    public function assertGlobalWrite(User $user): void
    {
        abort_unless($this->global($user), 403, 'تعديل دورة الرواتب العامة يتطلب صلاحية جميع الفروع.');
    }

    public function employees(User $user): Builder
    {
        return $this->global($user)
            ? Employee::query()
            : Employee::query()->accessibleBy($user);
    }

    public function items(User $user): Builder
    {
        return PayrollItem::query()
            ->whereIn('employee_id', $this->employees($user)->select('employees.id'));
    }

    public function periods(User $user): Builder
    {
        return PayrollPeriod::query()->when(! $this->global($user), fn (Builder $q) =>
            $q->whereHas('items', fn (Builder $items) =>
                $items->whereIn('employee_id', $this->employees($user)->select('employees.id'))));
    }

    public function assertEmployee(User $user, int $employeeId): void
    {
        abort_unless($this->employees($user)->whereKey($employeeId)->exists(), 403);
    }

    public function assertItem(User $user, PayrollItem $item): void
    {
        $this->assertEmployee($user, (int) $item->employee_id);
    }

    public function assertPeriod(User $user, PayrollPeriod $period): void
    {
        abort_unless($this->periods($user)->whereKey($period->id)->exists(), 403);
    }
}
