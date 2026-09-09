@extends('layouts.app')

@section('title', 'تذكرة المطبخ ' . $ticket->ticket_number)

@section('content')
@php
    $order = $ticket->order;
@endphp

<div class="page-actions">
    <div>
        <div class="page-actions-title">{{ $ticket->ticket_number }}</div>
        <div class="page-subheading" style="margin-top:.25rem">
            {{ $ticket->station?->name }} — طلب {{ $order?->order_number }}
        </div>
    </div>

    <div class="action-btns">
        <a href="{{ route('orders.show', $ticket->order_id) }}" class="btn btn-outline">فتح الطلب</a>
        <a href="{{ route('kitchen.tickets.index', ['location_id' => $ticket->location_id]) }}" class="btn btn-ghost">رجوع</a>
    </div>
</div>

<div class="ticket-show-grid">
    <div class="card">
        <div class="card-header">
            <span class="card-title">تفاصيل التذكرة</span>
            <div style="display:flex;gap:.4rem;align-items:center">
                @if($ticket->isUrgent())
                    <span class="badge badge-inactive">عاجل</span>
                @endif
                <span class="badge {{ $ticket->status?->badgeClass() ?? 'badge-grey' }}">
                    {{ $ticket->status?->label() ?? \App\Support\ArabicDisplay::status($ticket->status) }}
                </span>
            </div>
        </div>

        <div class="card-body ticket-meta-grid">
            <div><span>الفرع</span><strong>{{ $ticket->location?->name }}</strong></div>
            <div><span>المحطة</span><strong>{{ $ticket->station?->name }}</strong></div>
            <div><span>نوع الخدمة</span><strong>{{ $order?->restaurant_service_type?->label() ?? '—' }}</strong></div>
            <div>
                <span>الطاولة</span>
                <strong>
                    @if($order?->restaurantTable)
                        {{ $order->restaurantTable->area?->name ? $order->restaurantTable->area->name . ' — ' : '' }}
                        {{ $order->restaurantTable->displayName() }}
                    @else
                        —
                    @endif
                </strong>
            </div>
            <div><span>الموظف</span><strong>{{ $order?->waiter?->employee?->full_name ?? $order?->waiter?->display_name ?? '—' }}</strong></div>
            <div><span>الضيوف</span><strong>{{ $order?->guest_count ?? '—' }}</strong></div>
            <div><span>وقت الوصول</span><strong>{{ $ticket->queued_at?->format('H:i:s') ?? '—' }}</strong></div>
            <div><span>هدف المحطة</span><strong>{{ $ticket->station?->target_minutes ?? '—' }} دقيقة</strong></div>
        </div>

        @if($ticket->notes)
            <div class="ticket-general-note">
                <strong>ملاحظة الطلب:</strong>
                {{ $ticket->notes }}
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">الإجراءات</span>
        </div>

        <div class="card-body ticket-actions">
            @if($ticket->statusValue() === 'queued')
                @can('kitchen.ticket.start')
                    <form method="POST" action="{{ route('kitchen.tickets.start', $ticket) }}">
                        @csrf
                        <button class="btn btn-gold" style="width:100%">بدء التحضير</button>
                    </form>
                @endcan
            @elseif($ticket->statusValue() === 'preparing')
                @can('kitchen.ticket.ready')
                    <form method="POST" action="{{ route('kitchen.tickets.ready', $ticket) }}">
                        @csrf
                        <button class="btn btn-gold" style="width:100%">اعتماد جاهز</button>
                    </form>
                @endcan
            @elseif($ticket->statusValue() === 'ready')
                @can('kitchen.ticket.serve')
                    <form method="POST" action="{{ route('kitchen.tickets.serve', $ticket) }}">
                        @csrf
                        <button class="btn btn-gold" style="width:100%">تم التسليم</button>
                    </form>
                @endcan
            @endif

            @can('kitchen.ticket.priority')
                @if(!in_array($ticket->statusValue(), ['served', 'cancelled'], true))
                    <form method="POST" action="{{ route('kitchen.tickets.priority', $ticket) }}">
                        @csrf
                        <input type="hidden" name="urgent" value="{{ $ticket->isUrgent() ? 0 : 1 }}">
                        <button class="btn btn-outline" style="width:100%">
                            {{ $ticket->isUrgent() ? 'إلغاء العاجل' : 'تحديد كطلب عاجل' }}
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="card-header">
        <span class="card-title">بنود التحضير</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th>الكمية</th>
                    <th>ملاحظات المطبخ</th>
                    <th>مصدر التوجيه</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ticket->items as $item)
                    <tr>
                        <td><strong>{{ $item->product_name }}</strong></td>
                        <td>{{ number_format((float) $item->quantity, 3) }}</td>
                        <td>{{ $item->kitchen_notes ?: '—' }}</td>
                        <td>{{ $item->routing_source?->label() ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $item->status?->badgeClass() ?? 'badge-grey' }}">
                                {{ $item->status?->label() ?? \App\Support\ArabicDisplay::status($item->status) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="card-header">
        <span class="card-title">الخط الزمني</span>
    </div>

    <div class="card-body kitchen-timeline">
        <div class="{{ $ticket->queued_at ? 'done' : '' }}">
            <strong>وصل للمطبخ</strong>
            <span>{{ $ticket->queued_at?->format('d/m/Y H:i:s') ?? '—' }}</span>
            <small>{{ $ticket->dispatchedBy?->employee?->full_name ?? $ticket->dispatchedBy?->display_name ?? '' }}</small>
        </div>
        <div class="{{ $ticket->started_at ? 'done' : '' }}">
            <strong>بدء التحضير</strong>
            <span>{{ $ticket->started_at?->format('d/m/Y H:i:s') ?? '—' }}</span>
            <small>{{ $ticket->startedBy?->employee?->full_name ?? $ticket->startedBy?->display_name ?? '' }}</small>
        </div>
        <div class="{{ $ticket->ready_at ? 'done' : '' }}">
            <strong>جاهز</strong>
            <span>{{ $ticket->ready_at?->format('d/m/Y H:i:s') ?? '—' }}</span>
            <small>{{ $ticket->readyBy?->employee?->full_name ?? $ticket->readyBy?->display_name ?? '' }}</small>
        </div>
        <div class="{{ $ticket->served_at ? 'done' : '' }}">
            <strong>تم التسليم</strong>
            <span>{{ $ticket->served_at?->format('d/m/Y H:i:s') ?? '—' }}</span>
            <small>{{ $ticket->servedBy?->employee?->full_name ?? $ticket->servedBy?->display_name ?? '' }}</small>
        </div>
    </div>
</div>

<style>
.ticket-show-grid{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:1rem}
.ticket-meta-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}
.ticket-meta-grid div{padding:.65rem;border:1px solid var(--border);border-radius:9px}
.ticket-meta-grid span,.ticket-meta-grid strong{display:block}
.ticket-meta-grid span{font-size:.68rem;color:var(--text-muted);margin-bottom:.2rem}.ticket-meta-grid strong{font-size:.78rem}
.ticket-general-note{margin:0 1rem 1rem;padding:.8rem;border-radius:9px;background:rgba(234,179,8,.08);font-size:.76rem;line-height:1.7}
.ticket-actions{display:grid;gap:.6rem}
.kitchen-timeline{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem}
.kitchen-timeline>div{padding:.8rem;border:1px dashed var(--border);border-radius:10px;opacity:.55}
.kitchen-timeline>div.done{opacity:1;border-style:solid}
.kitchen-timeline strong,.kitchen-timeline span,.kitchen-timeline small{display:block}
.kitchen-timeline span{margin-top:.25rem;font-size:.72rem}.kitchen-timeline small{color:var(--text-muted);margin-top:.2rem}
@media(max-width:950px){.ticket-show-grid{grid-template-columns:1fr}.ticket-meta-grid,.kitchen-timeline{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.ticket-meta-grid,.kitchen-timeline{grid-template-columns:1fr}}
</style>
@endsection
