<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'order_type'        => ['required', 'in:order,special_cake_order'],
            'order_id'          => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'location_payment_account_id' => ['nullable', 'integer', 'exists:location_payment_accounts,id'],
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'reference_number'  => ['nullable', 'string', 'max:100'],
            'payment_proof'     => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'مبلغ الدفع يجب أن يكون أكبر من الصفر.',
        ];
    }
}
