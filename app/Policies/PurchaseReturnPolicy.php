<?php

namespace App\Policies;

use App\Enums\PurchaseReturnStatus;
use App\Models\PurchaseReturn;
use App\Models\User;

class PurchaseReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can('purchase_returns.view');
    }

    public function view(User $user, PurchaseReturn $return): bool
    {
        return $this->viewAny($user)
            && ($user->isAdmin() || $return->location_id === $user->primaryLocation()?->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->can('purchase_returns.create');
    }

    public function post(User $user, PurchaseReturn $return): bool
    {
        return ($user->isAdmin() || $user->can('purchase_returns.approve'))
            && ($user->isAdmin() || $return->location_id === $user->primaryLocation()?->id)
            && $return->statusValue() === PurchaseReturnStatus::Draft->value;
    }

    public function cancel(User $user, PurchaseReturn $return): bool
    {
        return ($user->isAdmin() || $user->can('purchase_returns.cancel'))
            && ($user->isAdmin() || $return->location_id === $user->primaryLocation()?->id)
            && $return->statusValue() === PurchaseReturnStatus::Draft->value;
    }
}
