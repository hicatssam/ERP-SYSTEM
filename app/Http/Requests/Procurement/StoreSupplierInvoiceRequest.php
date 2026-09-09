<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_number' => ['required', 'string', 'max:80', Rule::unique('supplier_invoices', 'invoice_number')],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')],
            'goods_receipt_id' => ['nullable', 'integer', Rule::exists('goods_receipts', 'id')],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required_without_all:purchase_order_id,goods_receipt_id', 'nullable', 'array', 'min:1', 'max:500'],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
