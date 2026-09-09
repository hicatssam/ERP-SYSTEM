@extends('layouts.app')

@section('title', 'تعديل مستخدم')

@section('content')
<div class="page-header">
    <h1 class="page-heading">تعديل: {{ $user->username }}</h1>

    <p class="page-subheading">
        <a href="{{ route('users.index') }}">المستخدمون</a>
        &laquo; تعديل
    </p>
</div>

<div class="card" style="max-width:600px">
    <div class="card-body">

        <form
            action="{{ route('users.update', $user) }}"
            method="POST"
            enctype="multipart/form-data"
        >
            @csrf
            @method('PUT')

            <div style="display:grid;gap:1.25rem">

                {{-- الصورة الشخصية --}}
                <div class="form-group">
                    <label class="form-label">الصورة الشخصية</label>

                    <div
                        style="
                            display:flex;
                            align-items:center;
                            gap:1.25rem;
                            padding:1rem;
                            border:1px solid var(--border);
                            border-radius:12px;
                            background:rgba(255,255,255,.02);
                        "
                    >
                        <div style="flex-shrink:0">
                            @if($user->profile_image)
                                <img
                                    id="profileImagePreview"
                                    src="{{ asset('storage/' . $user->profile_image) }}"
                                    alt="{{ $user->username }}"
                                    width="110"
                                    height="110"
                                    style="
                                        width:110px;
                                        height:110px;
                                        object-fit:cover;
                                        border-radius:50%;
                                        border:3px solid var(--gold, #d4af37);
                                    "
                                >
                            @else
                                <div
                                    id="profileImagePlaceholder"
                                    style="
                                        width:110px;
                                        height:110px;
                                        display:flex;
                                        align-items:center;
                                        justify-content:center;
                                        border-radius:50%;
                                        border:3px solid var(--gold, #d4af37);
                                        background:rgba(212,175,55,.12);
                                        color:var(--gold, #d4af37);
                                        font-size:2.5rem;
                                        font-weight:800;
                                    "
                                >
                                    {{ mb_strtoupper(mb_substr($user->username, 0, 1)) }}
                                </div>

                                <img
                                    id="profileImagePreview"
                                    src=""
                                    alt="معاينة الصورة"
                                    width="110"
                                    height="110"
                                    style="
                                        display:none;
                                        width:110px;
                                        height:110px;
                                        object-fit:cover;
                                        border-radius:50%;
                                        border:3px solid var(--gold, #d4af37);
                                    "
                                >
                            @endif
                        </div>

                        <div style="flex:1">
                            <input
                                id="profileImageInput"
                                name="profile_image"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                class="form-input @error('profile_image') is-invalid @enderror"
                            >

                            <small
                                style="
                                    display:block;
                                    margin-top:.5rem;
                                    color:var(--text-muted, #888);
                                    line-height:1.6;
                                "
                            >
                                JPG أو PNG أو WEBP، بحد أقصى 2MB.
                            </small>

                            @error('profile_image')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    @if($user->profile_image)
                        <label
                            style="
                                display:flex;
                                align-items:center;
                                gap:.5rem;
                                margin-top:.75rem;
                                cursor:pointer;
                                color:#ef4444;
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
                </div>

                {{-- اسم المستخدم --}}
                <div class="form-group">
                    <label class="form-label">اسم المستخدم *</label>

                    <input
                        name="username"
                        class="form-input @error('username') is-invalid @enderror"
                        value="{{ old('username', $user->username) }}"
                        required
                    >

                    @error('username')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                {{-- البريد الإلكتروني --}}
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني *</label>

                    <input
                        name="email"
                        type="email"
                        class="form-input @error('email') is-invalid @enderror"
                        value="{{ old('email', $user->email) }}"
                        required
                    >

                    @error('email')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                    <button class="btn btn-gold" type="submit">
                        حفظ التغييرات
                    </button>

                    <a href="{{ route('users.index') }}" class="btn btn-ghost">
                        إلغاء
                    </a>
                </div>
            </div>
        </form>

        @can('roles.manage')
            <hr style="margin:2rem 0;border-color:var(--border)">

            <h3 style="font-size:.9rem;font-weight:700;margin-bottom:1rem">
                تغيير الدور
            </h3>

            <form
                action="{{ route('users.assign-role', $user) }}"
                method="POST"
            >
                @csrf
                @method('PATCH')

                <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                    <select
                        name="role"
                        class="form-select"
                        style="flex:1"
                        required
                    >
                        @foreach($roles as $role)
                            <option
                                value="{{ $role->name }}"
                                {{ $user->roles->first()?->name === $role->name ? 'selected' : '' }}
                            >
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>

                    <button class="btn btn-outline" type="submit">
                        تحديث الدور
                    </button>
                </div>
            </form>
        @endcan
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('profileImageInput');
    const preview = document.getElementById('profileImagePreview');
    const placeholder = document.getElementById('profileImagePlaceholder');
    const removeCheckbox = document.getElementById('removeProfileImage');

    if (!input || !preview) {
        return;
    }

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

        if (placeholder) {
            placeholder.style.display = 'none';
        }

        if (removeCheckbox) {
            removeCheckbox.checked = false;
        }
    });

    if (removeCheckbox) {
        removeCheckbox.addEventListener('change', function () {
            if (this.checked) {
                input.value = '';
                preview.style.opacity = '.3';
            } else {
                preview.style.opacity = '1';
            }
        });
    }
});
</script>
@endsection