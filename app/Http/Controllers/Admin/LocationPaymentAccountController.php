<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\LocationPaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationPaymentAccountController extends Controller
{
    public function index(Location $location): View
    {
        $accounts = LocationPaymentAccount::query()
            ->whereHas('locationPaymentMethod', fn ($query) => $query
                ->where('location_id', $location->id))
            ->with([
                'locationPaymentMethod.paymentMethod',
                'paymentMethod',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $locationPaymentMethods = LocationPaymentMethod::query()
            ->where('location_id', $location->id)
            ->where('is_active', true)
            ->whereHas('paymentMethod', fn ($query) => $query->where('is_active', true))
            ->with('paymentMethod')
            ->get()
            ->sortBy(fn (LocationPaymentMethod $item): string => sprintf(
                '%010d-%010d',
                (int) ($item->paymentMethod?->sort_order ?? PHP_INT_MAX),
                (int) ($item->paymentMethod?->id ?? PHP_INT_MAX)
            ))
            ->values();

        // Keep the existing Blade contract: it still submits payment_method_id.
        $paymentMethods = $locationPaymentMethods
            ->pluck('paymentMethod')
            ->filter()
            ->values();

        return view('admin.locations.payment-accounts.index', compact(
            'location',
            'accounts',
            'paymentMethods',
            'locationPaymentMethods'
        ));
    }

    public function store(Request $request, Location $location): RedirectResponse
    {
        $data = $this->validated($request);
        $locationMethod = $this->resolveLocationMethod(
            $location,
            (int) $data['payment_method_id']
        );

        LocationPaymentAccount::query()->create([
            ...$this->accountAttributes($request, $data),
            'location_payment_method_id' => $locationMethod->id,
            'location_id' => $location->id,
            'payment_method_id' => $locationMethod->payment_method_id,
        ]);

        return back()->with('success', 'تمت إضافة حساب الدفع للفرع بنجاح.');
    }

    public function update(
        Request $request,
        Location $location,
        LocationPaymentAccount $account
    ): RedirectResponse {
        $this->assertAccountBelongsToLocation($account, $location);

        $data = $this->validated($request);
        $locationMethod = $this->resolveLocationMethod(
            $location,
            (int) $data['payment_method_id']
        );

        $account->update([
            ...$this->accountAttributes($request, $data),
            'location_payment_method_id' => $locationMethod->id,
            'location_id' => $location->id,
            'payment_method_id' => $locationMethod->payment_method_id,
        ]);

        return back()->with('success', 'تم تحديث حساب الدفع بنجاح.');
    }

    public function destroy(
        Location $location,
        LocationPaymentAccount $account
    ): RedirectResponse {
        $this->assertAccountBelongsToLocation($account, $location);
        $account->delete();

        return back()->with('success', 'تم حذف حساب الدفع.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
            'name' => ['required', 'string', 'max:190'],
            'provider_name' => ['nullable', 'string', 'max:190'],
            'account_holder_name' => ['nullable', 'string', 'max:190'],
            'account_number' => ['nullable', 'string', 'max:190'],
            'iban' => ['nullable', 'string', 'max:190'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'wallet_number' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function accountAttributes(Request $request, array $data): array
    {
        return [
            'name' => $data['name'],
            'provider_name' => $data['provider_name'] ?? null,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'iban' => $data['iban'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'wallet_number' => $data['wallet_number'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function resolveLocationMethod(
        Location $location,
        int $paymentMethodId
    ): LocationPaymentMethod {
        $locationMethod = LocationPaymentMethod::query()
            ->where('location_id', $location->id)
            ->where('payment_method_id', $paymentMethodId)
            ->where('is_active', true)
            ->whereHas('paymentMethod', fn ($query) => $query->where('is_active', true))
            ->first();

        abort_unless($locationMethod, 422, 'طريقة الدفع غير مفعلة لهذا الفرع.');

        return $locationMethod;
    }

    private function assertAccountBelongsToLocation(
        LocationPaymentAccount $account,
        Location $location
    ): void {
        $belongs = $account->locationPaymentMethod()
            ->where('location_id', $location->id)
            ->exists();

        abort_unless($belongs, 404);
    }
}
