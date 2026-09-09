<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isAdmin = $this->isSystemAdmin();

        return [
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'unit_id' => ['required', 'exists:units,id'],
            'product_type' => ['required', Rule::enum(ProductType::class)],
            'base_selling_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'tracks_batch' => ['nullable', 'boolean'],
            'tracks_expiry' => ['nullable', 'boolean'],

            // Multi-location
            'location_ids' => [
                Rule::requiredIf($isAdmin),
                'array',
                'min:1',
            ],

            'location_ids.*' => [
                'integer',
                'distinct',
                'exists:locations,id',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048',
            ],

            'remove_image' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'يجب اختيار فئة المنتج.',
            'category_id.exists' => 'الفئة المحددة غير موجودة.',

            'brand_id.exists' => 'العلامة التجارية المحددة غير موجودة.',

            'name.required' => 'يجب إدخال اسم المنتج باللغة الإنجليزية.',
            'name.max' => 'اسم المنتج باللغة الإنجليزية يجب ألا يتجاوز 150 حرفًا.',

            'name_ar.max' => 'اسم المنتج باللغة العربية يجب ألا يتجاوز 150 حرفًا.',

            'unit_id.required' => 'يجب اختيار وحدة القياس.',
            'unit_id.exists' => 'وحدة القياس المحددة غير موجودة.',

            'product_type.required' => 'يجب تحديد نوع المنتج.',
            'product_type.enum' => 'نوع المنتج المحدد غير صحيح.',

            'base_selling_price.required' => 'يجب إدخال السعر الأساسي.',
            'base_selling_price.numeric' => 'السعر الأساسي يجب أن يكون رقمًا.',
            'base_selling_price.min' => 'السعر الأساسي لا يمكن أن يكون أقل من صفر.',

            'location_ids.required' => 'يجب اختيار فرع أو مصنع واحد على الأقل.',
            'location_ids.array' => 'اختيار الفروع غير صحيح.',
            'location_ids.min' => 'يجب اختيار فرع أو مصنع واحد على الأقل.',
            'location_ids.*.integer' => 'أحد الفروع المحددة غير صحيح.',
            'location_ids.*.distinct' => 'لا يمكن اختيار نفس الفرع أكثر من مرة.',
            'location_ids.*.exists' => 'أحد الفروع أو المصانع المحددة غير موجود.',

            'image.image' => 'الملف المحدد يجب أن يكون صورة.',
            'image.mimes' => 'صيغة الصورة يجب أن تكون JPEG أو JPG أو PNG أو WebP.',
            'image.max' => 'حجم صورة المنتج يجب ألا يتجاوز 2 ميجابايت.',
        ];
    }

    private function isSystemAdmin(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if ((bool) ($user->is_admin ?? false)) {
            return true;
        }

        if ($user->can('roles.manage')) {
            return true;
        }

        if (method_exists($user, 'getRoleNames')) {
            if (
                $user->getRoleNames()->contains(
                    fn ($roleName) => $this->isAdminRoleName($roleName)
                )
            ) {
                return true;
            }
        }

        return $this->isAdminRoleName($user->role ?? null);
    }

    private function isAdminRoleName(mixed $role): bool
    {
        if ($role instanceof \BackedEnum) {
            $role = $role->value;
        } elseif ($role instanceof \UnitEnum) {
            $role = $role->name;
        } elseif (is_object($role)) {
            $role = $role->name
                ?? $role->slug
                ?? $role->value
                ?? null;
        }

        $role = Str::lower(trim((string) $role));
        $role = str_replace(['_', ' '], '-', $role);

        return in_array(
            $role,
            [
                'admin',
                'super-admin',
                'administrator',
                'system-admin',
                'system-administrator',
                'مدير-النظام',
                'مسؤول-النظام',
            ],
            true
        );
    }
}