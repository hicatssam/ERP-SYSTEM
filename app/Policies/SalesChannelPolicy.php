<?php

namespace App\Policies;

use App\Models\SalesChannel;
use App\Models\User;

class SalesChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales_channels.view');
    }

    public function view(User $user, SalesChannel $channel): bool
    {
        return $user->can('sales_channels.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales_channels.create');
    }

    public function update(User $user, SalesChannel $channel): bool
    {
        return $user->can('sales_channels.update');
    }

    public function delete(User $user, SalesChannel $channel): bool
    {
        return $user->can('sales_channels.delete');
    }

    public function toggleStatus(User $user, SalesChannel $channel): bool
    {
        return $user->can('sales_channels.activate');
    }

    public function reports(User $user): bool
    {
        return $user->can('sales_channels.reports');
    }
}
