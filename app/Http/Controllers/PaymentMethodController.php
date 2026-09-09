<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    /**
     * عرض جميع طرق الدفع.
     */
    public function index()
    {
        $methods = PaymentMethod::query()
            ->withTrashed()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.payment-methods.index', compact('methods'));
    }

    /**
     * صفحة إضافة طريقة دفع.
     */
    public function create()
    {
        return view('admin.payment-methods.create');
    }

    /**
     * حفظ طريقة دفع جديدة.
     */
    public function store(Request $request)
    {
        $data = $this->validatePaymentMethod($request);

        $data['code'] = strtoupper($data['code']);

        $data['is_active'] = $request->boolean('is_active');
        $data['requires_verification'] = $request->boolean('requires_verification');
        $data['requires_reference'] = $request->boolean('requires_reference');

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request
                ->file('logo')
                ->store('payment-methods', 'public');
        }

        PaymentMethod::create($data);

        return redirect()
            ->route('payment-methods.index')
            ->with('success', 'تمت إضافة طريقة الدفع بنجاح.');
    }

    /**
     * عرض تفاصيل طريقة الدفع.
     */
    public function show(PaymentMethod $paymentMethod)
    {
        return redirect()
            ->route('payment-methods.edit', $paymentMethod);
    }

    /**
     * صفحة تعديل طريقة الدفع.
     */
    public function edit(PaymentMethod $paymentMethod)
    {
        $method = $paymentMethod;

        return view(
            'admin.payment-methods.edit',
            compact('method')
        );
    }

    /**
     * تحديث طريقة الدفع.
     */
    public function update(
        Request $request,
        PaymentMethod $paymentMethod
    ) {
        $data = $this->validatePaymentMethod(
            $request,
            $paymentMethod
        );

        $data['code'] = strtoupper($data['code']);

        $data['is_active'] = $request->boolean('is_active');
        $data['requires_verification'] =
            $request->boolean('requires_verification');

        $data['requires_reference'] =
            $request->boolean('requires_reference');

        if ($request->hasFile('logo')) {

            if (
                $paymentMethod->logo_path &&
                Storage::disk('public')->exists(
                    $paymentMethod->logo_path
                )
            ) {
                Storage::disk('public')->delete(
                    $paymentMethod->logo_path
                );
            }

            $data['logo_path'] = $request
                ->file('logo')
                ->store('payment-methods', 'public');
        }

        $paymentMethod->update($data);

        return redirect()
            ->route('payment-methods.index')
            ->with('success', 'تم تحديث طريقة الدفع بنجاح.');
    }

    /**
     * حذف طريقة الدفع.
     */
    public function destroy(PaymentMethod $paymentMethod)
    {
        $paymentMethod->delete();

        return redirect()
            ->route('payment-methods.index')
            ->with('success', 'تم حذف طريقة الدفع بنجاح.');
    }

    /**
     * تفعيل أو تعطيل طريقة الدفع.
     */
    public function toggle(PaymentMethod $paymentMethod)
    {
        $paymentMethod->update([
            'is_active' => ! $paymentMethod->is_active,
        ]);

        return redirect()
            ->back()
            ->with(
                'success',
                $paymentMethod->is_active
                    ? 'تم تفعيل طريقة الدفع.'
                    : 'تم تعطيل طريقة الدفع.'
            );
    }

    /**
     * التحقق من البيانات.
     */
    private function validatePaymentMethod(
        Request $request,
        ?PaymentMethod $paymentMethod = null
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'name_ar' => [
                'required',
                'string',
                'max:255',
            ],

            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('payment_methods', 'code')
                    ->ignore($paymentMethod?->id),
            ],

            'type' => [
                'required',
                Rule::in([
                    'cash',
                    'electronic_wallet',
                    'bank_transfer',
                    'card_pos',
                    'other',
                ]),
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp,svg',
                'max:2048',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'api_key' => [
                'nullable',
                'string',
                'max:500',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ]);
    }
}