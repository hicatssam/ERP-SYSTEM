<?php

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can('purchase_orders.view');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->viewAny($user)
            && ($user->isAdmin() || $order->location_id === $user->primaryLocation()?->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->can('purchase_orders.create');
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return ($user->isAdmin() || $user->can('purchase_orders.update'))
            && $this->belongsToUserLocation($user, $order)
            && $order->statusValue() === PurchaseOrderStatus::Draft->value;
    }

    public function submit(User $user, PurchaseOrder $order): bool
    {
        return $this->update($user, $order);
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return ($user->isAdmin() || $user->can('purchase_orders.approve'))
            && $this->belongsToUserLocation($user, $order)
            && $order->statusValue() === PurchaseOrderStatus::Submitted->value;
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return ($user->isAdmin() || $user->can('purchase_orders.cancel'))
            && $this->belongsToUserLocation($user, $order)
            && ! in_array($order->statusValue(), [
                PurchaseOrderStatus::Received->value,
                PurchaseOrderStatus::Cancelled->value,
            ], true);
    }

    private function belongsToUserLocation(User $user, PurchaseOrder $order): bool
    {
        return $user->isAdmin() || $order->location_id === $user->primaryLocation()?->id;
    }
}
