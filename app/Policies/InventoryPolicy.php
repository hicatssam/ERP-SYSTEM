<?php

namespace App\Policies;

use App\Models\Inventory;
use App\Models\User;

/**
 * Keeps the replacement InventoryController compatible with Laravel policy
 * discovery and with the existing `inventory.view` / `inventory.adjust`
 * permissions.
 */
class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can('inventory.view');
    }

    public function view(User $user, Inventory $inventory): bool
    {
        return $this->viewAny($user)
            && ($user->isAdmin() || $inventory->location_id === $user->primaryLocation()?->id);
    }

    public function adjust(User $user): bool
    {
        return $user->isAdmin() || $user->can('inventory.adjust');
    }
}
