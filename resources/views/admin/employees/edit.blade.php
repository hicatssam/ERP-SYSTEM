@extends('layouts.app')

@section('title', 'تعديل موظف')

@section('page-title', 'تعديل موظف')

@section('content')

@php

    $employmentStatus = $employee->employment_status instanceof \BackedEnum

        ? $employee->employment_status->value

        : $employee->employment_status;

@endphp

<div class="page-header">

    <h1 class="page-heading">

        تعديل: {{ $employee->full_name }}

    </h1>

    <p class="page-subheading">

        <a href="{{ route('employees.index') }}">الموظفون</a>

        &laquo;

        <a href="{{ route('employees.show', $employee) }}">

            {{ $employee->full_name }}

        </a>

        &laquo; تعديل

    </p>

</div>

<div class="card" style="max-width:750px">

    <div class="card-body">

        <form

            action="{{ route('employees.update', $employee) }}"

            method="POST"

            enctype="multipart/form-data"

        >

            @csrf

            @method('PUT')

            <div style="display:grid;gap:1.25rem">

                {{-- الصورة الشخصية --}}

                <div class="form-group">

                    <label class="form-label">

                        الصورة الشخصية

                        <span style="color:var(--text-muted);font-weight:400">

                            (اختيارية)

                        </span>

                    </label>

                    <div class="employee-image-uploader">

                        <div class="employee-image-preview-wrap">

                            @if($employee->profile_image)

                                <img

                                    id="employeeImagePreview"

                                    src="{{ asset('storage/' . $employee->profile_image) }}"

                                    alt="{{ $employee->full_name }}"

                                    class="employee-image-preview"

                                    style="display:block"

                                >

                                <div

                                    id="employeeImagePlaceholder"

                                    class="employee-image-placeholder"

                                    style="display:none"

                                >

                                    {{ mb_strtoupper(mb_substr($employee->full_name, 0, 1)) }}

                                </div>

                            @else

                                <img

                                    id="employeeImagePreview"

                                    src=""

                                    alt="معاينة صورة الموظف"

                                    class="employee-image-preview"

                                >

                                <div

                                    id="employeeImagePlaceholder"

                                    class="employee-image-placeholder"

                                >

                                    {{ mb_strtoupper(mb_substr($employee->full_name, 0, 1)) }}

                                </div>

                            @endif

                        </div>

                        <div class="employee-image-controls">

                            <label

                                for="profileImageInput"

                                class="btn btn-outline btn-sm"

                                style="width:fit-content;cursor:pointer"

                            >

                                <svg

                                    viewBox="0 0 24 24"

                                    fill="none"

                                    stroke="currentColor"

                                    stroke-width="2"

                                >

                                    <rect x="3" y="3" width="18" height="18" rx="2"/>

                                    <circle cx="8.5" cy="8.5" r="1.5"/>

                                    <polyline points="21 15 16 10 5 21"/>

                                </svg>

                                {{ $employee->profile_image ? 'تغيير الصورة' : 'اختيار صورة' }}

                            </label>

                            <input

                                id="profileImageInput"

                                type="file"

                                name="profile_image"

                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"

                                hidden

                            >

                            <button

                                id="cancelNewImage"

                                type="button"

                                class="btn btn-ghost btn-sm"

                                style="display:none;width:fit-content"

                            >

                                إلغاء الصورة الجديدة

                            </button>

                            <small class="employee-image-help">

                                JPG أو PNG أو WEBP، بحد أقصى 2MB.

                            </small>

                            <div

                                id="selectedImageName"

                                class="employee-image-name"

                            ></div>

                            @error('profile_image')

                                <span class="form-error">

                                    {{ $message }}

                                </span>

                            @enderror

                            @if($employee->profile_image)

                                <label class="remove-image-option">

                                    <input

                                        id="removeProfileImage"

                                        type="checkbox"

                                        name="remove_profile_image"

                                        value="1"

                                        {{ old('remove_profile_image') ? 'checked' : '' }}

                                    >

                                    حذف الصورة الشخصية الحالية

                                </label>

                            @endif

                        </div>

                    </div>

                </div>

                {{-- الاسم ورقم الموظف --}}

                <div class="employee-form-grid">

                    <div class="form-group">

                        <label class="form-label">الاسم الكامل *</label>

                        <input

                            name="full_name"

                            class="form-input @error('full_name') is-invalid @enderror"

                            value="{{ old('full_name', $employee->full_name) }}"

                            required

                        >

                        @error('full_name')

                            <span class="form-error">{{ $message }}</span>

                        @enderror

                    </div>

                    <div class="form-group">
                        <label class="form-label">رقم الموظف</label>

                        <input
                            class="form-input employee-number-readonly"
                            value="{{ $employee->employee_number }}"
                            readonly
                            disabled
                        >

                        <small class="employee-number-help">
                            رقم الموظف ثابت ولا يمكن تغييره بعد إنشاء السجل.
                        </small>
                    </div>

                </div>

                {{-- الهاتف والبريد --}}

                <div class="employee-form-grid">

                    <div class="form-group">

                        <label class="form-label">الهاتف</label>

                        <input

                            name="phone"

                            type="tel"

                            class="form-input @error('phone') is-invalid @enderror"

                            value="{{ old('phone', $employee->phone) }}"

                        >

                        @error('phone')

                            <span class="form-error">{{ $message }}</span>

                        @enderror

                    </div>

                    <div class="form-group">

                        <label class="form-label">

                            البريد الإلكتروني

                        </label>

                        <input

                            name="email"

                            type="email"

                            class="form-input @error('email') is-invalid @enderror"

                            value="{{ old('email', $employee->email) }}"

                        >

                        @error('email')

                            <span class="form-error">{{ $message }}</span>

                        @enderror

                    </div>

                </div>

                {{-- الوظيفة وتاريخ التعيين --}}

                <div class="employee-form-grid">

                    <div class="form-group">

                        <label class="form-label">

                            المسمى الوظيفي

                        </label>

                        <input

                            name="job_title"

                            class="form-input @error('job_title') is-invalid @enderror"

                            value="{{ old('job_title', $employee->job_title) }}"

                        >

                        @error('job_title')

                            <span class="form-error">{{ $message }}</span>

                        @enderror

                    </div>

                    <div class="form-group">

                        <label class="form-label">

                            تاريخ التعيين

                        </label>

                        <input

                            name="hire_date"

                            type="date"

                            class="form-input @error('hire_date') is-invalid @enderror"

                            value="{{ old('hire_date', $employee->hire_date?->format('Y-m-d')) }}"

                        >

                        @error('hire_date')

                            <span class="form-error">{{ $message }}</span>

                        @enderror

                    </div>

                </div>

                {{-- الحالة والموقع --}}

                <div class="employee-form-grid">

                    <div class="form-group">

                        <label class="form-label">

                            حالة التوظيف *

                        </label>

                        <select

                            name="employment_status"

                            class="form-select @error('employment_status') is-invalid @enderror"

                            required

                        >

                            @foreach([

                                'active' => 'نشط',

                                'inactive' => 'غير نشط',

                                'terminated' => 'منتهي الخدمة'

                            ] as $value => $label)

                                <option

                                    value="{{ $value }}"

                                    {{ old('employment_status', $employmentStatus) === $value ? 'selected' : '' }}

                                >

                                    {{ $label }}

                                </option>

                            @endforeach

                        </select>

                        @error('employment_status')

                            <span class="form-error">{{ $message }}</span>

                        @enderror

                    </div>

                    <div class="form-group">

                        <label class="form-label">

                            الموقع الأساسي

                        </label>

                        <select

                            name="primary_location_id"

                            class="form-select @error('primary_location_id') is-invalid @enderror"

                        >

                            <option value="">بدون موقع أساسي</option>

                            @foreach($locations as $location)

                                <option

                                    value="{{ $location->id }}"

                                    {{ (string) old(

                                        'primary_location_id',

                                        $primaryLocationId ?? ''

                                    ) === (string) $location->id ? 'selected' : '' }}

                                >

                                    {{ $location->name }}

                                </option>

                            @endforeach

                        </select>

                        @error('primary_location_id')

                            <span class="form-error">{{ $message }}</span>

                        @enderror

                    </div>

                </div>

                <div style="display:flex;gap:.75rem;flex-wrap:wrap">

                    <button class="btn btn-gold" type="submit">

                        حفظ التغييرات

                    </button>

                    <a

                        href="{{ route('employees.show', $employee) }}"

                        class="btn btn-ghost"

                    >

                        إلغاء

                    </a>

                </div>

            </div>

        </form>

    </div>

