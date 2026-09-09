<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('financial.periods.open');
    }

    public function rules(): array
    {
        return [
            'year' => [
                'required',
                'integer',
                'between:2020,2100',
            ],
            'month' => [
                'required',
                'integer',
                'between:1,12',
            ],
            'opening_balance' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'year.required' => 'السنة مطلوبة.',
            'year.between' => 'السنة يجب أن تكون بين 2020 و2100.',
            'month.required' => 'الشهر مطلوب.',
            'month.between' => 'الشهر يجب أن يكون بين 1 و12.',
            'opening_balance.numeric' => 'الرصيد الافتتاحي يجب أن يكون رقماً.',
            'opening_balance.min' => 'الرصيد الافتتاحي لا يمكن أن يكون سالباً.',
        ];
    }
}
