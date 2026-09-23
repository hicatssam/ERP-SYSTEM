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

    public function view(User $user, Payment $payment): bool
    {
        return (
            $user->isAdmin()
            || $user->can('payments.record')
            || $user->can('payments.verify')
            || $user->can('financial.branch.view')
            || $user->can('financial.global.view')
            || $user->can('financial.collections.view')
        ) && $this->belongsToUserLocation($user, $payment);
    }

    public function verify(User $user, Payment $payment): bool
    {
        return ($user->hasPermissionTo('payments.verify') || $user->isAdmin())
            && $this->belongsToUserLocation($user, $payment)
            && ($payment->status?->value ?? $payment->status) === 'pending_verification';
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
