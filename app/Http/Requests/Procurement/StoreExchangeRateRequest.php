<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'effective_date' => ['required', 'date'],
            'rate_to_base' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
