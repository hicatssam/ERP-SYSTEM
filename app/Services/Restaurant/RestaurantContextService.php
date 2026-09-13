<?php

namespace App\Services\Restaurant;

use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RestaurantContextService
{
    /**
     * Resolve the branch that the restaurant operation should use.
     *
     * - System admins / explicit global restaurant operators may select any active branch.
     * - Regular users are restricted to their active primary branch.
     * - If no location_id is supplied, the user's primary branch is preferred.
     */
    public function resolveLocation(User $user, ?int $requestedLocationId = null): Location
    {
        $locations = $this->selectableLocations($user);

        if ($locations->isEmpty()) {
            throw ValidationException::withMessages([
                'location_id' => 'لا يوجد فرع فعال متاح لحسابك.',
            ]);
        }

        $primary = $user->primaryLocation();

        if (! $this->canSelectAnyBranch($user)) {
            $branch = $locations->first();

            if (
                $requestedLocationId !== null
                && (int) $requestedLocationId !== (int) $branch->id
            ) {
                abort(403, 'لا يمكنك تشغيل المطعم على فرع غير الفرع المرتبط بحسابك.');
            }

            return $branch;
        }

        if ($requestedLocationId !== null) {
            $selected = $locations->first(
                fn (Location $location) =>
                    (int) $location->id === (int) $requestedLocationId
            );

            abort_unless(
                $selected,
                404,
                'الفرع المحدد غير موجود أو غير فعال.'
            );

            return $selected;
        }

        if ($primary) {
            $primaryBranch = $locations->first(
                fn (Location $location) =>
                    (int) $location->id === (int) $primary->id
            );

            if ($primaryBranch) {
                return $primaryBranch;
            }
        }

        return $locations->first();
    }

    /** @return Collection<int, Location> */
    public function selectableLocations(User $user): Collection
    {
        if ($this->canSelectAnyBranch($user)) {
            return Location::query()
                ->branches()
                ->active()
                ->orderBy('name')
                ->get();
        }

        $primary = $user->primaryLocation();

        if (! $primary || ! $primary->isBranch()) {
            return collect();
        }

        $branch = Location::query()
            ->branches()
            ->active()
            ->whereKey($primary->id)
            ->first();

        return $branch ? collect([$branch]) : collect();
    }

    private function canSelectAnyBranch(User $user): bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if ((bool) ($user->is_admin ?? false)) {
            return true;
        }

        // roles.manage is not a branch-access permission. A branch manager may
        // legitimately manage roles without being allowed to operate another
        // branch's POS/tables. Cross-branch restaurant access must be explicit.
        return $user->can('restaurant.view_all_locations');
    }
}
