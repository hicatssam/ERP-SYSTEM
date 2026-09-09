<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('production.complete') ?? false;
    }

    public function rules(): array
    {
        return [
            'actual_output_quantity' => ['required', 'numeric', 'gt:0'],
            'materials' => ['required', 'array', 'min:1'],
            'materials.*.item_id' => ['required', 'integer', 'exists:production_order_items,id'],
            'materials.*.actual_quantity' => ['required', 'numeric', 'min:0'],
            'materials.*.waste_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
