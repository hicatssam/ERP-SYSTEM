@extends('layouts.app')

@section('title', 'أوامر الإنتاج')
@section('page-title', 'أوامر الإنتاج')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">أوامر الإنتاج</h1>
        <p class="page-subheading">تخطيط، اعتماد، صرف مواد، تسوية استهلاك، وإدخال الناتج.</p>
    </div>
    <div class="page-header-actions">
        @can('production.create')
            <a class="btn btn-gold" href="{{ route('production.orders.create') }}">أمر جديد</a>
        @endcan
        <a class="btn btn-ghost" href="{{ route('production.dashboard') }}">لوحة الإنتاج</a>
    </div>
</div>

<div class="filter-row">
    <form method="GET" action="{{ route('production.orders.index') }}" style="width:100%">
        <div class="filter-grid">
            <div class="filter-group">
                <label class="filter-label">بحث</label>
                <input class="form-input" name="q" value="{{ request('q') }}" placeholder="رقم الإنتاج / المنتج / SKU">
            </div>
            <div class="filter-group">
                <label class="filter-label">الحالة</label>
                <select class="form-input" name="status">
                    <option value="">الكل</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            @if($locations->count() > 1)
                <div class="filter-group">
                    <label class="filter-label">الموقع</label>
                    <select class="form-input" name="location_id">
                        <option value="">كل المواقع المسموحة</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string)request('location_id')===(string)$location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="filter-group" style="justify-content:flex-end">
                <label class="filter-label">&nbsp;</label>
                <button class="btn btn-gold" type="submit">تطبيق</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">سجل أوامر الإنتاج</span>
        <span style="font-size:.78rem;color:var(--text-muted)">{{ number_format($orders->total()) }} أمر</span>
    </div>
    @if($orders->isEmpty())
        <div class="card-body"><div class="empty-state"><h3>لا توجد أوامر إنتاج</h3></div></div>
    @else
        <div class="table-wrap" style="border:none;border-radius:0">
            <table class="data-table">
                <thead><tr><th>الرقم</th><th>المنتج</th><th>الوصفة</th><th>الموقع</th><th>المخطط</th><th>الفعلي</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                @foreach($orders as $order)
                    @php
                        $s=$order->statusValue();
                        $badge=match($s){'completed'=>'badge-active','cancelled'=>'badge-inactive','in_progress'=>'badge-pending','released'=>'badge-pending',default=>'badge-pending'};
                    @endphp
                    <tr>
                        <td><strong>{{ $order->production_number }}</strong><div style="font-size:.7rem;color:var(--text-muted)">{{ $order->planned_at?->format('Y/m/d H:i') }}</div></td>
                        <td>{{ $order->product?->name_ar ?: $order->product?->name }}</td>
                        <td>{{ $order->recipe?->name }} v{{ $order->recipe_version }}</td>
                        <td>{{ $order->location?->name }}</td>
                        <td>{{ number_format((float)$order->planned_output_quantity,3) }}</td>
                        <td>{{ $order->actual_output_quantity === null ? '—' : number_format((float)$order->actual_output_quantity,3) }}</td>
                        <td><span class="badge {{ $badge }}">{{ $order->status->label() }}</span></td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route('production.orders.show',$order) }}">عرض</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:1rem">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
