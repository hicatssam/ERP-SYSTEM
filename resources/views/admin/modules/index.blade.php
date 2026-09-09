@extends('layouts.app')

@section('title', 'إدارة الوحدات')
@section('page-title', 'إدارة الوحدات')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">إدارة الوحدات</h1>
        <p class="page-subheading">المصدر المركزي لتفعيل وحدات النظام — الصلاحيات تبقى مستقلة عبر Spatie Permission.</p>
    </div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        @if(\Illuminate\Support\Facades\Route::has('business-profiles.index'))
            <a href="{{ route('business-profiles.index') }}" class="btn btn-outline">نوع النشاط</a>
        @endif
        <a href="{{ route('settings.index') }}" class="btn btn-ghost">إعدادات النظام</a>
    </div>
</div>

@if($errors->any())
    <div class="alert-banner alert-banner-danger" style="margin-bottom:1rem">
        {{ $errors->first() }}
    </div>
@endif

<div class="card" style="margin-bottom:1rem">
    <div class="card-body">
        <form method="GET" action="{{ route('modules.index') }}" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:.75rem;align-items:end">
            <div>
                <label class="form-label">بحث</label>
                <input class="form-input" name="q" value="{{ request('q') }}" placeholder="اسم الوحدة أو الكود">
            </div>
            <div>
                <label class="form-label">النوع</label>
                <select class="form-input" name="type">
                    <option value="">الكل</option>
                    @foreach($types as $type)
                        <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">الحالة</label>
                <select class="form-input" name="status">
                    <option value="">الكل</option>
                    <option value="active" @selected(request('status') === 'active')>مفعلة</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>غير مفعلة</option>
                </select>
            </div>
            <button class="btn btn-gold" type="submit">تطبيق</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">سجل الوحدات</span>
        <span style="font-size:.75rem;color:var(--text-muted)">{{ $modules->count() }} وحدة</span>
    </div>
    <div class="table-wrap" style="border:none;border-radius:0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>الوحدة</th>
                    <th>الكود</th>
                    <th>النوع</th>
                    <th>التنفيذ</th>
                    <th>المتطلبات</th>
                    <th>الحالة</th>
                    <th>الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @foreach($modules as $module)
                    <tr>
                        <td>
                            <strong>{{ $module->name }}</strong>
                            <div style="font-size:.7rem;color:var(--text-muted);margin-top:.2rem">{{ $module->description }}</div>
                            @if($module->is_system)
                                <span class="badge badge-active" style="margin-top:.35rem">نظام محمي</span>
                            @endif
                        </td>
                        <td><code>{{ $module->code }}</code></td>
                        <td><span class="badge badge-pending">{{ $module->type->label() }}</span></td>
                        <td>
                            @if($module->isImplemented())
                                <span class="badge badge-active">منفذة</span>
                            @else
                                <span class="badge badge-pending">مسجلة للمستقبل</span>
                            @endif
                        </td>
                        <td>
                            @forelse($module->dependencies as $dependency)
                                <span class="badge {{ $dependency->is_active ? 'badge-active' : 'badge-inactive' }}" style="margin:.1rem">{{ $dependency->name }}</span>
                            @empty
                                <span style="color:var(--text-muted)">—</span>
                            @endforelse
                        </td>
                        <td>
                            <span class="badge {{ $module->is_active ? 'badge-active' : 'badge-inactive' }}">
                                {{ $module->is_active ? 'مفعلة' : 'غير مفعلة' }}
                            </span>
                        </td>
                        <td>
                            @if($module->is_system)
                                <span style="font-size:.72rem;color:var(--text-muted)">محمي</span>
                            @elseif(!$module->isImplemented() && !$module->is_active)
                                <span style="font-size:.72rem;color:var(--text-muted)">غير متاح بعد</span>
                            @else
                                <form method="POST" action="{{ route('modules.update', $module) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="enabled" value="{{ $module->is_active ? 0 : 1 }}">
                                    <button type="submit" class="btn {{ $module->is_active ? 'btn-ghost' : 'btn-gold' }} btn-sm">
                                        {{ $module->is_active ? 'تعطيل' : 'تفعيل' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($bundles->isNotEmpty())
<div class="card" style="margin-top:1rem">
    <div class="card-header"><span class="card-title">حزم الوحدات</span></div>
    <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem">
        @foreach($bundles as $bundle)
            <div style="border:1px solid var(--border);border-radius:var(--radius);padding:1rem">
                <strong>{{ $bundle->name }}</strong>
                <p style="font-size:.75rem;color:var(--text-muted);margin:.35rem 0 .75rem">{{ $bundle->description }}</p>
                <div style="display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:.8rem">
                    @foreach($bundle->modules as $module)
                        <span class="badge {{ $module->isImplemented() ? 'badge-active' : 'badge-pending' }}">{{ $module->name }}</span>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('modules.bundles.apply', $bundle) }}">
                    @csrf
                    <button class="btn btn-outline btn-sm" type="submit">تطبيق الحزمة الآمنة</button>
                </form>
            </div>
        @endforeach
    </div>
</div>
@endif
@endsection
