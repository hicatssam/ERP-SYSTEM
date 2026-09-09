@extends('layouts.app')
@section('title', 'حركات المخزون')
@section('content')
<div class="page-actions">
    <div class="page-actions-title">حركات المخزون</div>
</div>

<div class="filter-row">
    <form method="GET" class="filter-grid">
        @if($locations->count() > 1)
        <div class="filter-group">
            <label class="filter-label">الموقع</label>
            <select name="location_id" class="form-select"><option value="">الكل</option>
                @foreach($locations as $loc)<option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>@endforeach
            </select>
        </div>
        @endif
        <div class="filter-group">
            <label class="filter-label">السبب</label>
            <select name="reason" class="form-select"><option value="">الكل</option>
                @foreach(\App\Enums\MovementReason::cases() as $r)
                    <option value="{{ $r->value }}" {{ request('reason') == $r->value ? 'selected' : '' }}>{{ $r->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">من تاريخ</label>
            <input type="date" name="date_from" class="form-input" value="{{ request('date_from') }}">
        </div>
        <div class="filter-group">
            <label class="filter-label">إلى تاريخ</label>
            <input type="date" name="date_to" class="form-input" value="{{ request('date_to') }}">
        </div>
        <div class="filter-group" style="justify-content:flex-end">
            <button class="btn btn-outline btn-sm" type="submit">تصفية</button>
        </div>
    </form>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>التاريخ</th><th>المنتج</th><th>الموقع</th><th>النوع</th><th>السبب</th><th>الكمية</th><th>الرصيد بعد</th><th>المنفذ</th></tr>
        </thead>
        <tbody>
        @forelse($movements as $mv)
            @php $isIn = $mv->movement_type === \App\Enums\MovementType::In; @endphp
            <tr>
                <td>{{ $mv->created_at->format('Y-m-d H:i') }}</td>
                <td>{{ $mv->product?->name_ar ?? $mv->product?->name }}</td>
                <td>{{ $mv->location?->name }}</td>
                <td><span class="badge {{ $isIn ? 'badge-active' : 'badge-inactive' }}">{{ $isIn ? 'وارد' : 'صادر' }}</span></td>
                <td>{{ $mv->reason?->label() ?? $mv->reason?->value ?? '—' }}</td>
                <td><strong style="{{ $isIn ? 'color:var(--success)' : 'color:var(--error)' }}">{{ $isIn ? '+' : '-' }}{{ number_format($mv->quantity, 2) }}</strong></td>
                <td>{{ number_format($mv->balance_after, 2) }}</td>
                <td>{{ $mv->creator?->display_name ?? 'غير مسجل' }}</td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state-sm">لا توجد حركات.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div>
    {{ $movements->withQueryString()->links() }}
</div>
@endsection
