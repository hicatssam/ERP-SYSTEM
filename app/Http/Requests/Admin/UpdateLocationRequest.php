<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('location')?->id;
        return [
            'name'    => ['required', 'string', 'max:100'],
            'code'    => ['required', 'string', 'max:20', "unique:locations,code,{$id}"],
            'type'    => ['required', 'in:branch,factory'],
            'phone'   => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
