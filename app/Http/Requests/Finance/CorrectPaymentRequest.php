<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class CorrectPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'corrected_amount' => ['required', 'numeric', 'min:0.01'],
            'reason'           => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
