@extends('layouts.app')

@section('title', 'تذاكر المطبخ')

@section('content')
<div class="page-actions">
    <div>
        <div class="page-actions-title">تذاكر المطبخ</div>
        <div class="page-subheading" style="margin-top:.25rem">
            {{ $location->name }} — السجل التشغيلي لتذاكر التحضير.
        </div>
    </div>

    <div class="action-btns">
        @can('kds.view')
            @if(\Illuminate\Support\Facades\Route::has('kds.index'))
                <a href="{{ route('kds.index', ['location_id' => $location->id]) }}" class="btn btn-gold">شاشة KDS</a>
            @endif
        @endcan

        @can('kitchen.stations.manage')
            <a href="{{ route('kitchen.stations.index', ['location_id' => $location->id]) }}" class="btn btn-outline">
                محطات المطبخ
            </a>
        @endcan
    </div>
</div>

<div class="filter-row">
    <form method="GET" class="filter-grid">
        @if($locations->count() > 1)
        <div class="filter-group">
            <label class="filter-label">الفرع</label>
            <select name="location_id" class="form-select">
                @foreach($locations as $branch)
                    <option value="{{ $branch->id }}" @selected((int) $branch->id === (int) $location->id)>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @else
            <input type="hidden" name="location_id" value="{{ $location->id }}">
        @endif

        <div class="filter-group">
            <label class="filter-label">المحطة</label>
            <select name="station_id" class="form-select">
                <option value="">كل المحطات</option>
                @foreach($stations as $station)
                    <option value="{{ $station->id }}" @selected((int) request('station_id') === (int) $station->id)>
                        {{ $station->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">الحالة</label>
            <select name="status" class="form-select">
                <option value="">كل الحالات</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">التاريخ</label>
            <input type="date" name="date" class="form-input" value="{{ request('date') }}">
        </div>

        <div class="filter-group" style="justify-content:flex-end">
            <label class="filter-label">&nbsp;</label>
            <button class="btn btn-gold">تطبيق</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">التذاكر</span>
        <span class="badge badge-grey">{{ number_format($tickets->total()) }}</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>التذكرة</th>
                    <th>الطلب</th>
                    <th>المحطة</th>
                    <th>الخدمة / الطاولة</th>
                    <th>الموظف</th>
                    <th>الحالة</th>
                    <th>الانتظار</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                    @php
                        $order = $ticket->order;
                        $table = $order?->restaurantTable;
                        $minutes = $ticket->queued_at ? $ticket->queued_at->diffInMinutes(now()) : 0;
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $ticket->ticket_number }}</strong>
                            @if($ticket->isUrgent())
                                <span class="badge badge-inactive" style="margin-inline-start:.35rem">عاجل</span>
                            @endif
                        </td>
                        <td>{{ $order?->order_number ?? '—' }}</td>
                        <td>{{ $ticket->station?->name ?? '—' }}</td>
                        <td>
                            @if($table)
                                {{ $table->area?->name ? $table->area->name . ' — ' : '' }}{{ $table->displayName() }}
                            @else
                                {{ $order?->restaurant_service_type?->label() ?? '—' }}
                            @endif
                        </td>
                        <td>{{ $order?->waiter?->employee?->full_name ?? $order?->waiter?->display_name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $ticket->status?->badgeClass() ?? 'badge-grey' }}">
                                {{ $ticket->status?->label() ?? \App\Support\ArabicDisplay::status($ticket->status) }}
                            </span>
                        </td>
                        <td>{{ number_format($minutes) }} د</td>
                        <td>
                            <a href="{{ route('kitchen.tickets.show', $ticket) }}" class="btn btn-ghost btn-sm">عرض</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state-sm">لا توجد تذاكر مطابقة للفلاتر.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tickets->hasPages())
        <div class="card-body">
            {{ $tickets->links() }}
        </div>
    @endif
</div>
@endsection
