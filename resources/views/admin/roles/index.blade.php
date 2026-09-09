@extends('layouts.app')

@section('title', 'الأدوار والصلاحيات')
@section('page-title', 'الأدوار والصلاحيات')

@php
    $roleLabels = \App\Support\PermissionUi::roleLabels();
@endphp

@section('content')
<div class="page-actions">
    <div class="page-actions-title">الأدوار والصلاحيات</div>

    <div class="action-btns">
        @can('roles.manage')
            <a href="{{ route('roles.create') }}" class="btn btn-gold">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                إضافة دور
            </a>
        @endcan
    </div>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>اسم الدور</th>
                <th>عدد الصلاحيات</th>
                <th>عدد المستخدمين</th>
                <th>الإجراءات</th>
            </tr>
        </thead>

        <tbody>
            @forelse($roles as $role)
                @php
                    $isAdminRole = strcasecmp($role->name, 'Admin') === 0;
                @endphp

                <tr>
                    <td>
                        <strong>{{ $roleLabels[$role->name] ?? $role->name }}</strong>
                    </td>

                    <td>{{ $role->permissions->count() }}</td>
                    <td>{{ $role->users_count ?? 0 }}</td>

                    <td>
                        <div class="actions">
                            <a href="{{ route('roles.show', $role) }}" class="btn btn-ghost btn-sm">
                                عرض
                            </a>

                            @can('roles.manage')
                                @unless($isAdminRole)
                                    <a href="{{ route('roles.edit', $role) }}" class="btn btn-outline btn-sm">
                                        تعديل
                                    </a>

                                    <form action="{{ route('roles.destroy', $role) }}" method="POST" style="display:inline">
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="btn btn-ghost btn-sm"
                                            data-confirm="حذف دور {{ $roleLabels[$role->name] ?? $role->name }}؟"
                                            style="color:var(--error)"
                                        >
                                            حذف
                                        </button>
                                    </form>
                                @endunless
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">
                        <div class="empty-state-sm">لا توجد أدوار.</div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection