<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('user')?->id;
        return [
            'username' => ['required', 'string', 'max:50', "unique:users,username,{$id}"],
            'email'    => ['required', 'email', "unique:users,email,{$id}"],
        ];
    }
}
