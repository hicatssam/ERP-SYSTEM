<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function record(User $user): bool
    {
        return $user->hasPermissionTo('payments.record');
    }

    public function verify(User $user, Payment $payment): bool
    {
        if (($payment->status?->value ?? $payment->status) !== 'pending_verification') {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // Separation of duties: the employee who recorded/received a pending
        // transfer may not approve their own proof. A manager/accountant/other
        // authorized verifier in the same branch must perform the review.
        if ((int) ($payment->received_by ?? 0) === (int) $user->id) {
            return false;
        }

        return $user->hasPermissionTo('payments.verify')
            && $this->belongsToUserLocation($user, $payment);
    }

    public function correct(User $user, Payment $payment): bool
    {
        return ($user->hasPermissionTo('payments.correct') || $user->isAdmin())
            && $this->belongsToUserLocation($user, $payment)
            && in_array(($payment->status?->value ?? $payment->status), ['confirmed', 'corrected'], true);
    }

    public function refund(User $user, Payment $payment): bool
    {
        return ($user->hasPermissionTo('payments.refund') || $user->isAdmin())
            && $this->belongsToUserLocation($user, $payment)
            && in_array(($payment->status?->value ?? $payment->status), ['confirmed', 'corrected'], true);
    }

    private function belongsToUserLocation(User $user, Payment $payment): bool
    {
        return $user->isAdmin()
            || $user->can('financial.global.view')
            || (int) ($user->primaryLocation()?->id ?? 0) === (int) $payment->location_id;
    }
}
