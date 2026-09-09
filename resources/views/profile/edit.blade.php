@extends('layouts.app')

@section('title', 'الملف الشخصي')
@section('page-title', 'الملف الشخصي')

@section('content')
@php
    $currentUser = $user ?? auth()->user();

    $profileImage = $currentUser->employee?->profile_image
        ?? $currentUser->profile_image;

    $displayName = $currentUser->employee?->full_name
        ?? $currentUser->username;
@endphp

<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">الملف الشخصي</h1>

        <p class="page-subheading">
            عرض بياناتك وتحديث الصورة الشخصية وكلمة المرور
        </p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1.25rem">
        <p>{{ session('success') }}</p>
    </div>
@endif

@if(session('info'))
    <div class="alert alert-info" style="margin-bottom:1.25rem">
        <p>{{ session('info') }}</p>
    </div>
@endif

<div class="dashboard-row">

    {{-- بطاقة الملف الشخصي --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>

                بياناتي الشخصية
            </span>
        </div>

        <div class="card-body">

            {{-- الصورة والبيانات المختصرة --}}
            <div
                style="
                    display:flex;
                    align-items:center;
                    gap:1rem;
                    margin-bottom:1.5rem;
                    padding-bottom:1.5rem;
                    border-bottom:1px solid var(--border);
                "
            >
                <div style="position:relative;flex-shrink:0">
                    @if($profileImage)
                        <img
                            id="profileImagePreview"
                            src="{{ asset('storage/' . $profileImage) }}"
                            alt="{{ $displayName }}"
                            width="88"
                            height="88"
                            style="
                                width:88px;
                                height:88px;
                                display:block;
                                object-fit:cover;
                                border-radius:50%;
                                border:3px solid var(--gold);
                                background:var(--surface);
                            "
                        >
                    @else
                        <div
                            id="profileImagePlaceholder"
                            style="
                                width:88px;
                                height:88px;
                                border-radius:50%;
                                background:linear-gradient(
                                    135deg,
                                    var(--gold),
                                    var(--gold-deep)
                                );
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                font-size:2rem;
                                font-weight:800;
                                color:#fff;
                            "
                        >
                            {{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}
                        </div>

                        <img
                            id="profileImagePreview"
                            src=""
                            alt="معاينة الصورة"
                            width="88"
                            height="88"
                            style="
                                display:none;
                                width:88px;
                                height:88px;
                                object-fit:cover;
                                border-radius:50%;
                                border:3px solid var(--gold);
                            "
                        >
                    @endif
                </div>

                <div>
                    <div
                        style="
                            font-size:1.05rem;
                            font-weight:800;
                            color:var(--text);
                        "
                    >
                        {{ $displayName }}
                    </div>

                    <div
                        style="
                            font-size:.82rem;
                            color:var(--text-muted);
                            margin-top:.2rem;
                        "
                    >
                        {{ $currentUser->email }}
                    </div>

                    <div style="margin-top:.5rem">
                        @forelse($currentUser->roles as $role)
                            <span class="badge badge-gold">
                                {{ $role->name }}
                            </span>
                        @empty
                            <span class="badge badge-grey">
                                بدون دور
                            </span>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- نموذج تعديل الصورة فقط --}}
            <form
                action="{{ route('profile.update') }}"
                method="POST"
                enctype="multipart/form-data"
                style="
                    margin-bottom:1.5rem;
                    padding-bottom:1.5rem;
                    border-bottom:1px solid var(--border);
                "
            >
                @csrf
                @method('PUT')

                <div style="display:grid;gap:1rem">
                    <div class="form-group">
                        <label class="form-label" for="profileImageInput">
                            تغيير الصورة الشخصية
                        </label>

                        <input
                            id="profileImageInput"
                            type="file"
                            name="profile_image"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            class="form-input @error('profile_image') is-invalid @enderror"
                        >

                        <small
                            style="
                                display:block;
                                margin-top:.5rem;
                                color:var(--text-muted);
                            "
                        >
                            الصيغ المسموحة: JPG وPNG وWEBP، والحد الأقصى 2MB.
                        </small>

                        @error('profile_image')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    @if($profileImage)
                        <label
                            style="
                                display:flex;
                                align-items:center;
                                gap:.5rem;
                                width:fit-content;
                                color:var(--error);
                                cursor:pointer;
                                font-size:.875rem;
                            "
                        >
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

                    <button
                        type="submit"
                        class="btn btn-gold"
                        style="width:fit-content"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>

                        حفظ الصورة
                    </button>
                </div>
            </form>

            {{-- البيانات للعرض فقط --}}
            <div class="detail-list">
                <div class="detail-row">
                    <span class="detail-label">اسم المستخدم</span>
                    <span class="detail-value">
                        {{ $currentUser->username }}
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">البريد الإلكتروني</span>
                    <span class="detail-value">
                        {{ $currentUser->email }}
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">الاسم الكامل</span>
                    <span class="detail-value">
                        {{ $currentUser->employee?->full_name ?? '—' }}
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">المسمى الوظيفي</span>
                    <span class="detail-value">
                        {{ $currentUser->employee?->job_title ?? '—' }}
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">رقم الجوال</span>
                    <span class="detail-value">
                        {{ $currentUser->employee?->phone ?? '—' }}
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">الموقع الرئيسي</span>
                    <span class="detail-value">
                        {{ $currentUser->primaryLocation()?->name ?? '—' }}
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">الدور الوظيفي</span>
                    <span class="detail-value">
                        {{ $currentUser->roles->pluck('name')->join(', ') ?: '—' }}
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">آخر تسجيل دخول</span>
                    <span class="detail-value">
                        {{ $currentUser->last_login_at?->format('Y/m/d H:i') ?? '—' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- بطاقة تغيير كلمة المرور --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>

                تغيير كلمة المرور
            </span>
        </div>

        <div class="card-body">
            @if(session('password_success'))
                <div class="alert alert-success" style="margin-bottom:1.25rem">
                    <p>{{ session('password_success') }}</p>
                </div>
            @endif

            <form
                action="{{ route('password.change') }}"
                method="POST"
            >
                @csrf

                <div style="display:grid;gap:1.25rem">
                    <div class="form-group">
                        <label class="form-label" for="current_password">
                            كلمة المرور الحالية
                            <span style="color:var(--error)">*</span>
                        </label>

                        <div class="input-with-toggle">
                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                class="form-input @error('current_password') is-invalid @enderror"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                type="button"
                                class="input-toggle-btn"
                                onclick="toggleField('current_password')"
                                tabindex="-1"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>

                        @error('current_password')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">
                            كلمة المرور الجديدة
                            <span style="color:var(--error)">*</span>
                        </label>

                        <div class="input-with-toggle">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-input @error('password') is-invalid @enderror"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="input-toggle-btn"
                                onclick="toggleField('password')"
                                tabindex="-1"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>

                        @error('password')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label
                            class="form-label"
                            for="password_confirmation"
                        >
                            تأكيد كلمة المرور الجديدة
                            <span style="color:var(--error)">*</span>
                        </label>

                        <div class="input-with-toggle">
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                class="form-input"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="input-toggle-btn"
                                onclick="toggleField('password_confirmation')"
                                tabindex="-1"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="password-rules">
                        <strong>متطلبات كلمة المرور:</strong><br>
                        10 أحرف على الأقل · حرف كبير واحد على الأقل ·
                        رقم واحد على الأقل · رمز خاص واحد على الأقل
                    </div>

                    <button
                        class="btn btn-gold"
                        type="submit"
                        style="width:fit-content"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>

                        حفظ كلمة المرور
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleField(id) {
    const input = document.getElementById(id);

    if (input) {
        input.type = input.type === 'password' ? 'text' : 'password';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('profileImageInput');
    const preview = document.getElementById('profileImagePreview');
    const placeholder = document.getElementById('profileImagePlaceholder');
    const removeCheckbox = document.getElementById('removeProfileImage');

    if (input && preview) {
        input.addEventListener('change', function (event) {
            const file = event.target.files[0];

            if (!file) {
                return;
            }

            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (!allowedTypes.includes(file.type)) {
                alert('يرجى اختيار صورة بصيغة JPG أو PNG أو WEBP.');

                input.value = '';

                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                alert('حجم الصورة يجب ألا يتجاوز 2MB.');

                input.value = '';

                return;
            }

            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
            preview.style.opacity = '1';

            if (placeholder) {
                placeholder.style.display = 'none';
            }

            if (removeCheckbox) {
                removeCheckbox.checked = false;
            }
        });
    }

    if (removeCheckbox && preview) {
        removeCheckbox.addEventListener('change', function () {
            preview.style.opacity = this.checked ? '.3' : '1';

            if (this.checked && input) {
                input.value = '';
            }
        });
    }
});
</script>
@endpush