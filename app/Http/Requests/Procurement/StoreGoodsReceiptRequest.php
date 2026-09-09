<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', Rule::exists('purchase_orders', 'id')],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct', Rule::exists('purchase_order_items', 'id')],
            'items.*.received_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.accepted_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.rejected_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.manufacturing_date' => ['nullable', 'date'],
            'items.*.expiry_date' => ['nullable', 'date', 'after_or_equal:items.*.manufacturing_date'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
