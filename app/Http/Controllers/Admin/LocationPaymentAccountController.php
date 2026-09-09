<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationPaymentAccountController extends Controller
{
    public function index(Location $location): View
    {
        $accounts = $location->paymentAccounts()
            ->with('paymentMethod')
            ->get();

        /*
         |--------------------------------------------------------------------------
         | IMPORTANT
         |--------------------------------------------------------------------------
         |
         | لاحقًا يمكننا هنا جلب طرق الدفع المفعلة فقط من:
         |
         | location_payment_methods
         |
         | لكن بما أننا لا نعرف تركيب Model الحالي لديك بشكل كامل،
         | سنبقي هذا الجزء آمنًا حاليًا.
         |
         */

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.locations.payment-accounts.index', [
            'location' => $location,
            'accounts' => $accounts,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function store(
        Request $request,
        Location $location
    ): RedirectResponse {
        $data = $request->validate([
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id'),
            ],
            'name' => [
                'required',
                'string',
                'max:190',
            ],
            'provider_name' => [
                'nullable',
                'string',
                'max:190',
            ],
            'account_holder_name' => [
                'nullable',
                'string',
                'max:190',
            ],
            'account_number' => [
                'nullable',
                'string',
                'max:190',
            ],
            'iban' => [
                'nullable',
                'string',
                'max:190',
            ],
            'phone_number' => [
                'nullable',
                'string',
                'max:50',
            ],
            'instructions' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        $data['location_id'] = $location->id;
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        LocationPaymentAccount::create($data);

        return back()->with(
            'success',
            'تمت إضافة حساب الدفع للفرع بنجاح.'
        );
    }

    public function update(
        Request $request,
        Location $location,
        LocationPaymentAccount $account
    ): RedirectResponse {
        abort_unless(
            $account->location_id === $location->id,
            404
        );

        $data = $request->validate([
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id'),
            ],
            'name' => [
                'required',
                'string',
                'max:190',
            ],
            'provider_name' => [
                'nullable',
                'string',
                'max:190',
            ],
            'account_holder_name' => [
                'nullable',
                'string',
                'max:190',
            ],
            'account_number' => [
                'nullable',
                'string',
                'max:190',
            ],
            'iban' => [
                'nullable',
                'string',
                'max:190',
            ],
            'phone_number' => [
                'nullable',
                'string',
                'max:50',
            ],
            'instructions' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $account->update($data);

        return back()->with(
            'success',
            'تم تحديث حساب الدفع بنجاح.'
        );
    }

    public function destroy(
        Location $location,
        LocationPaymentAccount $account
    ): RedirectResponse {
        abort_unless(
            $account->location_id === $location->id,
            404
        );

        $account->delete();

        return back()->with(
            'success',
            'تم حذف حساب الدفع.'
        );
    }
}