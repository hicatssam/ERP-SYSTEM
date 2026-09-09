@extends('layouts.app')

@section('title', 'تعديل الدور')
@section('page-title', 'تعديل الدور')

@php
    $groupLabels = \App\Support\PermissionUi::groupLabels();
    $permissionLabels = \App\Support\PermissionUi::permissionLabels();

    $selectedPermissions = old(
        'permissions',
        $role->permissions->pluck('name')->all()
    );
@endphp

@section('content')
<div class="page-header">
    <h1 class="page-heading">
        تعديل الدور: {{ \App\Support\PermissionUi::roleLabel($role->name) }}
    </h1>

    <p class="page-subheading">
        <a href="{{ route('roles.index') }}">الأدوار</a>
        &laquo; تعديل
    </p>
</div>

<div class="card" style="max-width:900px">
    <div class="card-body">
        <form action="{{ route('roles.update', $role) }}" method="POST">
            @csrf
            @method('PUT')

            <div style="display:grid;gap:1.5rem">
                <div class="form-group">
                    <label class="form-label" for="role-name">اسم الدور *</label>

                    <input
                        id="role-name"
                        type="text"
                        name="name"
                        maxlength="50"
                        class="form-input @error('name') is-invalid @enderror"
                        value="{{ old('name', $role->name) }}"
                        required
                    >

                    @error('name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <div class="form-label" style="margin-bottom:.75rem">الصلاحيات</div>

                    @error('permissions')
                        <span class="form-error" style="display:block;margin-bottom:.75rem">{{ $message }}</span>
                    @enderror

                    @error('permissions.*')
                        <span class="form-error" style="display:block;margin-bottom:.75rem">{{ $message }}</span>
                    @enderror

                    @forelse($permissions as $group => $groupPerms)
                        <div style="margin-bottom:1rem;border:1px solid var(--border);border-radius:12px;overflow:hidden">
                            <div style="padding:.7rem 1rem;background:var(--surface);font-size:.82rem;font-weight:800;color:var(--gold-deep)">
                                {{ $groupLabels[$group] ?? 'صلاحيات أخرى' }}
                            </div>

                            <div style="padding:.85rem 1rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.65rem">
                                @foreach($groupPerms as $perm)
                                    @if(!\App\Support\PermissionUi::isLegacyPermission($perm->name))
                                        <label style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:var(--text-mid);cursor:pointer">
                                            <input
                                                type="checkbox"
                                                name="permissions[]"
                                                value="{{ $perm->name }}"
                                                @checked(in_array($perm->name, $selectedPermissions, true))
                                            >

                                            <span>
                                                {{ $permissionLabels[$perm->name] ?? 'صلاحية غير معرفة' }}
                                            </span>
                                        </label>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="empty-state-sm">لا توجد صلاحيات مسجلة في النظام.</div>
                    @endforelse
                </div>

                <div style="display:flex;gap:.75rem">
                    <button class="btn btn-gold" type="submit">حفظ التعديلات</button>
                    <a href="{{ route('roles.show', $role) }}" class="btn btn-ghost">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection