@extends('layouts.app')
@section('title', 'الطلبات')
@section('content')
@php
    $restaurantEnabled = app(\App\Services\ModuleService::class)->isEnabled('restaurant');
@endphp
<div class="page-actions">
    <div class="page-actions-title">الطلبات</div>
    <div class="action-btns">@can('orders.create')<a href="{{ route('orders.create', ['mode'=>'quick']) }}" class="btn btn-gold">⚡ بيع سريع</a><a href="{{ route('orders.create') }}" class="btn btn-outline">+ طلب مسجل</a>@endcan</div>
</div>
<div class="filter-row">
    <form method="GET" class="filter-grid">
        <div class="filter-group"><label class="filter-label">الحالة</label>
            <select name="status" class="form-select">
                <option value="">الكل</option>
                @foreach(['pending' => 'معلق', 'confirmed' => 'مؤكد', 'completed' => 'مكتمل', 'cancelled' => 'ملغى'] as $v => $l)
                    <option value="{{ $v }}" {{ request('status') == $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        @if($restaurantEnabled)
        <div class="filter-group">
            <label class="filter-label">نوع خدمة المطعم</label>
            <select name="service_type" class="form-select">
                <option value="">الكل</option>
                @foreach(\App\Enums\RestaurantServiceType::cases() as $serviceType)
                    <option value="{{ $serviceType->value }}" @selected(request('service_type') === $serviceType->value)>
                        {{ $serviceType->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="filter-group"><label class="filter-label">من تاريخ</label><input type="date" name="date_from" class="form-input" value="{{ request('date_from') }}"></div>
        <div class="filter-group"><label class="filter-label">إلى تاريخ</label><input type="date" name="date_to" class="form-input" value="{{ request('date_to') }}"></div>
        <div class="filter-group" style="justify-content:flex-end"><button class="btn btn-outline btn-sm" type="submit">تصفية</button></div>
    </form>
</div>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>رقم الطلب</th><th>الفرع</th><th>العميل</th>@if($restaurantEnabled)<th>خدمة المطعم</th>@endif<th>المبلغ</th><th>الحالة</th><th>التاريخ</th><th>الإجراءات</th></tr></thead>
        <tbody>
        @forelse($orders as $o)
            <tr>
                <td><strong>{{ $o->order_number }}</strong></td>
                <td>{{ $o->location?->name }}</td>
                <td>{{ $o->customer?->name ?? 'عميل نقدي' }}</td>
                @if($restaurantEnabled)
                    <td>
                        @if($o->restaurant_service_type)
                            {{ $o->restaurant_service_type->label() }}
                            @if(($o->order_source ?? null) === 'customer_menu')
                                <small style="display:block;color:var(--gold);font-weight:800">
                                    منيو العميل
                                </small>
                            @endif
                            @if($o->restaurantTable)
                                <small style="display:block;color:var(--text-muted)">
                                    {{ $o->restaurantTable->displayName() }}
                                </small>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                @endif
                <td>₪{{ number_format($o->total_amount, 2) }}</td>
                <td>
    <span class="badge {{ match($o->status->value) { 'confirmed' => 'badge-active', 'cancelled' => 'badge-inactive', default => 'badge-pending' } }}">
        {{ ['pending' => 'معلق','confirmed' => 'مؤكد','completed' => 'مكتمل','cancelled' => 'ملغى'][$o->status->value] ?? \App\Support\ArabicDisplay::status($o->status) }}
    </span>
</td>
                <td>{{ $o->created_at->format('Y-m-d') }}</td>
                <td><div class="actions"><a href="{{ route('orders.show', $o) }}" class="btn btn-ghost btn-sm">عرض</a></div></td>
            </tr>
        @empty
            <tr><td colspan="{{ $restaurantEnabled ? 8 : 7 }}"><div class="empty-state-sm">لا توجد طلبات.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div>
    {{ $orders->withQueryString()->links() }}
</div>
@endsection
