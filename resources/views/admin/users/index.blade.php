@extends('layouts.app')

@section('title', 'المستخدمون')
@section('page-title', 'المستخدمون')

@section('content')
<div class="page-actions">
    <div>
        <div class="page-actions-title">
            حسابات المستخدمين
        </div>

        <p class="page-subheading">
            إدارة حسابات الدخول والأدوار وحالة المستخدمين
        </p>
    </div>
</div>

<div class="filter-row">
    <div class="filter-grid">
        <input
            type="text"
            class="form-input"
            placeholder="البحث باسم المستخدم، الموظف، البريد أو الدور..."
            data-search-table="usersTable"
        >
    </div>
</div>

<div class="table-wrap users-table-wrap">
    <table class="data-table users-table" id="usersTable">
        <thead>
            <tr>
                <th>المستخدم</th>
                <th>الموظف المرتبط</th>
                <th>البريد الإلكتروني</th>
                <th>الدور</th>
                <th>آخر دخول</th>
                <th>الحالة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>

        <tbody>
        @forelse($users as $user)
            @php
                $profileImage = $user->employee?->profile_image
                    ?? $user->profile_image;

                $displayName = $user->employee?->full_name
                    ?? $user->username;
            @endphp

            <tr>
                {{-- المستخدم والصورة --}}
                <td>
                    <div class="user-table-identity">
                        <a
                            href="{{ route('users.edit', $user) }}"
                            class="user-table-avatar-link"
                            title="تعديل {{ $user->username }}"
                        >
                            @if($profileImage)
                                <img
                                    src="{{ asset('storage/' . $profileImage) }}"
                                    alt="{{ $displayName }}"
                                    class="user-table-avatar"
                                    loading="lazy"
                                >
                            @else
                                <div class="user-table-avatar user-avatar-placeholder">
                                    {{ mb_strtoupper(
                                        mb_substr($displayName, 0, 1)
                                    ) }}
                                </div>
                            @endif

                            <span
                                class="user-online-indicator {{ $user->is_active
                                    ? 'is-active'
                                    : 'is-inactive' }}"
                            ></span>
                        </a>

                        <div class="user-table-main-info">
                            <a
                                href="{{ route('users.edit', $user) }}"
                                class="user-table-username"
                            >
                                {{ $user->username }}
                            </a>

                            <span class="user-table-account-type">
                                حساب نظام
                            </span>
                        </div>
                    </div>
                </td>

                {{-- الموظف --}}
                <td>
                    @if($user->employee)
                        <div class="linked-employee">
                            <strong>
                                {{ $user->employee->full_name }}
                            </strong>

                            <span>
                                {{ $user->employee->job_title
                                    ?? 'بدون مسمى وظيفي' }}
                            </span>

                            <code>
                                {{ $user->employee->employee_number }}
                            </code>
                        </div>
                    @else
                        <span class="badge badge-grey">
                            غير مرتبط بموظف
                        </span>
                    @endif
                </td>

                {{-- البريد --}}
                <td>
                    <a
                        href="mailto:{{ $user->email }}"
                        class="user-email"
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

                        <span>{{ $user->email }}</span>
                    </a>
                </td>

                {{-- الأدوار --}}
                <td>
                    <div class="user-roles">
                        @forelse($user->roles as $role)
                            <span class="badge badge-gold">
                                {{ $role->name }}
                            </span>
                        @empty
                            <span class="badge badge-grey">
                                بدون دور
                            </span>
                        @endforelse
                    </div>
                </td>

                {{-- آخر دخول --}}
                <td>
                    @if($user->last_login_at)
                        <div class="last-login">
                            <strong>
                                {{ $user->last_login_at->diffForHumans() }}
                            </strong>

                            <span>
                                {{ $user->last_login_at->format('Y/m/d H:i') }}
                            </span>
                        </div>
                    @else
                        <span class="never-logged-in">
                            لم يسجل دخوله
                        </span>
                    @endif
                </td>

                {{-- الحالة --}}
                <td>
                    <span
                        class="badge {{ $user->is_active
                            ? 'badge-active'
                            : 'badge-inactive' }}"
                    >
                        <span class="user-status-dot"></span>

                        {{ $user->is_active ? 'نشط' : 'معطل' }}
                    </span>
                </td>

                {{-- الإجراءات --}}
                <td>
                    <div class="actions">
                        @can('users.manage')
                            <a
                                href="{{ route('users.edit', $user) }}"
                                class="btn btn-outline btn-sm"
                            >
                                تعديل
                            </a>

                            <form
                                action="{{ route('users.toggle', $user) }}"
                                method="POST"
                                style="display:inline"
                            >
                                @csrf
                              

                                <button
                                    type="submit"
                                    class="btn btn-ghost btn-sm"
                                    data-confirm="{{ $user->is_active
                                        ? 'هل تريد تعطيل حساب ' . $user->username . '؟'
                                        : 'هل تريد تفعيل حساب ' . $user->username . '؟' }}"
                                    @disabled($user->id === auth()->id())
                                    title="{{ $user->id === auth()->id()
                                        ? 'لا يمكنك تعطيل حسابك الخاص'
                                        : '' }}"
                                >
                                    {{ $user->is_active ? 'تعطيل' : 'تفعيل' }}
                                </button>
                            </form>

                            <form
                                action="{{ route('users.reset-password', $user) }}"
                                method="POST"
                                style="display:inline"
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="btn btn-ghost btn-sm"
                                    data-confirm="إعادة ضبط كلمة مرور {{ $user->username }}؟"
                                >
                                    ضبط كلمة المرور
                                </button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state-sm">
                        لا يوجد مستخدمون.
                    </div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>



