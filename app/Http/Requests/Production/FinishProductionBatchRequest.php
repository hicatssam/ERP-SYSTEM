<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class FinishProductionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('production.finish') ?? false;
    }

    public function rules(): array
    {
        return [
            'actual_output_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999.999',
            ],
            'output_expiry_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.actual_consumed_quantity' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999.999',
            ],
            'items.*.waste_quantity' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.999',
            ],
        ];
    }
}
