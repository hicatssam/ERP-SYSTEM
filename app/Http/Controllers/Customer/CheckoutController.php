<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\ResolvesCustomerMenuBranding;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\LocationPaymentMethod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    use ResolvesCustomerMenuBranding;

    public function index(Location $location): View
    {
        abort_unless((bool) $location->is_active && $location->isBranch(), 404);

        return view('customer-menu.checkout.checkout', [
            'location' => $location,
            'tables' => $this->tableOptions($location),
            'paymentMethods' => $this->paymentMethodsFor($location),
            'branding' => $this->branding($location),
            'theme' => $this->theme(),
            'requestToken' => (string) Str::uuid(),
            'serviceOptions' => $this->serviceOptions(),
        ]);
    }

    /**
     * Checkout must not depend on an XHR before the customer can pay.
     * Build the branch-scoped methods on the server and support both the
     * normalized account schema and older Dahab installations.
     */
    private function paymentMethodsFor(Location $location): Collection
    {
        return LocationPaymentMethod::query()
            ->with('paymentMethod')
            ->where('location_id', (int) $location->id)
            ->where('is_active', true)
            ->whereHas('paymentMethod', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn (LocationPaymentMethod $row): string => sprintf(
                '%010d-%010d',
                (int) ($row->paymentMethod?->sort_order ?? 0),
                (int) ($row->paymentMethod?->id ?? 0)
            ))
            ->map(function (LocationPaymentMethod $locationMethod) use ($location): array {
                $method = $locationMethod->paymentMethod;

                $accounts = LocationPaymentAccount::query()
                    ->where('is_active', true)
                    ->when(
                        Schema::hasColumn('location_payment_accounts', 'location_payment_method_id'),
                        fn ($query) => $query->where(function ($q) use ($locationMethod, $location, $method) {
                            $q->where('location_payment_method_id', (int) $locationMethod->id);

                            if (
                                Schema::hasColumn('location_payment_accounts', 'location_id')
                                && Schema::hasColumn('location_payment_accounts', 'payment_method_id')
                            ) {
                                $q->orWhere(function ($legacy) use ($location, $method) {
                                    $legacy->whereNull('location_payment_method_id')
                                        ->where('location_id', (int) $location->id)
                                        ->where('payment_method_id', (int) $method->id);
                                });
                            }
                        }),
                        fn ($query) => $query
                            ->where('location_id', (int) $location->id)
                            ->where('payment_method_id', (int) $method->id)
                    )
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (LocationPaymentAccount $account): array => [
                        'id' => (int) $account->id,
                        'name' => (string) ($account->name ?? ''),
                        'provider_name' => $account->provider_name ?? null,
                        'account_holder_name' => $account->account_holder_name ?? null,
                        'account_number' => $account->account_number ?? null,
                        'iban' => $account->iban ?? null,
                        'phone_number' => $account->phone_number ?? ($account->mobile_number ?? null),
                        'wallet_number' => $account->wallet_number ?? null,
                        'instructions' => $account->instructions ?? null,
                    ])
                    ->values();

                return [
                    'id' => (int) $method->id,
                    'location_payment_method_id' => (int) $locationMethod->id,
                    'name' => (string) ($method->name_ar ?: $method->name),
                    'code' => (string) $method->code,
                    'type' => (string) $method->type,
                    'logo' => $this->assetFromPath($method->logo ?: $method->logo_path),
                    'requires_verification' => (bool) $method->requires_verification,
                    'requires_reference' => (bool) $method->requires_reference,
                    'accounts' => $accounts->all(),
                ];
            })
            ->values();
    }
}
