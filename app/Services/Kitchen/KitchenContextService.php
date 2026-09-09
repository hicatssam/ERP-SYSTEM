<?php

namespace App\Services\Kitchen;

use App\Models\KitchenTicket;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class KitchenContextService
{
    public function resolveLocation(
        User $user,
        ?int $requestedLocationId = null
    ): Location {
        $canViewAll = $user->isAdmin()
            || $user->can('kitchen.view_all_locations');

        if ($canViewAll) {
            if ($requestedLocationId) {
                $location = Location::query()
                    ->branches()
                    ->active()
                    ->find($requestedLocationId);

                if (! $location) {
                    throw ValidationException::withMessages([
                        'location_id' => 'الفرع المحدد غير موجود أو غير مفعّل.',
                    ]);
                }

                return $location;
            }

            $primary = $user->primaryLocation();

            if ($primary && $primary->isBranch() && $primary->is_active) {
                return $primary;
            }

            $fallback = Location::query()
                ->branches()
                ->active()
                ->orderBy('id')
                ->first();

            abort_unless(
                $fallback,
                403,
                'لا يوجد فرع فعال متاح للمطبخ.'
            );

            return $fallback;
        }

        $location = $user->primaryLocation();

        abort_unless(
            $location && $location->isBranch() && $location->is_active,
            403,
            'لا يوجد فرع رئيسي فعال مرتبط بحسابك.'
        );

        if (
            $requestedLocationId
            && (int) $requestedLocationId !== (int) $location->id
        ) {
            abort(403, 'لا يمكنك الوصول إلى مطبخ فرع آخر.');
        }

        return $location;
    }

    public function selectableLocations(User $user): Collection
    {
        if (
            $user->isAdmin()
            || $user->can('kitchen.view_all_locations')
        ) {
            return Location::query()
                ->branches()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $location = $user->primaryLocation();

        return $location
            ? collect([$location])
            : collect();
    }

    public function authorizeTicket(
        User $user,
        KitchenTicket $ticket
    ): void {
        $location = $this->resolveLocation(
            $user,
            (int) $ticket->location_id
        );

        abort_unless(
            (int) $location->id === (int) $ticket->location_id,
            403,
            'لا يمكنك الوصول إلى تذكرة مطبخ تابعة لفرع آخر.'
        );
    }
}
