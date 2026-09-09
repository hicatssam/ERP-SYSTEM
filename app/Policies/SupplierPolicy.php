<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can('suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->can('suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin() || $user->can('suppliers.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin() || $user->can('suppliers.delete');
    }
}
