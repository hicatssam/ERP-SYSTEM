<?php

namespace App\Http\Requests\Procurement;

use App\Enums\SupplierStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_code' => ['nullable', 'string', 'max:50', Rule::unique('suppliers', 'supplier_code')],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'commercial_registration' => ['nullable', 'string', 'max:100'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'opening_balance' => ['nullable', 'numeric'],
            'status' => ['nullable', Rule::enum(SupplierStatus::class)],
            'notes' => ['nullable', 'string'],
            'contacts' => ['nullable', 'array', 'max:20'],
            'contacts.*.name' => ['nullable', 'string', 'max:255'],
            'contacts.*.position' => ['nullable', 'string', 'max:100'],
            'contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'contacts.*.whatsapp' => ['nullable', 'string', 'max:30'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
            'contacts.*.notes' => ['nullable', 'string'],
            'products' => ['nullable', 'array', 'max:500'],
            'products.*.product_id' => ['nullable', 'integer', 'distinct', Rule::exists('products', 'id')],
            'products.*.supplier_sku' => ['nullable', 'string', 'max:100'],
            'products.*.supplier_product_name' => ['nullable', 'string', 'max:255'],
            'products.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'products.*.purchase_unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')],
            'products.*.conversion_factor' => ['nullable', 'numeric', 'gt:0', 'max:999999999.999999'],
            'products.*.package_description' => ['nullable', 'string', 'max:255'],
            'products.*.minimum_order_quantity' => ['nullable', 'numeric', 'gt:0'],
            'products.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'products.*.is_preferred' => ['nullable', 'boolean'],
            'products.*.is_active' => ['nullable', 'boolean'],
            'products.*.notes' => ['nullable', 'string'],
        ];
    }
}
