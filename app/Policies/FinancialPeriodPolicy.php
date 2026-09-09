<?php

namespace App\Policies;

use App\Models\FinancialPeriod;
use App\Models\User;

class FinancialPeriodPolicy
{
    public function view(User $user, FinancialPeriod $period): bool
    {
        return $user->hasPermissionTo('financial.periods.view') || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('financial.periods.open') || $user->isAdmin();
    }

    public function open(User $user, FinancialPeriod $period): bool
    {
        return ($user->hasPermissionTo('financial.periods.open') || $user->isAdmin())
            && ($period->status?->value ?? $period->status) === 'closed';
    }

    public function close(User $user, FinancialPeriod $period): bool
    {
        return ($user->hasPermissionTo('financial.periods.close') || $user->isAdmin())
            && ($period->status?->value ?? $period->status) === 'open';
    }

    public function delete(User $user, FinancialPeriod $period): bool
    {
        return $user->isAdmin();
    }
}
