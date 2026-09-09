<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goods_receipt_id' => ['required', 'integer', Rule::exists('goods_receipts', 'id')],
            'supplier_invoice_id' => ['nullable', 'integer', Rule::exists('supplier_invoices', 'id')],
            'returned_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.goods_receipt_item_id' => ['required', 'integer', 'distinct', Rule::exists('goods_receipt_items', 'id')],
            'items.*.inventory_batch_id' => ['nullable', 'integer', Rule::exists('inventory_batches', 'id')],
            'items.*.return_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:100'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
