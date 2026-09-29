@php
    $receivingTheme = app(\App\Services\PrintThemeService::class)->settings();
    $transfer = $stockReceivingInvoice->stockTransfer;
@endphp

@extends('layouts.print')

@section('pdf_mode', ($pdfMode ?? false) ? '1' : '0')
@section('document_title', 'فاتورة استلام مخزون')
@section('document_number', $stockReceivingInvoice->invoice_number)
@section('document_date', $stockReceivingInvoice->issued_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'))
@section('document_subtitle', ($stockReceivingInvoice->sendingLocation?->name ?? '—') . ' ← ' . ($stockReceivingInvoice->receivingLocation?->name ?? '—'))
@section('document_meta', 'التحويل: ' . ($transfer?->transfer_number ?? '—'))
@section('signature_right', 'توقيع المستلم')
@section('signature_left', 'اعتماد الإدارة')

@push('print_styles')
<style>
    .receiving-info {
        width: 100%;
        margin: 12px 0;
        table-layout: fixed;
        border-spacing: 5px 0;
    }
    .receiving-info > tbody > tr > td {
        width: 50%;
        padding: 9px;
        vertical-align: top;
        background: #f8fafc;
    }
    .receiving-info-label {
        display: block;
        margin-bottom: 4px;
        color: {{ $receivingTheme['primary_color'] }};
        font-size: 8pt;
        font-weight: 800;
    }
    .receiving-info-value {
        color: {{ $receivingTheme['secondary_color'] }};
        font-size: 9pt;
        font-weight: 800;
    }
    .receiving-summary {
        width: 100%;
        margin: 0 0 12px;
        table-layout: fixed;
        border-spacing: 5px 0;
    }
    .receiving-summary td {
        width: 33.33%;
        padding: 8px;
        background: #f8fafc;
        text-align: center;
    }
    .receiving-summary span { display: block; color: #6b7280; font-size: 7.5pt; }
    .receiving-summary strong {
        display: block;
        margin-top: 3px;
        color: {{ $receivingTheme['secondary_color'] }};
        font-size: 11pt;
    }
    .receiving-difference { color: #b42318; font-weight: 800; }
    .receiving-match { color: #08783e; font-weight: 800; }
</style>
@endpush

@section('print_content')
    <table class="receiving-info" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <span class="receiving-info-label">موقع الإرسال</span>
                <span class="receiving-info-value">{{ $stockReceivingInvoice->sendingLocation?->name ?? '—' }}</span>
            </td>
            <td>
                <span class="receiving-info-label">موقع الاستلام</span>
                <span class="receiving-info-value">{{ $stockReceivingInvoice->receivingLocation?->name ?? '—' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="receiving-info-label">رقم التحويل</span>
                <span class="receiving-info-value">{{ $transfer?->transfer_number ?? '—' }}</span>
            </td>
            <td>
                <span class="receiving-info-label">المستلم</span>
                <span class="receiving-info-value">{{ $stockReceivingInvoice->receivedBy?->display_name ?? 'غير مسجل' }}</span>
            </td>
        </tr>
    </table>

    <table class="receiving-summary" cellpadding="0" cellspacing="0">
        <tr>
            <td><span>إجمالي المطلوب</span><strong>{{ $stockReceivingInvoice->total_items_ordered }}</strong></td>
            <td><span>إجمالي المستلم</span><strong>{{ $stockReceivingInvoice->total_items_received }}</strong></td>
            <td><span>تالف / ناقص</span><strong>{{ $stockReceivingInvoice->total_items_damaged }}</strong></td>
        </tr>
    </table>

    <div class="print-section">
        <div class="print-section-title">تفاصيل المنتجات</div>
        <table class="print-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>#</th><th>المنتج</th><th>SKU</th>
                    <th>المرسل</th><th>المستلم</th><th>الفرق</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfer?->items ?? [] as $item)
                    @php $difference = (float) $item->sent_quantity - (float) $item->received_quantity; @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->product?->name_ar ?: ($item->product?->name ?? '—') }}</td>
                        <td dir="ltr">{{ $item->product?->sku ?? '—' }}</td>
                        <td>{{ $item->sent_quantity }}</td>
                        <td>{{ $item->received_quantity }}</td>
                        <td class="{{ $difference == 0 ? 'receiving-match' : 'receiving-difference' }}">
                            {{ $difference == 0 ? 'مطابق' : number_format($difference, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="print-empty">لا توجد بنود مسجلة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($stockReceivingInvoice->notes)
        <div class="print-soft-box"><strong>ملاحظات:</strong> {{ $stockReceivingInvoice->notes }}</div>
    @endif
@endsection

@push('print_scripts')
@unless($pdfMode ?? false)
<script>
    window.addEventListener('load', function () { window.print(); });
</script>
@endunless
@endpush
