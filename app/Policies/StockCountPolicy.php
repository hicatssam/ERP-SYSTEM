<?php

namespace App\Policies;

use App\Enums\StockCountStatus;
use App\Models\StockCount;
use App\Models\User;

class StockCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can('inventory.count');
    }

    public function view(User $user, StockCount $count): bool
    {
        return $this->viewAny($user)
            && ($user->isAdmin() || $count->location_id === $user->primaryLocation()?->id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, StockCount $count): bool
    {
        return ($user->isAdmin() || $user->can('inventory.count'))
            && ($user->isAdmin() || $count->location_id === $user->primaryLocation()?->id)
            && in_array($this->value($count), [StockCountStatus::Draft->value, StockCountStatus::InProgress->value], true);
    }

    public function approve(User $user, StockCount $count): bool
    {
        return ($user->isAdmin() || $user->can('inventory.count'))
            && ($user->isAdmin() || $count->location_id === $user->primaryLocation()?->id)
            && $this->value($count) === StockCountStatus::InProgress->value;
    }

    public function delete(User $user, StockCount $count): bool
    {
        return $user->isAdmin() && $this->value($count) !== StockCountStatus::Approved->value;
    }

    private function value(StockCount $count): string
    {
        return $count->status instanceof \BackedEnum
            ? $count->status->value
            : (string) $count->status;
    }
}
