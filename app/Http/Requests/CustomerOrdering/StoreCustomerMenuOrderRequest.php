<?php

namespace App\Http\Requests\CustomerOrdering;

use App\Enums\RestaurantServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerMenuOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/[^\d+]/', '', trim((string) $this->input('phone', '')));

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'phone' => $phone,
            'address' => trim((string) $this->input('address', '')),
            'notes' => trim((string) $this->input('notes', '')),
            'payment_reference' => trim((string) $this->input('payment_reference', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'request_token' => ['required', 'string', 'min:20', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:7', 'max:20'],
            'service_type' => ['required', Rule::in([
                RestaurantServiceType::DineIn->value,
                RestaurantServiceType::Takeaway->value,
                RestaurantServiceType::Delivery->value,
            ])],
            'restaurant_table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:700'],
            'items' => ['required', 'array', 'min:1', 'max:80'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.kitchen_notes' => ['nullable', 'string', 'max:300'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.modifiers' => ['nullable', 'array', 'max:30'],
            'items.*.modifiers.*.modifier_id' => ['required', 'integer', 'exists:modifiers,id'],
            'items.*.modifiers.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],

            // Payment IDs are re-validated against the route location inside CustomerOrderingService.
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payment_account_id' => ['nullable', 'integer', 'exists:location_payment_accounts,id'],
            'payment_reference' => ['nullable', 'string', 'max:150'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $serviceType = (string) $this->input('service_type');

            if ($serviceType === RestaurantServiceType::DineIn->value && ! $this->filled('restaurant_table_id')) {
                $validator->errors()->add('restaurant_table_id', 'اختر الطاولة لطلب داخل المطعم.');
            }

            if ($serviceType === RestaurantServiceType::Delivery->value && ! $this->filled('address')) {
                $validator->errors()->add('address', 'أدخل عنوان التوصيل.');
            }
        });
    }
}
