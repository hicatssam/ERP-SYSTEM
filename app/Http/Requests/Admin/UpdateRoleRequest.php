<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('role')?->id;
        return [
            'name'          => ['required', 'string', 'max:50', "unique:roles,name,{$id}"],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ];
    }
}