<div>
    {{ $users->withQueryString()->links() }}
</div>

@endsection

@push('styles')
<style>
.users-table-wrap {
    overflow-x: auto;
}

.users-table td {
    vertical-align: middle;
}

.user-table-identity {
    min-width: 200px;
    display: flex;
    align-items: center;
    gap: .8rem;
}

.user-table-avatar-link {
    position: relative;
    display: block;
    flex-shrink: 0;
}

.user-table-avatar {
    width: 56px;
    height: 56px;
    display: block;
    object-fit: cover;
    border: 2px solid var(--gold);
    border-radius: 50%;
    background: var(--surface);
    box-shadow: 0 4px 12px rgba(0, 0, 0, .12);
    transition: transform .2s ease;
}

.user-table-avatar-link:hover .user-table-avatar {
    transform: scale(1.06);
}

.user-avatar-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: linear-gradient(
        135deg,
        var(--gold),
        var(--gold-deep)
    );
    font-size: 1.3rem;
    font-weight: 800;
}

.user-online-indicator {
    position: absolute;
    inset-inline-start: 1px;
    bottom: 2px;
    width: 13px;
    height: 13px;
    border: 2px solid var(--surface);
    border-radius: 50%;
}

.user-online-indicator.is-active {
    background: #22c55e;
}

.user-online-indicator.is-inactive {
    background: #ef4444;
}

.user-table-main-info {
    min-width: 0;
}

.user-table-username {
    display: block;
    color: var(--text);
    font-size: .9rem;
    font-weight: 800;
    text-decoration: none;
}

.user-table-username:hover {
    color: var(--gold);
}

.user-table-account-type {
    display: block;
    margin-top: .25rem;
    color: var(--text-muted);
    font-size: .7rem;
}

.linked-employee {
    min-width: 150px;
    display: grid;
    gap: .2rem;
}

.linked-employee strong {
    color: var(--text);
    font-size: .8rem;
}

.linked-employee span {
    color: var(--text-muted);
    font-size: .7rem;
}

.linked-employee code {
    width: fit-content;
    color: var(--gold);
    font-size: .68rem;
}

.user-email {
    max-width: 220px;
    display: flex;
    align-items: center;
    gap: .45rem;
    color: var(--text-muted);
    font-size: .77rem;
    text-decoration: none;
}

.user-email:hover {
    color: var(--gold);
}

.user-email svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.user-email span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-roles {
    min-width: 100px;
    display: flex;
    align-items: center;
    gap: .3rem;
    flex-wrap: wrap;
}

.last-login {
    min-width: 125px;
    display: grid;
    gap: .25rem;
}

.last-login strong {
    color: var(--text);
    font-size: .75rem;
}

.last-login span,
.never-logged-in {
    color: var(--text-muted);
    font-size: .68rem;
}

.user-status-dot {
    width: 6px;
    height: 6px;
    display: inline-block;
    margin-inline-end: .25rem;
    border-radius: 50%;
    background: currentColor;
}

@media (max-width: 768px) {
    .user-table-avatar {
        width: 50px;
        height: 50px;
    }
}
</style>
@endpush