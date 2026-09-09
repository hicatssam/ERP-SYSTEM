<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'actual_cash'  => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'actual_cash.required' => 'المبلغ الفعلي في الصندوق مطلوب.',
            'actual_cash.numeric'  => 'المبلغ الفعلي يجب أن يكون رقماً.',
            'actual_cash.min'      => 'المبلغ الفعلي يجب ألا يكون سالباً.',
        ];
    }
}
