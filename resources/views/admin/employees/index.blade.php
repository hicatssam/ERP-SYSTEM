@extends('layouts.app')

@section('title', 'الموظفون')
@section('page-title', 'الموظفون')

@section('content')
<div class="page-actions">
    <div>
        <div class="page-actions-title">الموظفون</div>
        <p class="page-subheading">
            إدارة بيانات الموظفين وحساباتهم ومواقع عملهم
        </p>
    </div>

    <div class="action-btns">
        @can('employees.manage')
            <a
                href="{{ route('employees.create') }}"
                class="btn btn-gold"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>

                إضافة موظف
            </a>
        @endcan
    </div>
</div>

<div class="filter-row">
    <div class="filter-grid">
        <input
            type="text"
            class="form-input"
            placeholder="البحث بالاسم، الرقم، الوظيفة، الهاتف أو البريد..."
            data-search-table="employeesTable"
        >
    </div>
</div>

<div class="table-wrap employees-table-wrap">
    <table class="data-table employees-table" id="employeesTable">
        <thead>
            <tr>
                <th>الموظف</th>
                <th>بيانات التواصل</th>
                <th>المسمى الوظيفي</th>
                <th>الفرع الأساسي</th>
                <th>حساب النظام</th>
                <th>الحالة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>

        <tbody>
        @forelse($employees as $emp)
            @php
                $primaryEmployeeLocation = $emp->employeeLocations
                    ->firstWhere('is_primary', true);

                $statusValue = $emp->employment_status instanceof \BackedEnum
                    ? $emp->employment_status->value
                    : $emp->employment_status;

                $statusLabel = match ($statusValue) {
                    'active' => 'نشط',
                    'inactive' => 'غير نشط',
                    'terminated' => 'منتهي الخدمة',
                    default => 'غير محدد',
                };

                $statusClass = $statusValue === 'active'
                    ? 'badge-active'
                    : 'badge-inactive';
            @endphp

            <tr>
                {{-- صورة واسم الموظف --}}
                <td>
                    <div class="employee-identity">
                        <a
                            href="{{ route('employees.show', $emp) }}"
                            class="employee-avatar-link"
                            title="عرض بيانات {{ $emp->full_name }}"
                        >
                            @if($emp->profile_image)
                                <img
                                    src="{{ asset('storage/' . $emp->profile_image) }}"
                                    alt="{{ $emp->full_name }}"
                                    class="employee-avatar"
                                    loading="lazy"
                                >
                            @else
                                <div class="employee-avatar employee-avatar-placeholder">
                                    {{ mb_strtoupper(mb_substr($emp->full_name, 0, 1)) }}
                                </div>
                            @endif
                        </a>

                        <div class="employee-main-info">
                            <a
                                href="{{ route('employees.show', $emp) }}"
                                class="employee-name"
                            >
                                {{ $emp->full_name }}
                            </a>

                            <div class="employee-number">
                                <span>رقم الموظف:</span>
                                <code>{{ $emp->employee_number }}</code>
                            </div>
                        </div>
                    </div>
                </td>

                {{-- التواصل --}}
                <td>
                    <div class="employee-contact">
                        @if($emp->phone)
                            <a href="tel:{{ $emp->phone }}" class="employee-contact-item">
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2
                                        19.79 19.79 0 0 1-8.63-3.07
                                        19.5 19.5 0 0 1-6-6
                                        19.79 19.79 0 0 1-3.07-8.67
                                        A2 2 0 0 1 4.11 2h3
                                        a2 2 0 0 1 2 1.72
                                        12.84 12.84 0 0 0 .7 2.81
                                        2 2 0 0 1-.45 2.11L8.09 9.91
                                        a16 16 0 0 0 6 6l1.27-1.27
                                        a2 2 0 0 1 2.11-.45
                                        12.84 12.84 0 0 0 2.81.7
                                        A2 2 0 0 1 22 16.92z"
                                    />
                                </svg>

                                {{ $emp->phone }}
                            </a>
                        @endif

                        @if($emp->email)
                            <a
                                href="mailto:{{ $emp->email }}"
                                class="employee-contact-item"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12
                                        c0 1.1-.9 2-2 2H4
                                        c-1.1 0-2-.9-2-2V6
                                        c0-1.1.9-2 2-2z"
                                    />
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>

                                <span>{{ $emp->email }}</span>
                            </a>
                        @endif

                        @if(!$emp->phone && !$emp->email)
                            <span class="employee-empty-value">
                                لا توجد بيانات تواصل
                            </span>
                        @endif
                    </div>
                </td>

                {{-- الوظيفة --}}
                <td>
                    @if($emp->job_title)
                        <strong class="employee-job-title">
                            {{ $emp->job_title }}
                        </strong>
                    @else
                        <span class="employee-empty-value">—</span>
                    @endif

                    @if($emp->hire_date)
                        <small class="employee-hire-date">
                            منذ {{ $emp->hire_date->format('Y/m/d') }}
                        </small>
                    @endif
                </td>

                {{-- الفرع --}}
                <td>
                    @if($primaryEmployeeLocation?->location)
                        <div class="employee-location">
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

                            <span>
                                {{ $primaryEmployeeLocation->location->name }}
                            </span>
                        </div>
                    @else
                        <span class="employee-empty-value">
                            غير محدد
                        </span>
                    @endif
                </td>

                {{-- حساب النظام --}}
                <td>
                    @if($emp->user)
                        <div class="employee-system-account">
                            <span class="badge badge-active">
                                {{ $emp->user->username }}
                            </span>

                            @if($emp->user->roles->isNotEmpty())
                                <small>
                                    {{ $emp->user->roles->pluck('name')->join(', ') }}
                                </small>
                            @endif
                        </div>
                    @else
                        <span class="badge badge-grey">
                            لا يوجد حساب
                        </span>
                    @endif
                </td>

                {{-- الحالة --}}
                <td>
                    <span class="badge {{ $statusClass }}">
                        <span class="status-dot"></span>
                        {{ $statusLabel }}
                    </span>
                </td>

                {{-- الإجراءات --}}
                <td>
                    <div class="actions">
                        <a
                            href="{{ route('employees.show', $emp) }}"
                            class="btn btn-ghost btn-sm"
                        >
                            عرض
                        </a>

                        @can('employees.manage')
                            <a
                                href="{{ route('employees.edit', $emp) }}"
                                class="btn btn-outline btn-sm"
                            >
                                تعديل
                            </a>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state-sm">
                        لا يوجد موظفون.
                    </div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>



