<?php

namespace App\Http\Requests\Finance;

use App\Models\IncomingBankTransfer;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreIncomingBankTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) (
            $user
            && (
                $user->isAdmin()
                || $user->can('payments.record')
            )
        );
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if (
            $user
            && ! $user->isAdmin()
            && ! $user->can('financial.global.view')
        ) {
            $this->merge([
                'location_id' => $user->primaryLocation()?->id,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'location_payment_account_id' => [
                'nullable',
                'integer',
                'exists:location_payment_accounts,id',
            ],
            'sender_name' => ['required', 'string', 'max:150'],
            'sender_phone' => [
                'nullable',
                'string',
                'max:50',
                'required_without:sender_account_number',
            ],
            'sender_account_number' => [
                'nullable',
                'string',
                'max:120',
                'required_without:sender_phone',
            ],
            'reference_number' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'received_at' => ['nullable', 'date'],
            'payment_proof' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:10240',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $locationId = (int) $this->input('location_id', 0);
            $methodId = (int) $this->input('payment_method_id', 0);

            if ($locationId <= 0 || $methodId <= 0 || ! $user) {
                return;
            }

            $location = Location::query()
                ->branches()
                ->active()
                ->find($locationId);

            if (! $location) {
                $validator->errors()->add(
                    'location_id',
                    'الفرع المحدد غير صالح أو غير فعال.'
                );

                return;
            }

            if (
                ! $user->isAdmin()
                && ! $user->can('financial.global.view')
                && (int) ($user->primaryLocation()?->id ?? 0) !== $locationId
            ) {
                $validator->errors()->add(
                    'location_id',
                    'لا يمكنك تسجيل حوالة لفرع آخر.'
                );
            }

            $method = PaymentMethod::query()
                ->active()
                ->find($methodId);

            if (! $method || (string) $method->type === 'cash') {
                $validator->errors()->add(
                    'payment_method_id',
                    'اختر طريقة دفع غير نقدية فعالة.'
                );

                return;
            }

            $assignments = $method->locationPaymentMethods();

            if (
                (clone $assignments)->exists()
                && ! (clone $assignments)
                    ->where('location_id', $locationId)
                    ->where('is_active', true)
                    ->exists()
            ) {
                $validator->errors()->add(
                    'payment_method_id',
                    'طريقة الدفع غير مفعلة في هذا الفرع.'
                );
            }

            $activeAccounts = LocationPaymentAccount::query()
                ->where('location_id', $locationId)
                ->where('payment_method_id', $methodId)
                ->where('is_active', true);

            $accountId = (int) $this->input('location_payment_account_id', 0);

            if ((clone $activeAccounts)->exists() && $accountId <= 0) {
                $validator->errors()->add(
                    'location_payment_account_id',
                    'اختر حساب الاستلام الذي وصلت إليه الحوالة.'
                );
            }

            if (
                $accountId > 0
                && ! (clone $activeAccounts)->whereKey($accountId)->exists()
            ) {
                $validator->errors()->add(
                    'location_payment_account_id',
                    'حساب الاستلام لا يتبع الفرع أو طريقة الدفع المحددة.'
                );
            }

            $reference = trim((string) $this->input('reference_number'));

            if (
                $reference !== ''
                && IncomingBankTransfer::query()
                    ->where('location_id', $locationId)
                    ->where('payment_method_id', $methodId)
                    ->where('reference_number', $reference)
                    ->exists()
            ) {
                $validator->errors()->add(
                    'reference_number',
                    'هذه الحوالة مسجلة مسبقًا بنفس الفرع وطريقة الدفع ورقم المرجع.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'sender_phone.required_without' =>
                'أدخل رقم جوال المحوّل أو رقم حسابه/محفظته.',
            'sender_account_number.required_without' =>
                'أدخل رقم حساب/محفظة المحوّل أو رقم جواله.',
            'payment_proof.mimes' =>
                'إثبات الحوالة يجب أن يكون صورة أو PDF.',
            'payment_proof.max' =>
                'حجم إثبات الحوالة يجب ألا يتجاوز 10MB.',
        ];
    }
}
