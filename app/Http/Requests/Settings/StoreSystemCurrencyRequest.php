<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('system_currencies.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name_ar' => trim((string) $this->input('name_ar')),
            'name_en' => trim((string) $this->input('name_en')),
            'symbol' => trim((string) $this->input('symbol')),
            'icon' => trim((string) $this->input('icon')),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->input('sort_order', 0),
            'decimal_places' => (int) $this->input('decimal_places', 2),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z0-9]{2,10}$/',
                Rule::unique('currencies', 'code'),
            ],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'symbol' => ['required', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
            'image' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:2048',
            ],
            'decimal_places' => ['required', 'integer', 'between:0,6'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'كود العملة مطلوب.',
            'code.regex' => 'كود العملة يجب أن يحتوي على أحرف إنجليزية كبيرة أو أرقام فقط، مثل USD أو ILS.',
            'code.unique' => 'هذه العملة موجودة مسبقًا.',
            'name_ar.required' => 'اسم العملة بالعربية مطلوب.',
            'symbol.required' => 'رمز العملة مطلوب.',
            'image.image' => 'الملف المرفوع يجب أن يكون صورة.',
            'image.mimes' => 'صيغة الصورة يجب أن تكون PNG أو JPG أو JPEG أو WEBP.',
            'image.max' => 'حجم الصورة يجب ألا يتجاوز 2MB.',
            'decimal_places.between' => 'عدد الخانات العشرية يجب أن يكون بين 0 و6.',
        ];
    }
}
