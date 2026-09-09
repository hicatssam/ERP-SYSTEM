<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('expenses.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'payee' => ['nullable', 'string', 'max:180'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.gt' => 'قيمة المصروف يجب أن تكون أكبر من صفر.',
            'expense_date.before_or_equal' => 'لا يمكن تسجيل مصروف بتاريخ مستقبلي.',
            'description.required' => 'وصف المصروف مطلوب لأغراض التدقيق.',
        ];
    }
}
