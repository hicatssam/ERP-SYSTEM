<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isAdmin() || $user->can('financial.global.view')) {
            return true;
        }

        return $user->hasPermissionTo('invoices.view')
            && $this->belongsToUserLocation($user, $invoice);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        if (($invoice->status?->value ?? $invoice->status) !== 'active') {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPermissionTo('invoices.cancel')
            && $this->belongsToUserLocation($user, $invoice);
    }

    public function download(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    private function belongsToUserLocation(User $user, Invoice $invoice): bool
    {
        return (int) ($user->primaryLocation()?->id ?? 0)
            === (int) $invoice->location_id;
    }
}
