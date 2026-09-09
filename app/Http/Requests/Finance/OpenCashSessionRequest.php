<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:99999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'opening_balance.required' => 'الرصيد الافتتاحي مطلوب.',
            'opening_balance.numeric'  => 'الرصيد الافتتاحي يجب أن يكون رقماً.',
            'opening_balance.min'      => 'الرصيد الافتتاحي يجب ألا يكون سالباً.',
        ];
    }
}
