<?php

namespace App\Services\Production;

use App\Models\Location;
use App\Models\ProductionBatch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductionContextService
{
    public function resolveLocation(
        User $user,
        ?int $requestedLocationId = null
    ): Location {
        $canViewAll = $user->isAdmin()
            || $user->can('production.view_all_locations');

        if ($canViewAll) {
            if ($requestedLocationId) {
                $location = Location::query()
                    ->active()
                    ->find($requestedLocationId);

                if (! $location) {
                    throw ValidationException::withMessages([
                        'location_id' => 'الموقع المحدد غير موجود أو غير مفعّل.',
                    ]);
                }

                return $location;
            }

            $primary = $user->primaryLocation();

            if ($primary && $primary->is_active) {
                return $primary;
            }

            $fallback = Location::query()
                ->active()
                ->orderByRaw("CASE WHEN type = 'factory' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->first();

            abort_unless(
                $fallback,
                403,
                'لا يوجد موقع فعال متاح للإنتاج.'
            );

            return $fallback;
        }

        $location = $user->primaryLocation();

        abort_unless(
            $location && $location->is_active,
            403,
            'لا يوجد موقع رئيسي فعال مرتبط بحسابك.'
        );

        if (
            $requestedLocationId
            && (int) $requestedLocationId !== (int) $location->id
        ) {
            abort(403, 'لا يمكنك الوصول إلى إنتاج موقع آخر.');
        }

        return $location;
    }

    public function selectableLocations(User $user): Collection
    {
        if (
            $user->isAdmin()
            || $user->can('production.view_all_locations')
        ) {
            return Location::query()
                ->active()
                ->orderByRaw("CASE WHEN type = 'factory' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name', 'type']);
        }

        $location = $user->primaryLocation();

        return $location
            ? collect([$location])
            : collect();
    }

    /** Compatibility API used by the production-orders workflow. */
    public function locations(User $user): Collection
    {
        return $this->selectableLocations($user);
    }

    public function locationsFor(User $user): Collection
    {
        return $this->selectableLocations($user);
    }

    /** @return array<int, int> */
    public function accessibleLocationIds(User $user): array
    {
        return $this->selectableLocations($user)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<int, int> */
    public function locationIdsFor(User $user): array
    {
        return $this->accessibleLocationIds($user);
    }

    public function assertLocationAccess(User $user, int $locationId): void
    {
        $this->resolveLocation($user, $locationId);
    }

    public function assertCanAccessLocation(User $user, int $locationId): void
    {
        $this->assertLocationAccess($user, $locationId);
    }

    public function authorizeBatch(
        User $user,
        ProductionBatch $batch
    ): void {
        $location = $this->resolveLocation(
            $user,
            (int) $batch->location_id
        );

        abort_unless(
            (int) $location->id === (int) $batch->location_id,
            403,
            'لا يمكنك الوصول إلى دفعة إنتاج تابعة لموقع آخر.'
        );
    }
}
