<?php

namespace App\Policies;

use App\Enums\CakeOrderStatus;
use App\Models\SpecialCakeOrder;
use App\Models\User;

class SpecialCakeOrderPolicy
{
    public function view(User $user, SpecialCakeOrder $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->can('cake_orders.view_all')) {
            return true;
        }

        if (! $user->can('cake_orders.view')) {
            return false;
        }

        $locationIds = $user->employee?->locations()
            ->pluck('locations.id')
            ->map(fn ($id) => (int) $id)
            ->all() ?? [];

        return in_array((int) $order->origin_branch_id, $locationIds, true)
            || in_array((int) $order->factory_location_id, $locationIds, true);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->can('cake_orders.create');
    }

    public function update(User $user, SpecialCakeOrder $order): bool
    {
        if (! $this->view($user, $order)) {
            return false;
        }

        if (! $user->isAdmin() && ! $user->can('cake_orders.edit')) {
            return false;
        }

        return in_array(
            $order->status,
            [
                CakeOrderStatus::Draft,
                CakeOrderStatus::Pending,
            ],
            true
        );
    }

    public function transition(User $user, SpecialCakeOrder $order): bool
    {
        return $this->view($user, $order)
            && ! $order->isInTerminalState();
    }

    public function delete(User $user, SpecialCakeOrder $order): bool
    {
        if (! $this->view($user, $order)) {
            return false;
        }

        if (! $user->isAdmin() && ! $user->can('cake_orders.delete')) {
            return false;
        }

        return $order->status === CakeOrderStatus::Draft;
    }
}
