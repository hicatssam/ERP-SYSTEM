<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockCountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'location_id' => ['required', 'exists:locations,id'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ];
    }
}
