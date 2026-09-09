@extends('layouts.app')
@section('title', $location->name)
@section('content')
<div class="page-actions">
    <div class="page-actions-title">{{ $location->name }}</div>
    <div class="action-btns">
        @can('locations.manage')
        <a href="{{ route('locations.edit', $location) }}" class="btn btn-outline btn-sm">تعديل</a>
        @endcan
        <a href="{{ route('locations.index') }}" class="btn btn-ghost btn-sm">رجوع</a>
    </div>
</div>

<div class="dashboard-row">
    <div class="card">
        <div class="card-header"><span class="card-title">بيانات الموقع</span></div>
        <div class="card-body">
            <table class="data-table">
                <tr><td style="color:var(--text-muted)">الاسم</td><td>{{ $location->name }}</td></tr>
                <tr><td style="color:var(--text-muted)">الرمز</td><td><code>{{ $location->code }}</code></td></tr>
                <tr><td style="color:var(--text-muted)">النوع</td><td>{{ $location->type === 'factory' ? 'مصنع' : 'فرع' }}</td></tr>
                <tr><td style="color:var(--text-muted)">الهاتف</td><td>{{ $location->phone ?? '—' }}</td></tr>
                <tr><td style="color:var(--text-muted)">العنوان</td><td>{{ $location->address ?? '—' }}</td></tr>
                <tr><td style="color:var(--text-muted)">الحالة</td><td><span class="badge {{ $location->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $location->is_active ? 'نشط' : 'معطل' }}</span></td></tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">الموظفون ({{ $location->employees->count() }})</span></div>
        <div class="card-body">
            @forelse($location->employees as $emp)
                <div style="padding:.5rem 0;border-bottom:1px solid var(--border)">
                    <strong>{{ $emp->full_name }}</strong>
                    <span style="color:var(--text-muted);font-size:.8rem"> — {{ $emp->job_title }}</span>
                </div>
            @empty
                <div class="empty-state-sm">لا يوجد موظفون.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
