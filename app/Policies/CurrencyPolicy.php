<?php

namespace App\Policies;

use App\Models\Currency;
use App\Models\User;

class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin()
            || $user->can('procurement.exchange_rates.view')
            || $user->can('procurement.exchange_rates.manage');
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin() || $user->can('procurement.exchange_rates.manage');
    }
}
