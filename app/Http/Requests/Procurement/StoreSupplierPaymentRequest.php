<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'supplier_invoice_id' => ['required', 'integer', Rule::exists('supplier_invoices', 'id')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
