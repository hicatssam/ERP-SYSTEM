<?php

namespace App\Http\Requests\Admin;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Employee|null $employee */
        $employee = $this->route('employee');

        return [
            /*
            |--------------------------------------------------------------------------
            | employee_number
            |--------------------------------------------------------------------------
            | رقم الموظف ثابت ولا يتم قبوله من نموذج التعديل.
            */

            'full_name' => [
                'required',
                'string',
                'max:100',
            ],

            'profile_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'remove_profile_image' => [
                'nullable',
                'boolean',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'email')
                    ->ignore($employee?->id),
            ],

            'job_title' => [
                'nullable',
                'string',
                'max:100',
            ],

            'hire_date' => [
                'nullable',
                'date',
            ],

            'employment_status' => [
                'required',
                'in:active,inactive,terminated',
            ],

            'primary_location_id' => [
                'nullable',
                'integer',
                'exists:locations,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' =>
                'الاسم الكامل مطلوب.',

            'profile_image.image' =>
                'يجب أن تكون الصورة الشخصية ملف صورة صالحًا.',

            'profile_image.mimes' =>
                'الصورة الشخصية يجب أن تكون بصيغة JPG أو JPEG أو PNG أو WEBP.',

            'profile_image.max' =>
                'حجم الصورة الشخصية يجب ألا يتجاوز 2 ميجابايت.',

            'email.email' =>
                'صيغة البريد الإلكتروني غير صحيحة.',

            'email.unique' =>
                'البريد الإلكتروني مستخدم لموظف آخر.',

            'employment_status.required' =>
                'حالة التوظيف مطلوبة.',

            'employment_status.in' =>
                'حالة التوظيف المحددة غير صحيحة.',

            'primary_location_id.exists' =>
                'الموقع الأساسي المحدد غير موجود.',
        ];
    }
}