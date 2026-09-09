<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'username'    => ['required', 'string', 'max:50', 'unique:users,username'],
            'email'       => ['required', 'email', 'unique:users,email'],
            'password'    => ['required', 'string', 'min:8', 'confirmed'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'role'        => ['nullable', 'exists:roles,name'],
        ];
    }
}