<div>
    {{ $employees->withQueryString()->links() }}
</div>
@endsection

@push('styles')
<style>
.employees-table-wrap {
    overflow-x: auto;
}

.employees-table td {
    vertical-align: middle;
}

.employee-identity {
    min-width: 220px;
    display: flex;
    align-items: center;
    gap: .85rem;
}

.employee-avatar-link {
    display: block;
    flex-shrink: 0;
}

.employee-avatar {
    width: 58px;
    height: 58px;
    display: block;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid var(--gold);
    background: var(--surface);
    box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
    transition: transform .2s ease, box-shadow .2s ease;
}

.employee-avatar-link:hover .employee-avatar {
    transform: scale(1.06);
    box-shadow: 0 5px 16px rgba(212, 175, 55, .25);
}

.employee-avatar-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(
        135deg,
        var(--gold),
        var(--gold-deep)
    );
    color: #fff;
    font-size: 1.35rem;
    font-weight: 800;
}

.employee-main-info {
    min-width: 0;
}

.employee-name {
    display: block;
    color: var(--text);
    font-size: .92rem;
    font-weight: 800;
    text-decoration: none;
    transition: color .2s ease;
}

.employee-name:hover {
    color: var(--gold);
}

.employee-number {
    margin-top: .35rem;
    display: flex;
    align-items: center;
    gap: .35rem;
    color: var(--text-muted);
    font-size: .72rem;
}

.employee-number code {
    color: var(--gold);
    direction: ltr;
}

.employee-contact {
    min-width: 190px;
    display: grid;
    gap: .45rem;
}

.employee-contact-item {
    max-width: 230px;
    display: flex;
    align-items: center;
    gap: .45rem;
    color: var(--text-muted);
    font-size: .78rem;
    text-decoration: none;
}

.employee-contact-item:hover {
    color: var(--gold);
}

.employee-contact-item svg {
    width: 15px;
    height: 15px;
    flex-shrink: 0;
}

.employee-contact-item span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.employee-job-title {
    display: block;
    color: var(--text);
    font-size: .84rem;
}

.employee-hire-date {
    display: block;
    margin-top: .35rem;
    color: var(--text-muted);
    font-size: .7rem;
}

.employee-location {
    min-width: 145px;
    display: flex;
    align-items: center;
    gap: .45rem;
    color: var(--text);
    font-size: .8rem;
}

.employee-location svg {
    width: 17px;
    height: 17px;
    flex-shrink: 0;
    color: var(--gold);
}

.employee-system-account {
    display: grid;
    justify-items: start;
    gap: .4rem;
}

.employee-system-account small {
    color: var(--text-muted);
    font-size: .7rem;
}

.employee-empty-value {
    color: var(--text-muted);
    font-size: .78rem;
}

.status-dot {
    width: 6px;
    height: 6px;
    display: inline-block;
    margin-inline-end: .25rem;
    border-radius: 50%;
    background: currentColor;
}

@media (max-width: 768px) {
    .employee-avatar {
        width: 50px;
        height: 50px;
    }

    .employee-identity {
        min-width: 190px;
    }
}
</style>
@endpush