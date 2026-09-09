<?php

namespace App\Http\Requests\Restaurant;

use App\Enums\PaymentArrangement;
use App\Enums\RestaurantServiceType;
use App\Models\LocationPaymentMethod;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerMenuOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_token' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{7,30}$/'],
            'service_type' => ['required', Rule::in(array_map(
                fn (RestaurantServiceType $type): string => $type->value,
                RestaurantServiceType::cases()
            ))],
            'restaurant_table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:30'],
            'address' => ['nullable', 'string', 'max:700'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_arrangement' => ['required', Rule::in([
                PaymentArrangement::PayNow->value,
                PaymentArrangement::PayOnPickup->value,
                PaymentArrangement::PendingVerification->value,
            ])],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:50'],
            'items.*.kitchen_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $location = $this->route('location');
            $service = (string) $this->input('service_type');
            $arrangement = (string) $this->input('payment_arrangement');

            if ($service === RestaurantServiceType::DineIn->value && ! $this->filled('restaurant_table_id')) {
                $validator->errors()->add('restaurant_table_id', 'اختر طاولة متاحة.');
            }

            if ($service === RestaurantServiceType::Delivery->value && ! $this->filled('address')) {
                $validator->errors()->add('address', 'عنوان التوصيل مطلوب.');
            }

            if ($arrangement === PaymentArrangement::PayOnPickup->value) {
                return;
            }

            if (! $this->filled('payment_method_id')) {
                $validator->errors()->add('payment_method_id', 'اختر طريقة الدفع.');
                return;
            }

            $method = PaymentMethod::query()->active()->find($this->integer('payment_method_id'));
            $available = $method && LocationPaymentMethod::query()
                ->where('location_id', $location?->id)
                ->where('payment_method_id', $method->id)
                ->where('is_active', true)->exists();

            if (! $available) {
                $validator->errors()->add('payment_method_id', 'طريقة الدفع غير متاحة في هذا الفرع.');
                return;
            }

            if (($method->requires_reference || $method->requires_verification) && ! $this->filled('reference_number')) {
                $validator->errors()->add('reference_number', 'رقم العملية مطلوب لهذه الطريقة.');
            }

            if ($method->requires_verification && ! $this->hasFile('payment_proof')) {
                $validator->errors()->add('payment_proof', 'أرفق صورة أو PDF لإثبات الدفع.');
            }
        });
    }
}
