<?php

namespace App\Policies;

use App\Models\CashSession;
use App\Models\User;

class CashSessionPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('cash_sessions.manage') || $user->isAdmin();
    }

    public function open(User $user): bool
    {
        return $user->hasPermissionTo('cash_sessions.manage') || $user->isAdmin();
    }

    public function close(User $user, CashSession $session): bool
    {
        // Only the employee who opened it, their manager, or admin can close
        return $user->isAdmin()
            || $user->hasPermissionTo('cash_sessions.manage')
            || $user->employee?->id === $session->employee_id;
    }

    public function view(User $user, CashSession $session): bool
    {
        return $user->isAdmin()
            || $user->hasPermissionTo('cash_sessions.manage')
            || $user->employee?->id === $session->employee_id
            || $session->location_id === $user->primaryLocation()?->id;
    }
}
