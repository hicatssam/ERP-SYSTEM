<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class CloseFinancialPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('financial.periods.close');
    }

    public function rules(): array
    {
        return [
            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}
