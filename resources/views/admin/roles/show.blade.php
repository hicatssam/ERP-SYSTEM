@extends('layouts.app')

@section('title', 'تفاصيل الدور')
@section('page-title', 'تفاصيل الدور')

@php
    $roleLabels = \App\Support\PermissionUi::roleLabels();
    $groupLabels = \App\Support\PermissionUi::groupLabels();
    $permissionLabels = \App\Support\PermissionUi::permissionLabels();

    $grouped = $role->permissions
        ->reject(fn($p) => \App\Support\PermissionUi::isLegacyPermission($p->name))
        ->groupBy(fn($p) => explode('.', $p->name)[0]);

    $allPermissions = \Spatie\Permission\Models\Permission::query()
        ->where('guard_name', 'web')
        ->get()
        ->reject(fn($p) => \App\Support\PermissionUi::isLegacyPermission($p->name))
        ->groupBy(fn($p) => explode('.', $p->name)[0]);
@endphp

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">الدور: {{ $roleLabels[$role->name] ?? $role->name }}</h1>
        <p class="page-subheading">{{ $role->permissions->count() }} صلاحية مرتبطة بهذا الدور</p>
    </div>

    <div class="page-header-actions">
        @can('roles.manage')
            @if($role->name !== 'Admin')
                <a href="{{ route('roles.edit', $role) }}" class="btn btn-outline btn-sm">
                    تعديل الدور
                </a>
            @endif
        @endcan

        <a href="{{ route('roles.index') }}" class="btn btn-ghost btn-sm">
            رجوع
        </a>
    </div>
</div>

<div class="dashboard-row">
    <div class="card">
        <div class="card-header">
            <span class="card-title">معلومات الدور</span>
        </div>

        <div class="card-body">
            <div class="detail-list">
                <div class="detail-row">
                    <span class="detail-label">اسم الدور</span>
                    <span class="detail-value">
                        <strong>{{ $roleLabels[$role->name] ?? $role->name }}</strong>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">عدد الصلاحيات</span>
                    <span class="detail-value">
                        <span class="badge badge-gold">{{ $role->permissions->count() }}</span>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">عدد المستخدمين</span>
                    <span class="detail-value">{{ $role->users->count() ?? 0 }} مستخدم</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">تاريخ الإنشاء</span>
                    <span class="detail-value">{{ $role->created_at->format('Y/m/d') }}</span>
                </div>

                @if($role->name === 'Admin')
                    <div class="detail-row">
                        <span class="detail-label">النوع</span>
                        <span class="detail-value">
                            <span class="badge badge-active">دور محمي — لا يمكن تعديله</span>
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">المستخدمون بهذا الدور</span>
        </div>

        <div class="card-body">
            @if(isset($role->users) && $role->users->count() > 0)
                <div style="display:flex;flex-direction:column;gap:.625rem">
                    @foreach($role->users->take(8) as $user)
                        <div style="display:flex;align-items:center;gap:.625rem;padding:.5rem 0;border-bottom:1px solid var(--border-light)">
                            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--gold),var(--gold-deep));display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.75rem;color:#fff;flex-shrink:0">
                                {{ mb_substr($user->employee->full_name ?? $user->username, 0, 1) }}
                            </div>

                            <div>
                                <div style="font-size:.84rem;font-weight:600;color:var(--text)">
                                    {{ $user->employee->full_name ?? $user->username }}
                                </div>
                                <div style="font-size:.74rem;color:var(--text-muted)">
                                    {{ $user->username }}
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @if($role->users->count() > 8)
                        <div style="font-size:.8rem;color:var(--text-muted);text-align:center;padding:.5rem 0">
                            و {{ $role->users->count() - 8 }} آخرون...
                        </div>
                    @endif
                </div>
            @else
                <div class="empty-state-sm">لا يوجد مستخدمون بهذا الدور</div>
            @endif
        </div>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="card-header">
        <span class="card-title">
            صلاحيات الدور ({{ $role->permissions->count() }})
        </span>
    </div>

    <div class="card-body">
        @if($allPermissions->isEmpty())
            <div class="empty-state-sm">لا توجد صلاحيات مسجلة في النظام</div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem">
                @foreach($allPermissions as $group => $allPerms)
                    @php
                        $rolePerms = $grouped->get($group, collect())->pluck('name')->toArray();
                    @endphp

                    <div style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">
                        <div style="background:var(--surface);padding:.625rem 1rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
                            <span style="font-size:.75rem;font-weight:800;color:var(--text-mid)">
                                {{ $groupLabels[$group] ?? 'صلاحيات أخرى' }}
                            </span>

                            <span style="font-size:.7rem;color:var(--text-muted)">
                                {{ count($rolePerms) }}/{{ $allPerms->count() }}
                            </span>
                        </div>

                        <div style="padding:.75rem 1rem;display:flex;flex-direction:column;gap:.5rem">
                            @foreach($allPerms as $perm)
                                @php
                                    $has = in_array($perm->name, $rolePerms, true);
                                @endphp

                                <div style="display:flex;align-items:center;gap:.5rem;font-size:.82rem">
                                    @if($has)
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>

                                        <span style="color:var(--text)">
                                            {{ $permissionLabels[$perm->name] ?? 'صلاحية غير معرفة' }}
                                        </span>
                                    @else
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--text-subtle)" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <line x1="15" y1="9" x2="9" y2="15"/>
                                            <line x1="9" y1="9" x2="15" y2="15"/>
                                        </svg>

                                        <span style="color:var(--text-subtle)">
                                            {{ $permissionLabels[$perm->name] ?? 'صلاحية غير معرفة' }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection