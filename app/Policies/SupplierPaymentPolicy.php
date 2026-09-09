<?php

namespace App\Policies;

use App\Models\SupplierPayment;
use App\Models\User;

class SupplierPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can('supplier_payments.view');
    }

    public function view(User $user, SupplierPayment $payment): bool
    {
        return $this->viewAny($user)
            && ($user->isAdmin() || $payment->location_id === $user->primaryLocation()?->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->can('supplier_payments.create');
    }
}
