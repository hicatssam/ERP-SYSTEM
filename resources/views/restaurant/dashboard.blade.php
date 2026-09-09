@extends('layouts.app')

@section('title', 'تشغيل المطعم')

@section('content')
<div class="page-actions">
    <div>
        <div class="page-actions-title">تشغيل المطعم</div>
        <div style="margin-top:.25rem;color:var(--text-muted);font-size:.78rem">
            {{ $location->name }} — تشغيل المطعم والطاولات والمطبخ من مكان واحد
        </div>
    </div>

    <div class="action-btns">
        @if($kdsEnabled)
            @can('kds.view')
                @if(\Illuminate\Support\Facades\Route::has('kds.index'))
                    <a href="{{ route('kds.index', ['location_id' => $location->id]) }}" class="btn btn-gold">
                        شاشة المطبخ KDS
                    </a>
                @endif
            @endcan
        @endif

         @if($kitchenEnabled)
            @can('kitchen.view')
                @if(\Illuminate\Support\Facades\Route::has('restaurant.customer-display.index'))
                    <a href="{{ route('restaurant.customer-display.index', ['location_id' => $location->id]) }}" class="btn btn-outline">
                       شاشة عرض العملاء
                    </a>
                @endif
            @endcan
        @endif

       

        @if($kitchenEnabled)
            @can('kitchen.view')
                @if(\Illuminate\Support\Facades\Route::has('kitchen.tickets.index'))
                    <a href="{{ route('kitchen.tickets.index', ['location_id' => $location->id]) }}" class="btn btn-outline">
                        تذاكر المطبخ
                    </a>
                @endif
            @endcan
        @endif

        @can('restaurant_pos.use')
            @if(\Illuminate\Support\Facades\Route::has('restaurant.pos.index'))
                <a href="{{ route('restaurant.pos.index', ['location_id' => $location->id]) }}" class="btn btn-gold">
                    نقطة البيع POS
                </a>
            @endif
        @endcan

        @can('restaurant_tables.view')
            <a href="{{ route('restaurant.tables.index', ['location_id' => $location->id]) }}" class="btn btn-outline">
                الطاولات
            </a>
        @endcan
    </div>
</div>

@if($locations->count() > 1)
<div class="filter-row" style="margin-bottom:1rem">
    <form method="GET" class="filter-grid">
        <div class="filter-group">
            <label class="filter-label">الفرع</label>
            <select name="location_id" class="form-select" onchange="this.form.submit()">
                @foreach($locations as $branch)
                    <option value="{{ $branch->id }}" @selected((int) $branch->id === (int) $location->id)>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>
</div>
@endif

<div class="stats-grid restaurant-stats">
    <div class="stat-card stat-gold">
        <div class="stat-info">
            <div class="stat-value">{{ number_format($todayOrderCount) }}</div>
            <div class="stat-label">طلبات المطعم اليوم</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value">{{ number_format($openOrders) }}</div>
            <div class="stat-label">طلبات مفتوحة</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value">{{ $occupiedTables }} / {{ $totalTables }}</div>
            <div class="stat-label">طاولات مشغولة</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value">₪{{ number_format($todaySales, 2) }}</div>
            <div class="stat-label">قيمة طلبات اليوم</div>
        </div>
    </div>
</div>

@if($kitchenEnabled)
<div class="stats-grid restaurant-kitchen-stats" style="margin-top:1rem">
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value">{{ number_format($kitchenCounts['queued']) }}</div>
            <div class="stat-label">بانتظار التحضير</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value">{{ number_format($kitchenCounts['preparing']) }}</div>
            <div class="stat-label">قيد التحضير</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value">{{ number_format($kitchenCounts['ready']) }}</div>
            <div class="stat-label">جاهز للتسليم</div>
        </div>
    </div>
    <div class="stat-card {{ $kitchenCounts['urgent'] > 0 ? 'stat-gold' : '' }}">
        <div class="stat-info">
            <div class="stat-value">{{ number_format($kitchenCounts['urgent']) }}</div>
            <div class="stat-label">طلبات عاجلة</div>
        </div>
    </div>
</div>
@endif

<div class="card" style="margin-top:1rem">
    <div class="card-header">
        <span class="card-title">آخر طلبات المطعم</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>الطلب</th>
                    <th>الخدمة</th>
                    <th>الطاولة</th>
                    <th>الموظف</th>
                    <th>الإجمالي</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOrders as $order)
                    <tr>
                        <td><strong>{{ $order->order_number }}</strong></td>
                        <td>{{ $order->restaurant_service_type?->label() ?? '—' }}</td>
                        <td>
                            @if($order->restaurantTable)
                                {{ $order->restaurantTable->area?->name ? $order->restaurantTable->area->name . ' — ' : '' }}
                                {{ $order->restaurantTable->displayName() }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $order->waiter?->employee?->full_name ?? $order->waiter?->display_name ?? '—' }}</td>
                        <td>₪{{ number_format((float) $order->total_amount, 2) }}</td>
                        <td>{{ $order->status?->label() ?? \App\Support\ArabicDisplay::status($order->status) }}</td>
                        <td>
                            <a href="{{ route('orders.show', $order) }}" class="btn btn-ghost btn-sm">عرض</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state-sm">لا توجد طلبات مطعم حتى الآن.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<style>
.restaurant-stats,.restaurant-kitchen-stats{grid-template-columns:repeat(4,minmax(0,1fr))}
@media(max-width:1000px){.restaurant-stats,.restaurant-kitchen-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.restaurant-stats,.restaurant-kitchen-stats{grid-template-columns:1fr}}
</style>
@endsection
