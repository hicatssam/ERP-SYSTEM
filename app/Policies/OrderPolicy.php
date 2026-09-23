<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasPermissionTo('orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPermissionTo('orders.view')
            && $this->belongsToUserLocation($user, $order);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasPermissionTo('orders.create');
    }

    public function update(User $user, Order $order): bool
    {
        if (! in_array(
            $this->statusValue($order),
            ['draft', 'confirmed'],
            true
        )) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPermissionTo('orders.update')
            && $this->belongsToUserLocation($user, $order);
    }

    public function confirm(User $user, Order $order): bool
    {
        if ($this->statusValue($order) !== 'draft') {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPermissionTo('orders.confirm')
            && $this->belongsToUserLocation($user, $order);
    }

    public function cancel(User $user, Order $order): bool
    {
        if (! in_array(
            $this->statusValue($order),
            ['draft', 'confirmed'],
            true
        )) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPermissionTo('orders.cancel')
            && $this->belongsToUserLocation($user, $order);
    }

    public function complete(User $user, Order $order): bool
    {
        if ($this->statusValue($order) !== 'confirmed') {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPermissionTo('orders.complete')
            && $this->belongsToUserLocation($user, $order);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    private function statusValue(Order $order): string
    {
        return $order->status instanceof \BackedEnum
            ? $order->status->value
            : (string) $order->status;
    }

    private function belongsToUserLocation(
        User $user,
        Order $order
    ): bool {
        $locationId = $user->primaryLocation()?->id;

        return $locationId !== null
            && (int) $order->location_id === (int) $locationId;
    }
}
