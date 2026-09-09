<?php

namespace App\Http\Requests\Procurement;

use App\Enums\SupplierStatus;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends StoreSupplierRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['supplier_code'] = ['prohibited'];
        $rules['status'] = ['required', Rule::enum(SupplierStatus::class)];

        return $rules;
    }
}
