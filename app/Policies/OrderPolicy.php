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
        // Compatibility bridge for the existing order details/controller:
        // - the Complete button is rendered with @can('update') on orders.show
        // - OrderController::complete also authorizes 'update'
        // Allow completion-only operators only in those two confirmed-order
        // contexts. Normal edit/update routes still require orders.update.
        if (
            $this->statusValue($order) === 'confirmed'
            && request()?->routeIs('orders.show', 'orders.complete')
            && $user->hasPermissionTo('orders.complete')
            && $this->belongsToUserLocation($user, $order)
        ) {
            return true;
        }

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

        /*
         * Block confirmation only when there is a REAL payment row still
         * waiting for verification. Do not rely only on orders.payment_status:
         * that field is a denormalized summary and can be stale after an older
         * verification flow. A stale pending_payment_verification value used to
         * hide the confirm button forever even though no pending payment existed.
         */
        $hasPendingVerification = $order->payments()
            ->where('status', 'pending_verification')
            ->exists();

        if ($hasPendingVerification) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $this->belongsToUserLocation($user, $order)) {
            return false;
        }

        // Preserve the explicit Dahab rule: Branch Manager does not confirm
        // sales orders. Restaurant operators may accept only restaurant drafts
        // (POS/QR/customer-menu), never generic ERP sales orders.
        if ($user->hasRole('Branch Manager')) {
            return false;
        }

        if ($user->hasAnyRole(['Cashier', 'Waiter'])) {
            return $user->hasPermissionTo('orders.confirm')
                && $user->hasPermissionTo('restaurant_pos.use')
                && $order->isRestaurantOrder();
        }

        return $user->hasPermissionTo('orders.confirm');
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
