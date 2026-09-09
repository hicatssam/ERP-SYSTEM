<?php

namespace App\Http\Controllers\Procurement\Concerns;

use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;

trait ScopesProcurementLocations
{
    /** @return Collection<int, int> */
    protected function permittedLocationIds(User $user): Collection
    {
        return $user->isAdmin()
            // Keep historical procurement documents visible to administrators
            // even when a location was later deactivated.
            ? Location::query()->pluck('id')
            : collect([$user->primaryLocation()?->id])->filter()->values();
    }

    /** @return Collection<int, Location> */
    protected function permittedLocations(User $user): Collection
    {
        return Location::query()
            ->active()
            ->whereIn('id', $this->permittedLocationIds($user))
            ->orderBy('name')
            ->get();
    }

    protected function ensurePermittedLocation(User $user, int $locationId): void
    {
        abort_unless($this->permittedLocationIds($user)->contains($locationId), 403);
    }
}
