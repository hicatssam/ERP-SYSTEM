<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockTransferRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.received_quantity'      => ['required', 'numeric', 'min:0'],
            'items.*.damaged_quantity'       => ['nullable', 'numeric', 'min:0'],
            'receiving_notes'                => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                     => 'يجب إدخال الكميات المستلمة.',
            'items.*.received_quantity.required'  => 'الكمية المستلمة مطلوبة لكل صنف.',
            'items.*.received_quantity.min'       => 'الكمية المستلمة يجب ألا تكون سالبة.',
        ];
    }
}
