<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpecialCakeOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'customer_id'          => ['required', 'exists:customers,id'],
            'required_date'        => ['required', 'date', 'after:today'],
            'required_time'        => ['nullable', 'date_format:H:i'],
            'cake_type'            => ['nullable', 'string', 'max:100'],
            'cake_size'            => ['nullable', 'string', 'max:50'],
            'cake_weight'          => ['nullable', 'numeric', 'min:0'],
            'persons_count'        => ['nullable', 'integer', 'min:1'],
            'flavor'               => ['nullable', 'string', 'max:100'],
            'filling'              => ['nullable', 'string', 'max:100'],
            'shape'                => ['nullable', 'string', 'max:100'],
            'color'                => ['nullable', 'string', 'max:50'],
            'cake_text'            => ['nullable', 'string', 'max:200'],
            'theme'                => ['nullable', 'string', 'max:200'],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
            'total_price'          => ['required', 'numeric', 'min:0'],
            'payment_arrangement'  => ['required', 'in:pay_now,deposit,partial_payment,pay_on_pickup,pending_verification'],
        ];
    }
}
