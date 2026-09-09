@extends('layouts.app')

@section('title', $employee->full_name)
@section('page-title', 'بيانات الموظف')

@section('content')
@php
    $statusValue = $employee->employment_status instanceof \BackedEnum
        ? $employee->employment_status->value
        : $employee->employment_status;

    $statusLabel = match ($statusValue) {
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'terminated' => 'منتهي الخدمة',
        default => 'غير محدد',
    };

    $statusClass = $statusValue === 'active'
        ? 'badge-active'
        : 'badge-inactive';

    $primaryLocation = $employee->employeeLocations
        ->firstWhere('is_primary', true)
        ?->location;
@endphp

<div class="page-actions">
    <div>
        <div class="page-actions-title">
            بيانات الموظف
        </div>

        <p class="page-subheading">
            عرض المعلومات الشخصية والوظيفية وحساب النظام
        </p>
    </div>

    <div class="action-btns">
        @can('employees.manage')
            <a
                href="{{ route('employees.edit', $employee) }}"
                class="btn btn-outline btn-sm"
            >
                تعديل البيانات
            </a>
        @endcan

        @can('employee_ledger.view')
    <a class="btn btn-outline" href="{{ route('payroll.employees.show', $employee) }}">
        الراتب وكشف الحساب
    </a>
@endcan

        <a
            href="{{ route('employees.index') }}"
            class="btn btn-ghost btn-sm"
        >
            رجوع
        </a>
    </div>
</div>

