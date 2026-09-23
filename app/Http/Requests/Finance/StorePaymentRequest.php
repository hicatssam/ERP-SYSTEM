<?php

namespace App\Http\Requests\Finance;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('entry_context') === 'order_bank_transfer') {
            // Manual bank/e-wallet entries from the order page must always
            // pass through the verification workflow before counting as paid.
            $this->merge([
                'force_verification' => true,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'entry_context' => ['nullable', 'in:order_bank_transfer'],
            'force_verification' => ['nullable', 'boolean'],
            'order_type' => ['required', 'in:order,special_cake_order'],
            'order_id' => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'location_payment_account_id' => [
                'nullable',
                'integer',
                'exists:location_payment_accounts,id',
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_proof' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
            'sender_name' => ['nullable', 'string', 'max:150'],
            'sender_phone' => ['nullable', 'string', 'max:50'],
            'sender_account_number' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('entry_context') !== 'order_bank_transfer') {
                return;
            }

            if ($this->input('order_type') !== 'order') {
                $validator->errors()->add(
                    'order_type',
                    'إضافة الحوالة من صفحة الطلب متاحة للطلبات العادية فقط.'
                );
            }

            $methodId = (int) $this->input('payment_method_id', 0);

            if ($methodId <= 0) {
                return;
            }

            $method = PaymentMethod::query()->find($methodId);

            if (
                ! $method
                || ! in_array(
                    (string) $method->type,
                    ['bank_transfer', 'electronic_wallet'],
                    true
                )
            ) {
                $validator->errors()->add(
                    'payment_method_id',
                    'يجب اختيار طريقة دفع بنكية أو محفظة إلكترونية.'
                );

                return;
            }

            if (blank($this->input('location_payment_account_id'))) {
                $validator->errors()->add(
                    'location_payment_account_id',
                    'يجب اختيار حساب الاستلام للحوالة.'
                );
            }

            if (blank($this->input('sender_name'))) {
                $validator->errors()->add(
                    'sender_name',
                    'اسم المحوّل مطلوب للحوالات البنكية والإلكترونية.'
                );
            }

            if (blank($this->input('sender_account_number'))) {
                $validator->errors()->add(
                    'sender_account_number',
                    'رقم حساب أو محفظة المحوّل مطلوب.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'مبلغ الدفع يجب أن يكون أكبر من الصفر.',
            'payment_proof.mimes' => 'إثبات الدفع يجب أن يكون صورة JPG/PNG أو ملف PDF.',
            'payment_proof.max' => 'حجم إثبات الدفع يجب ألا يتجاوز 5MB.',
        ];
    }
}
