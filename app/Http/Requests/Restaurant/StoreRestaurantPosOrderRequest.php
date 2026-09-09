<?php

namespace App\Http\Requests\Restaurant;

use App\Enums\PaymentArrangement;
use App\Enums\RestaurantServiceType;
use App\Models\PaymentMethod;
use App\Models\SalesChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestaurantPosOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) (
            $this->user()?->can('orders.create')
            && $this->user()?->can('restaurant_pos.use')
        );
    }

    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'service_type' => [
                'required',
                Rule::in(array_map(
                    fn (RestaurantServiceType $type): string => $type->value,
                    RestaurantServiceType::cases()
                )),
            ],
            'restaurant_table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'payment_arrangement' => [
                'required',
                Rule::in(array_map(
                    fn (PaymentArrangement $arrangement): string => $arrangement->value,
                    PaymentArrangement::cases()
                )),
            ],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.kitchen_notes' => ['nullable', 'string', 'max:500'],
            'items.*.modifiers' => ['nullable', 'array', 'max:20'],
            'items.*.modifiers.*.modifier_id' => ['required', 'integer', 'exists:modifiers,id'],
            'items.*.modifiers.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'sales_channel_id' => ['required', 'integer', 'exists:sales_channels,id'],
            'discount_type' => ['nullable', Rule::in(['none', 'percentage', 'fixed'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $serviceType = (string) $this->input('service_type');

            if (
                $serviceType === RestaurantServiceType::DineIn->value
                && ! $this->filled('restaurant_table_id')
            ) {
                $validator->errors()->add(
                    'restaurant_table_id',
                    'يجب اختيار الطاولة لطلب داخل المطعم.'
                );
            }

            if (
                $serviceType === RestaurantServiceType::DineIn->value
                && (int) $this->input('guest_count', 0) < 1
            ) {
                $validator->errors()->add(
                    'guest_count',
                    'يجب إدخال عدد الضيوف.'
                );
            }

            $arrangement = (string) $this->input('payment_arrangement');

            $noImmediatePayment = in_array(
                $arrangement,
                [
                    PaymentArrangement::PayOnPickup->value,
                    PaymentArrangement::OnAccount->value,
                ],
                true
            );

            if (
                $arrangement === PaymentArrangement::OnAccount->value
                && ! $this->filled('customer_id')
            ) {
                $validator->errors()->add(
                    'customer_id',
                    'يجب اختيار عميل مسجل للبيع على الحساب.'
                );
            }

            if (! $noImmediatePayment && ! $this->filled('payment_method_id')) {
                $validator->errors()->add(
                    'payment_method_id',
                    'طريقة الدفع مطلوبة لهذا النوع من الدفع.'
                );
            }

            if (
                in_array(
                    $arrangement,
                    [
                        PaymentArrangement::Deposit->value,
                        PaymentArrangement::PartialPayment->value,
                    ],
                    true
                )
                && (float) $this->input('paid_amount', 0) <= 0
            ) {
                $validator->errors()->add(
                    'paid_amount',
                    'يجب إدخال المبلغ المدفوع.'
                );
            }

            if ($arrangement === PaymentArrangement::PendingVerification->value) {
                if (! $this->filled('reference_number')) {
                    $validator->errors()->add(
                        'reference_number',
                        'رقم العملية مطلوب للدفعة بانتظار التحقق.'
                    );
                }

                if (! $this->hasFile('payment_proof')) {
                    $validator->errors()->add(
                        'payment_proof',
                        'إثبات الدفع مطلوب للدفعة بانتظار التحقق.'
                    );
                }
            }

            if ($this->filled('payment_method_id') && ! $noImmediatePayment) {
                $method = PaymentMethod::query()
                    ->active()
                    ->find($this->integer('payment_method_id'));

                if (! $method) {
                    $validator->errors()->add(
                        'payment_method_id',
                        'طريقة الدفع المحددة غير مفعلة.'
                    );
                } else {
                    if (
                        ($method->requires_reference || $method->requires_verification)
                        && ! $this->filled('reference_number')
                    ) {
                        $validator->errors()->add(
                            'reference_number',
                            'رقم العملية مطلوب لطريقة الدفع المحددة.'
                        );
                    }

                    if (
                        $method->requires_verification
                        && ! $this->hasFile('payment_proof')
                    ) {
                        $validator->errors()->add(
                            'payment_proof',
                            'إثبات الدفع مطلوب لطريقة الدفع المحددة.'
                        );
                    }
                }
            }

            if (
                $this->filled('sales_channel_id')
                && ! SalesChannel::query()
                    ->active()
                    ->whereKey($this->integer('sales_channel_id'))
                    ->exists()
            ) {
                $validator->errors()->add(
                    'sales_channel_id',
                    'قناة البيع المحددة غير مفعلة.'
                );
            }

            if (
                $this->input('discount_type') === 'percentage'
                && (float) $this->input('discount_value', 0) > 100
            ) {
                $validator->errors()->add(
                    'discount_value',
                    'نسبة الخصم لا يمكن أن تتجاوز 100%.'
                );
            }
        });
    }
}