</div>

@endsection

@push('styles')

<style>

.employee-form-grid {

    display: grid;

    grid-template-columns: repeat(2, minmax(0, 1fr));

    gap: 1rem;

}

.employee-image-uploader {

    display: flex;

    align-items: center;

    gap: 1.25rem;

    padding: 1.25rem;

    border: 1px dashed var(--border);

    border-radius: 14px;

    background: rgba(255, 255, 255, .02);

}

.employee-image-preview-wrap {

    width: 120px;

    height: 120px;

    flex-shrink: 0;

}

.employee-image-preview,

.employee-image-placeholder {

    width: 120px;

    height: 120px;

    border-radius: 50%;

    border: 3px solid var(--gold);

}

.employee-image-preview {

    display: none;

    object-fit: cover;

    background: var(--surface);

}

.employee-image-placeholder {

    display: flex;

    align-items: center;

    justify-content: center;

    color: #fff;

    background: linear-gradient(

        135deg,

        var(--gold),

        var(--gold-deep)

    );

    font-size: 2rem;

    font-weight: 800;

}

.employee-image-controls {

    display: grid;

    align-items: start;

    gap: .65rem;

}

.employee-image-help,

.employee-image-name {

    color: var(--text-muted);

    font-size: .75rem;

}

.employee-image-name {

    max-width: 350px;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.remove-image-option {

    width: fit-content;

    display: flex;

    align-items: center;

    gap: .5rem;

    color: var(--error);

    font-size: .8rem;

    cursor: pointer;

}


.employee-number-readonly {
    direction: ltr;
    text-align: center;
    font-weight: 800;
    letter-spacing: .06em;
    color: var(--gold);
    background: rgba(212, 175, 55, .06);
    cursor: not-allowed;
}

.employee-number-help {
    display: block;
    margin-top: .45rem;
    color: var(--text-muted);
    font-size: .75rem;
}

@media (max-width: 650px) {

    .employee-form-grid {

        grid-template-columns: 1fr;

    }

    .employee-image-uploader {

        align-items: flex-start;

        flex-direction: column;

    }

}

</style>

@endpush

@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    const input = document.getElementById('profileImageInput');

    const preview = document.getElementById('employeeImagePreview');

    const placeholder = document.getElementById('employeeImagePlaceholder');

    const cancelButton = document.getElementById('cancelNewImage');

    const removeCheckbox = document.getElementById('removeProfileImage');

    const fileName = document.getElementById('selectedImageName');

    const originalImage = @json(

        $employee->profile_image

            ? asset('storage/' . $employee->profile_image)

            : null

    );

    if (!input || !preview || !placeholder) {

        return;

    }

    input.addEventListener('change', function () {

        const file = input.files[0];

        if (!file) {

            restoreOriginalImage();

            return;

        }

        const allowedTypes = [

            'image/jpeg',

            'image/png',

            'image/webp'

        ];

        if (!allowedTypes.includes(file.type)) {

            alert('يرجى اختيار صورة بصيغة JPG أو PNG أو WEBP.');

            restoreOriginalImage();

            return;

        }

        if (file.size > 2 * 1024 * 1024) {

            alert('حجم الصورة يجب ألا يتجاوز 2MB.');

            restoreOriginalImage();

            return;

        }

        preview.src = URL.createObjectURL(file);

        preview.style.display = 'block';

        preview.style.opacity = '1';

        placeholder.style.display = 'none';

        cancelButton.style.display = 'inline-flex';

        fileName.textContent = file.name;

        if (removeCheckbox) {

            removeCheckbox.checked = false;

        }

    });

    cancelButton.addEventListener('click', restoreOriginalImage);

    if (removeCheckbox) {

        removeCheckbox.addEventListener('change', function () {

            if (this.checked) {

                input.value = '';

                preview.style.opacity = '.25';

                cancelButton.style.display = 'none';

                fileName.textContent = '';

            } else {

                restoreOriginalImage(false);

            }

        });

    }

    function restoreOriginalImage(resetCheckbox = true) {

        input.value = '';

        cancelButton.style.display = 'none';

        fileName.textContent = '';

        preview.style.opacity = '1';

        if (resetCheckbox && removeCheckbox) {

            removeCheckbox.checked = false;

        }

        if (originalImage) {

            preview.src = originalImage;

            preview.style.display = 'block';

            placeholder.style.display = 'none';

        } else {

            preview.src = '';

            preview.style.display = 'none';

            placeholder.style.display = 'flex';

        }

    }

});

</script>

@endpush