{{-- رأس الملف الشخصي --}}
<div class="card employee-profile-header">
    <div class="card-body">
        <div class="employee-profile-hero">
            <div class="employee-profile-image-wrap">
                @if($employee->profile_image)
                    <img
                        src="{{ asset('storage/' . $employee->profile_image) }}"
                        alt="{{ $employee->full_name }}"
                        class="employee-profile-image"
                    >
                @else
                    <div class="employee-profile-image employee-profile-placeholder">
                        {{ mb_strtoupper(mb_substr($employee->full_name, 0, 1)) }}
                    </div>
                @endif

                <span
                    class="employee-profile-status-dot {{ $statusValue === 'active' ? 'is-active' : 'is-inactive' }}"
                    title="{{ $statusLabel }}"
                ></span>
            </div>

            <div class="employee-profile-heading">
                <div class="employee-profile-name-row">
                    <h1>{{ $employee->full_name }}</h1>

                    <span class="badge {{ $statusClass }}">
                        {{ $statusLabel }}
                    </span>
                </div>

                <div class="employee-profile-job">
                    {{ $employee->job_title ?? 'لا يوجد مسمى وظيفي' }}
                </div>

                <div class="employee-profile-meta">
                    <span>
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <rect x="3" y="4" width="18" height="16" rx="2"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                        </svg>

                        رقم الموظف:
                        <strong>{{ $employee->employee_number }}</strong>
                    </span>

                    <span>
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13
                                a9 9 0 0 1 18 0z"
                            />
                            <circle cx="12" cy="10" r="3"/>
                        </svg>

                        {{ $primaryLocation?->name ?? 'لا يوجد موقع أساسي' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-row employee-details-grid">

    {{-- البيانات الشخصية والوظيفية --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8
                        a4 4 0 0 0-4 4v2"
                    />
                    <circle cx="12" cy="7" r="4"/>
                </svg>

                بيانات الموظف
            </span>
        </div>

        <div class="card-body">
            <div class="employee-detail-list">
                <div class="employee-detail-row">
                    <span class="employee-detail-label">
                        الاسم الكامل
                    </span>

                    <strong class="employee-detail-value">
                        {{ $employee->full_name }}
                    </strong>
                </div>

                <div class="employee-detail-row">
                    <span class="employee-detail-label">
                        رقم الموظف
                    </span>

                    <code class="employee-detail-value">
                        {{ $employee->employee_number }}
                    </code>
                </div>

                <div class="employee-detail-row">
                    <span class="employee-detail-label">
                        رقم الهاتف
                    </span>

                    <span class="employee-detail-value">
                        @if($employee->phone)
                            <a href="tel:{{ $employee->phone }}">
                                {{ $employee->phone }}
                            </a>
                        @else
                            —
                        @endif
                    </span>
                </div>

                <div class="employee-detail-row">
                    <span class="employee-detail-label">
                        البريد الإلكتروني
                    </span>

                    <span class="employee-detail-value">
                        @if($employee->email)
                            <a href="mailto:{{ $employee->email }}">
                                {{ $employee->email }}
                            </a>
                        @else
                            —
                        @endif
                    </span>
                </div>

                <div class="employee-detail-row">
                    <span class="employee-detail-label">
                        المسمى الوظيفي
                    </span>

                    <span class="employee-detail-value">
                        {{ $employee->job_title ?? '—' }}
                    </span>
                </div>

                <div class="employee-detail-row">
                    <span class="employee-detail-label">
                        تاريخ التعيين
                    </span>

                    <span class="employee-detail-value">
                        {{ $employee->hire_date?->format('Y/m/d') ?? '—' }}
                    </span>
                </div>

                <div class="employee-detail-row">
                    <span class="employee-detail-label">
                        مدة العمل
                    </span>

                    <span class="employee-detail-value">
                        {{ $employee->hire_date?->diffForHumans(null, true) ?? '—' }}
                    </span>
                </div>

                <div class="employee-detail-row">
                    <span class="employee-detail-label">
                        الموقع الأساسي
                    </span>

                    <span class="employee-detail-value">
                        {{ $primaryLocation?->name ?? '—' }}
                    </span>
                </div>

                <div class="employee-detail-row">
                    <span class="employee-detail-label">
                        حالة التوظيف
                    </span>

                    <span class="employee-detail-value">
                        <span class="badge {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- حساب النظام --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M20 21a8 8 0 1 0-16 0"/>
                </svg>

                حساب النظام
            </span>
        </div>

        <div class="card-body">
            @if($employee->user)
                <div class="system-account-header">
                    <div class="system-account-avatar">
                        @if($employee->profile_image)
                            <img
                                src="{{ asset('storage/' . $employee->profile_image) }}"
                                alt="{{ $employee->user->username }}"
                            >
                        @else
                            {{ mb_strtoupper(
                                mb_substr($employee->user->username, 0, 1)
                            ) }}
                        @endif
                    </div>

                    <div>
                        <strong>{{ $employee->user->username }}</strong>

                        <span>
                            {{ $employee->user->email }}
                        </span>
                    </div>
                </div>

                <div class="employee-detail-list">
                    <div class="employee-detail-row">
                        <span class="employee-detail-label">
                            اسم المستخدم
                        </span>

                        <strong class="employee-detail-value">
                            {{ $employee->user->username }}
                        </strong>
                    </div>

                    <div class="employee-detail-row">
                        <span class="employee-detail-label">
                            البريد
                        </span>

                        <span class="employee-detail-value">
                            {{ $employee->user->email }}
                        </span>
                    </div>

                    <div class="employee-detail-row">
                        <span class="employee-detail-label">
                            الدور
                        </span>

                        <span class="employee-detail-value">
                            @forelse($employee->user->roles as $role)
                                <span class="badge badge-gold">
                                    {{ $role->name }}
                                </span>
                            @empty
                                —
                            @endforelse
                        </span>
                    </div>

                    <div class="employee-detail-row">
                        <span class="employee-detail-label">
                            حالة الحساب
                        </span>

                        <span class="employee-detail-value">
                            <span
                                class="badge {{ $employee->user->is_active
                                    ? 'badge-active'
                                    : 'badge-inactive' }}"
                            >
                                {{ $employee->user->is_active
                                    ? 'نشط'
                                    : 'معطل' }}
                            </span>
                        </span>
                    </div>

                    <div class="employee-detail-row">
                        <span class="employee-detail-label">
                            آخر دخول
                        </span>

                        <span class="employee-detail-value">
                            {{ $employee->user->last_login_at?->diffForHumans()
                                ?? 'لم يسجل دخوله' }}
                        </span>
                    </div>
                </div>

                @can('users.manage')
                    <div style="margin-top:1.25rem">
                        <a
                            href="{{ route('users.edit', $employee->user) }}"
                            class="btn btn-outline btn-sm"
                        >
                            إدارة حساب المستخدم
                        </a>
                    </div>
                @endcan
            @else
                <div class="no-system-account">
                    <div class="no-system-account-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                        >
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M20 21a8 8 0 1 0-16 0"/>
                            <line x1="18" y1="5" x2="18" y2="11"/>
                            <line x1="15" y1="8" x2="21" y2="8"/>
                        </svg>
                    </div>

                    <h3>لا يوجد حساب نظام</h3>

                    <p>
                        هذا الموظف لا يملك حسابًا للدخول إلى النظام.
                    </p>
                </div>

                @can('users.manage')
                    <form
                        action="{{ route('employees.create-user', $employee) }}"
                        method="POST"
                        class="create-user-form"
                    >
                        @csrf

                        <div style="display:grid;gap:1rem">
                            <div class="form-group">
                                <label class="form-label">
                                    اسم المستخدم *
                                </label>

                                <input
                                    name="username"
                                    class="form-input @error('username') is-invalid @enderror"
                                    value="{{ old('username') }}"
                                    required
                                >

                                @error('username')
                                    <span class="form-error">
                                        {{ $message }}
                                    </span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    الدور *
                                </label>

                                <select
                                    name="role"
                                    class="form-select @error('role') is-invalid @enderror"
                                    required
                                >
                                    <option value="">اختر الدور</option>

                                    @foreach(\Spatie\Permission\Models\Role::orderBy('name')->get() as $role)
                                        <option
                                            value="{{ $role->name }}"
                                            {{ old('role') === $role->name
                                                ? 'selected'
                                                : '' }}
                                        >
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('role')
                                    <span class="form-error">
                                        {{ $message }}
                                    </span>
                                @enderror
                            </div>

                            <button
                                class="btn btn-gold btn-sm"
                                type="submit"
                                style="width:fit-content"
                            >
                                إنشاء حساب للموظف
                            </button>
                        </div>
                    </form>
                @endcan
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.employee-profile-header {
    margin-bottom: 1.25rem;
    overflow: hidden;
}

.employee-profile-hero {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.employee-profile-image-wrap {
    position: relative;
    flex-shrink: 0;
}

.employee-profile-image {
    width: 125px;
    height: 125px;
    display: block;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid var(--gold);
    background: var(--surface);
    box-shadow: 0 8px 25px rgba(0, 0, 0, .15);
}

.employee-profile-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: linear-gradient(
        135deg,
        var(--gold),
        var(--gold-deep)
    );
    font-size: 2.8rem;
    font-weight: 800;
}

.employee-profile-status-dot {
    position: absolute;
    inset-inline-start: 8px;
    bottom: 8px;
    width: 19px;
    height: 19px;
    border: 3px solid var(--surface);
    border-radius: 50%;
}

.employee-profile-status-dot.is-active {
    background: #22c55e;
}

.employee-profile-status-dot.is-inactive {
    background: #ef4444;
}

.employee-profile-heading {
    min-width: 0;
}

.employee-profile-name-row {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
}

.employee-profile-name-row h1 {
    margin: 0;
    color: var(--text);
    font-size: 1.6rem;
    font-weight: 800;
}

.employee-profile-job {
    margin-top: .4rem;
    color: var(--gold);
    font-size: .95rem;
    font-weight: 700;
}

.employee-profile-meta {
    margin-top: .85rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex-wrap: wrap;
    color: var(--text-muted);
    font-size: .8rem;
}

.employee-profile-meta span {
    display: flex;
    align-items: center;
    gap: .4rem;
}

.employee-profile-meta svg {
    width: 17px;
    height: 17px;
    color: var(--gold);
}

.employee-details-grid {
    align-items: start;
}

.employee-detail-list {
    display: grid;
}

.employee-detail-row {
    min-height: 50px;
    display: grid;
    grid-template-columns: minmax(120px, .7fr) 1.3fr;
    align-items: center;
    gap: 1rem;
    padding: .8rem 0;
    border-bottom: 1px solid var(--border);
}

.employee-detail-row:last-child {
    border-bottom: 0;
}

.employee-detail-label {
    color: var(--text-muted);
    font-size: .8rem;
}

.employee-detail-value {
    min-width: 0;
    color: var(--text);
    font-size: .82rem;
    overflow-wrap: anywhere;
}

.employee-detail-value a {
    color: var(--gold);
    text-decoration: none;
}

.system-account-header {
    display: flex;
    align-items: center;
    gap: .85rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.system-account-avatar {
    width: 55px;
    height: 55px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    border-radius: 50%;
    background: linear-gradient(
        135deg,
        var(--gold),
        var(--gold-deep)
    );
    color: #fff;
    font-size: 1.25rem;
    font-weight: 800;
}

.system-account-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.system-account-header strong,
.system-account-header span {
    display: block;
}

.system-account-header span {
    margin-top: .2rem;
    color: var(--text-muted);
    font-size: .75rem;
}

.no-system-account {
    padding: 1.5rem 1rem;
    text-align: center;
}

.no-system-account-icon {
    width: 65px;
    height: 65px;
    margin: 0 auto 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(212, 175, 55, .1);
    color: var(--gold);
}

.no-system-account-icon svg {
    width: 32px;
    height: 32px;
}

.no-system-account h3 {
    margin: 0;
    color: var(--text);
    font-size: 1rem;
}

.no-system-account p {
    margin: .45rem 0 0;
    color: var(--text-muted);
    font-size: .8rem;
}

.create-user-form {
    margin-top: 1rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--border);
}

@media (max-width: 650px) {
    .employee-profile-hero {
        align-items: center;
        flex-direction: column;
        text-align: center;
    }

    .employee-profile-image {
        width: 105px;
        height: 105px;
    }

    .employee-profile-name-row,
    .employee-profile-meta {
        justify-content: center;
    }

    .employee-detail-row {
        grid-template-columns: 1fr;
        gap: .3rem;
    }
}
</style>
@endpush