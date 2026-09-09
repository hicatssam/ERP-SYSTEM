@extends('layouts.app')

@section('title', 'إضافة موظف')

@section('page-title', 'إضافة موظف')

@section('content')

<div class="page-header">

    <h1 class="page-heading">إضافة موظف جديد</h1>

    <p class="page-subheading">

        <a href="{{ route('employees.index') }}">الموظفون</a>

        &laquo; إضافة

    </p>

</div>

<div class="card" style="max-width:750px">

    <div class="card-body">

        <form

            action="{{ route('employees.store') }}"

            method="POST"

            enctype="multipart/form-data"

        >

            @csrf

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

                            <div

                                id="employeeImagePlaceholder"

                                class="employee-image-placeholder"

                            >

                                <svg

                                    viewBox="0 0 24 24"

                                    fill="none"

                                    stroke="currentColor"

                                    stroke-width="1.7"

                                >

                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8

                                        a4 4 0 0 0-4 4v2"/>

                                    <circle cx="12" cy="7" r="4"/>

                                </svg>

                            </div>

                            <img

                                id="employeeImagePreview"

                                src=""

                                alt="معاينة صورة الموظف"

                                class="employee-image-preview"

                            >

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

                                اختيار صورة

                            </label>

                            <input

                                id="profileImageInput"

                                type="file"

                                name="profile_image"

                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"

                                class="@error('profile_image') is-invalid @enderror"

                                hidden

                            >

                            <button

                                id="removeSelectedImage"

                                type="button"

                                class="btn btn-ghost btn-sm"

                                style="display:none;color:var(--error)"

                            >

                                إزالة الصورة

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

                        </div>

                    </div>

                </div>

                {{-- الاسم ورقم الموظف --}}

                <div class="employee-form-grid">

                    <div class="form-group">

                        <label class="form-label">

                            الاسم الكامل *

                        </label>

                        <input

                            name="full_name"

                            class="form-input @error('full_name') is-invalid @enderror"

                            value="{{ old('full_name') }}"

                            required

                        >

                        @error('full_name')

                            <span class="form-error">

                                {{ $message }}

                            </span>

                        @enderror

                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            رقم الموظف
                        </label>

                        <input
                            id="employeeNumberPreview"
                            class="form-input employee-number-readonly"
                            value="{{ $nextEmployeeNumber }}"
                            readonly
                            disabled
                        >

                        <small class="employee-number-help">
                            يُنشأ رقم الموظف تلقائيًا عند الحفظ حسب آخر رقم موجود في النظام.
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

                            value="{{ old('phone') }}"

                        >

                        @error('phone')

                            <span class="form-error">

                                {{ $message }}

                            </span>

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

                            value="{{ old('email') }}"

                        >

                        @error('email')

                            <span class="form-error">

                                {{ $message }}

                            </span>

                        @enderror

                    </div>

                </div>

                {{-- الوظيفة والتاريخ --}}

                <div class="employee-form-grid">

                    <div class="form-group">

                        <label class="form-label">

                            المسمى الوظيفي

                        </label>

                        <input

                            name="job_title"

                            class="form-input @error('job_title') is-invalid @enderror"

                            value="{{ old('job_title') }}"

                        >

                        @error('job_title')

                            <span class="form-error">

                                {{ $message }}

                            </span>

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

                            value="{{ old('hire_date') }}"

                        >

                        @error('hire_date')

                            <span class="form-error">

                                {{ $message }}

                            </span>

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

                            <option

                                value="active"

                                {{ old('employment_status', 'active') === 'active' ? 'selected' : '' }}

                            >

                                نشط

                            </option>

                            <option

                                value="inactive"

                                {{ old('employment_status') === 'inactive' ? 'selected' : '' }}

                            >

                                غير نشط

                            </option>

                            <option

                                value="terminated"

                                {{ old('employment_status') === 'terminated' ? 'selected' : '' }}

                            >

                                منتهي الخدمة

                            </option>

                        </select>

                        @error('employment_status')

                            <span class="form-error">

                                {{ $message }}

                            </span>

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

                            <option value="">اختر موقعًا</option>

                            @foreach($locations as $loc)

                                <option

                                    value="{{ $loc->id }}"

                                    {{ (string) old('primary_location_id') === (string) $loc->id ? 'selected' : '' }}

                                >

                                    {{ $loc->name }}

                                </option>

                            @endforeach

                        </select>

                        @error('primary_location_id')

                            <span class="form-error">

                                {{ $message }}

                            </span>

                        @enderror

                    </div>

                </div>

                <div style="display:flex;gap:.75rem;flex-wrap:wrap">

                    <button class="btn btn-gold" type="submit">

                        حفظ الموظف

                    </button>

                    <a

                        href="{{ route('employees.index') }}"

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

    width: 115px;

    height: 115px;

    flex-shrink: 0;

}

.employee-image-placeholder,

.employee-image-preview {

    width: 115px;

    height: 115px;

    border-radius: 50%;

    border: 3px solid var(--gold);

}

.employee-image-placeholder {

    display: flex;

    align-items: center;

    justify-content: center;

    background: linear-gradient(

        135deg,

        rgba(212, 175, 55, .16),

        rgba(212, 175, 55, .05)

    );

    color: var(--gold);

}

.employee-image-placeholder svg {

    width: 48px;

    height: 48px;

}

.employee-image-preview {

    display: none;

    object-fit: cover;

    background: var(--surface);

}

.employee-image-controls {

    display: grid;

    align-items: start;

    gap: .6rem;

}

.employee-image-help {

    color: var(--text-muted);

    font-size: .75rem;

}

.employee-image-name {

    max-width: 350px;

    color: var(--text-muted);

    font-size: .72rem;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;

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

    const removeButton = document.getElementById('removeSelectedImage');

    const fileName = document.getElementById('selectedImageName');

    if (!input || !preview) {

        return;

    }

    input.addEventListener('change', function () {

        const file = input.files[0];

        if (!file) {

            resetImage();

            return;

        }

        const allowedTypes = [

            'image/jpeg',

            'image/png',

            'image/webp'

        ];

        if (!allowedTypes.includes(file.type)) {

            alert('يرجى اختيار صورة بصيغة JPG أو PNG أو WEBP.');

            resetImage();

            return;

        }

        if (file.size > 2 * 1024 * 1024) {

            alert('حجم الصورة يجب ألا يتجاوز 2MB.');

            resetImage();

            return;

        }

        preview.src = URL.createObjectURL(file);

        preview.style.display = 'block';

        placeholder.style.display = 'none';

        removeButton.style.display = 'inline-flex';

        fileName.textContent = file.name;

    });

    removeButton.addEventListener('click', resetImage);

    function resetImage() {

        input.value = '';

        preview.src = '';

        preview.style.display = 'none';

        placeholder.style.display = 'flex';

        removeButton.style.display = 'none';

        fileName.textContent = '';

    }

});

</script>

@endpush