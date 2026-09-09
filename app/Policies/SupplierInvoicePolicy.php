<?php

namespace App\Policies;

use App\Models\SupplierInvoice;
use App\Enums\SupplierInvoiceStatus;
use App\Models\User;

class SupplierInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can('supplier_invoices.view');
    }

    public function view(User $user, SupplierInvoice $invoice): bool
    {
        return $this->viewAny($user)
            && ($user->isAdmin() || $invoice->location_id === $user->primaryLocation()?->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->can('supplier_invoices.create');
    }

    public function update(User $user, SupplierInvoice $invoice): bool
    {
        return ($user->isAdmin() || $user->can('supplier_invoices.update'))
            && ($user->isAdmin() || $invoice->location_id === $user->primaryLocation()?->id)
            && $invoice->paid_amount == 0;
    }

    public function cancel(User $user, SupplierInvoice $invoice): bool
    {
        return ($user->isAdmin() || $user->can('supplier_invoices.cancel'))
            && ($user->isAdmin() || $invoice->location_id === $user->primaryLocation()?->id)
            && $invoice->statusValue() !== SupplierInvoiceStatus::Cancelled->value;
    }
}
