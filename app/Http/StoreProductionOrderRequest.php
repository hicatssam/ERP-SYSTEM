<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('production.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'recipe_id' => ['required', 'integer', 'exists:recipes,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'planned_output_quantity' => ['required', 'numeric', 'gt:0'],
            'planned_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
