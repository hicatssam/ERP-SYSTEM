<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ReviewStockRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'action'            => ['required', 'in:accept,partially_accept,reject'],
            'rejection_reason'  => ['required_if:action,reject', 'nullable', 'string', 'max:500'],
            'items'             => ['nullable', 'array'],
            'items.*.approved_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required_if' => 'سبب الرفض مطلوب عند رفض الطلب.',
        ];
    }
}
