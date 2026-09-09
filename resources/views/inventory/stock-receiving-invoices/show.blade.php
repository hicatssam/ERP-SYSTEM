@extends('layouts.app')
@section('title', 'فاتورة استلام — ' . $stockReceivingInvoice->invoice_number)
@section('page-title', 'فاتورة استلام المخزون')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">فاتورة رقم {{ $stockReceivingInvoice->invoice_number }}</h1>
        <p class="page-subheading">استلام تحويل مخزون رقم {{ $stockReceivingInvoice->stockTransfer?->transfer_number }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('stock-receiving-invoices.print', $stockReceivingInvoice) }}" target="_blank" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            طباعة
        </a>
        <a href="{{ route('stock-receiving-invoices.pdf', $stockReceivingInvoice) }}" class="btn btn-gold btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            تحميل PDF
        </a>
        <a href="{{ route('stock-transfers.show', $stockReceivingInvoice->stock_transfer_id) }}" class="btn btn-ghost btn-sm">
            العودة للتحويل
        </a>
    </div>
</div>

{{-- Info Grid --}}
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-label">رقم الفاتورة</div>
            <div class="stat-value" style="font-size:1.1rem">{{ $stockReceivingInvoice->invoice_number }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-label">الفرع المستلِم</div>
            <div class="stat-value" style="font-size:1rem">{{ $stockReceivingInvoice->receivingLocation?->name }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-label">المرسِل (المصنع)</div>
            <div class="stat-value" style="font-size:1rem">{{ $stockReceivingInvoice->sendingLocation?->name }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-label">المستلِم</div>
            <div class="stat-value" style="font-size:1rem">{{ $stockReceivingInvoice->receivedBy?->display_name ?? 'غير مسجل' }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-label">تاريخ الاستلام</div>
            <div class="stat-value" style="font-size:1rem">{{ $stockReceivingInvoice->issued_at?->format('Y/m/d H:i') }}</div>
        </div>
    </div>
</div>

{{-- Summary Badges --}}
<div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem">
    <span class="badge badge-blue">إجمالي مطلوب: {{ $stockReceivingInvoice->total_items_ordered }}</span>
    <span class="badge badge-active">إجمالي مستلَم: {{ $stockReceivingInvoice->total_items_received }}</span>
    @if($stockReceivingInvoice->total_items_damaged > 0)
    <span class="badge badge-danger">تالف/ناقص: {{ $stockReceivingInvoice->total_items_damaged }}</span>
    @endif
</div>

{{-- Items Table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">تفاصيل المنتجات</span>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>المنتج</th>
                    <th>SKU</th>
                    <th>الكمية المرسَلة</th>
                    <th>الكمية المستلَمة</th>
                    <th>الفرق</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stockReceivingInvoice->stockTransfer?->items ?? [] as $i => $item)
                @php $diff = $item->sent_quantity - $item->received_quantity; @endphp
                <tr>
                    <td>{{ $i+1 }}</td>
                    <td>{{ $item->product?->name }}</td>
                    <td style="color:var(--text-muted);font-size:.82rem">{{ $item->product?->sku }}</td>
                    <td>{{ $item->sent_quantity }}</td>
                    <td>{{ $item->received_quantity }}</td>
                    <td>
                        @if($diff == 0)
                            <span class="badge badge-active">مطابق</span>
                        @elseif($diff > 0)
                            <span class="badge badge-danger">-{{ $diff }}</span>
                        @else
                            <span class="badge badge-blue">+{{ abs($diff) }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($stockReceivingInvoice->notes)
<div class="card" style="margin-top:1rem">
    <div class="card-body">
        <strong>ملاحظات:</strong> {{ $stockReceivingInvoice->notes }}
    </div>
</div>
@endif
@endsection
