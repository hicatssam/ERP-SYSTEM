<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin()
            || $user->hasPermissionTo('invoices.view')
            || $invoice->location_id === $user->primaryLocation()?->id;
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return ($user->hasPermissionTo('invoices.cancel') || $user->isAdmin())
            && ($invoice->status?->value ?? $invoice->status) === 'active';
    }

    public function download(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
