<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recipes.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
            'product_variant_id' => [
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],
            'name' => [
                'required',
                'string',
                'max:180',
            ],
            'yield_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999.999',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],
            'items.*.ingredient_product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999.999',
            ],
            'items.*.expected_waste_percent' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'items.*.stage' => [
                'nullable',
                'string',
                'max:120',
            ],
            'items.*.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
