<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockCountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items'                       => ['nullable', 'array'],
            'items.*.counted_quantity'    => ['nullable', 'numeric', 'min:0'],
            'items.*.notes'               => ['nullable', 'string', 'max:255'],
            'notes'                       => ['nullable', 'string', 'max:500'],
        ];
    }
}
