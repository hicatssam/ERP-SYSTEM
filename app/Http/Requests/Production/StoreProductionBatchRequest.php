<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('production.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'recipe_id' => [
                'required',
                'integer',
                'exists:recipes,id',
            ],
            'location_id' => [
                'required',
                'integer',
                'exists:locations,id',
            ],
            'planned_output_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999.999',
            ],
            'planned_date' => [
                'nullable',
                'date',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
