<?php

namespace App\Services\Growth;

use App\Models\Customer;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerGrowthAccessService
{
    /**
     * Apply the same customer/location rules used by the existing ERP, while
     * allowing the explicit Growth "view all" permission to widen the scope.
     */
    public function applyCustomerScope(
        Builder $query,
        User $user,
        ?string $allPermission = 'crm.view_all'
    ): Builder {
        if ($this->canViewAllCustomers($user, $allPermission)) {
            return $query;
        }

        // Current Dahab Customer model already exposes accessibleBy(). Reuse it
        // so central/selected-branch customers keep their existing semantics.
        if (method_exists(Customer::class, 'scopeAccessibleBy')) {
            return $query->accessibleBy($user);
        }

        $locationId = $user->primaryLocation()?->id;

        return $locationId
            ? $query->where('location_id', $locationId)
            : $query->whereRaw('1 = 0');
    }

    public function ensureCustomerAccess(
        Customer $customer,
        User $user,
        ?string $allPermission = 'crm.view_all'
    ): void {
        if ($this->canViewAllCustomers($user, $allPermission)) {
            return;
        }

        if (method_exists($customer, 'canBeAccessedBy')) {
            abort_unless(
                $customer->canBeAccessedBy($user),
                403,
                'لا يمكنك الوصول إلى هذا العميل.'
            );

            return;
        }

        $locationId = $user->primaryLocation()?->id;

        abort_unless(
            $locationId && (int) $customer->location_id === (int) $locationId,
            403,
            'لا يمكنك الوصول إلى هذا العميل.'
        );
    }

    public function canViewAllCustomers(
        User $user,
        ?string $allPermission = 'crm.view_all'
    ): bool {
        if ($user->isAdmin()) {
            return true;
        }

        if ($allPermission && $user->can($allPermission)) {
            return true;
        }

        // Preserve existing Dahab global-customer authorities.
        return $user->can('customers.view_all')
            || $user->can('financial.global.view');
    }

    public function customerStatsLocationId(
        User $user,
        ?string $allPermission = 'crm.view_all'
    ): ?int {
        if ($this->canViewAllCustomers($user, $allPermission)) {
            return null;
        }

        $location = $user->primaryLocation();

        if (! $location || ! $location->isBranch()) {
            return null;
        }

        return (int) $location->id;
    }

    /** @return Collection<int, Location> */
    public function selectableLocations(User $user, string $allPermission): Collection
    {
        if (
            $user->isAdmin()
            || $user->can($allPermission)
            || ($allPermission === 'crm.view_all'
                && $this->canViewAllCustomers($user, $allPermission))
        ) {
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

        return Location::query()
            ->branches()
            ->active()
            ->whereKey($primary->id)
            ->get();
    }

    public function ensureLocationAccess(
        int $locationId,
        User $user,
        string $allPermission
    ): void {
        if (
            $user->isAdmin()
            || $user->can($allPermission)
            || ($allPermission === 'crm.view_all'
                && $this->canViewAllCustomers($user, $allPermission))
        ) {
            abort_unless(
                Location::query()->active()->whereKey($locationId)->exists(),
                404,
                'الموقع غير موجود أو غير فعال.'
            );

            return;
        }

        abort_unless(
            (int) $user->primaryLocation()?->id === $locationId,
            403,
            'لا يمكنك إدارة بيانات موقع آخر.'
        );
    }
}
