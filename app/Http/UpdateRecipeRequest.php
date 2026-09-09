<?php

namespace App\Http\Requests\Production;

class UpdateRecipeRequest extends StoreRecipeRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recipes.update') ?? false;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['product_id']);

        return $rules;
    }
}
