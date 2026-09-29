@php
    $customerStatementTheme = app(\App\Services\PrintThemeService::class)->settings();
    $customerStatementCurrency = $customerStatementTheme['currency_symbol'];
@endphp

@extends('layouts.print')

@section('pdf_mode', '1')
@section('paper_orientation', 'landscape')
@section('document_title', 'كشف حساب العميل')
@section('document_date', now()->format('Y-m-d H:i'))
@section('document_subtitle', $customer->name)
@section('document_meta', 'النطاق: ' . ($selectedLocation?->name ?? 'جميع الفروع'))
@section('signature_right', 'إعداد الكشف')
@section('signature_left', 'اعتماد الإدارة')

@push('print_styles')
<style>
    .customer-statement-meta { width: 100%; margin-bottom: 10px; }
    .customer-statement-meta td {
        padding: 7px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        vertical-align: top;
    }
    .customer-statement-amount { direction: ltr; text-align: left; white-space: nowrap; }
</style>
@endpush

@section('print_content')
    <table class="customer-statement-meta" cellpadding="0" cellspacing="0">
        <tr>
            <td>الهاتف: {{ $customer->phone ?: '—' }}</td>
            <td>نوع العميل: {{ $customer->typeLabel() }}</td>
            <td>الفرع: {{ $selectedLocation?->name ?? 'جميع الفروع' }}</td>
        </tr>
        <tr>
            <td>من: {{ request('date_from') ?: 'بداية التعامل' }}</td>
            <td>إلى: {{ request('date_to') ?: 'تاريخ الطباعة' }}</td>
            <td>تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}</td>
        </tr>
    </table>

    <table class="print-summary" cellpadding="0" cellspacing="0">
        <tr>
            <td><div class="print-summary-label">الرصيد السابق</div><strong class="print-summary-value">{{ number_format((float) $statement['opening_balance'], 2) }} {{ $customerStatementCurrency }}</strong></td>
            <td><div class="print-summary-label">مدين الفترة</div><strong class="print-summary-value">{{ number_format((float) $statement['period_debit'], 2) }} {{ $customerStatementCurrency }}</strong></td>
            <td><div class="print-summary-label">دائن الفترة</div><strong class="print-summary-value">{{ number_format((float) $statement['period_credit'], 2) }} {{ $customerStatementCurrency }}</strong></td>
            <td><div class="print-summary-label">الرصيد الختامي</div><strong class="print-summary-value">{{ number_format((float) $statement['closing_balance'], 2) }} {{ $customerStatementCurrency }}</strong></td>
            <td><div class="print-summary-label">المتأخر الحالي</div><strong class="print-summary-value">{{ number_format((float) $summary['overdue'], 2) }} {{ $customerStatementCurrency }}</strong></td>
        </tr>
    </table>

    <div class="print-section">
        <div class="print-section-title">تفاصيل الحركات</div>
        <table class="print-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>التاريخ</th><th>الفرع</th><th>الحركة</th><th>المرجع</th>
                    <th>البيان</th><th>مدين</th><th>دائن</th><th>الرصيد</th>
                </tr>
            </thead>
            <tbody>
                @if(abs((float) $statement['opening_balance']) > 0.0001)
                    <tr>
                        <td colspan="5"><strong>الرصيد الافتتاحي</strong></td>
                        <td></td><td></td>
                        <td class="customer-statement-amount">{{ number_format((float) $statement['opening_balance'], 2) }} {{ $customerStatementCurrency }}</td>
                    </tr>
                @endif
                @forelse($statement['rows'] as $row)
                    <tr>
                        <td>{{ $row['date']?->format('Y-m-d') }}</td>
                        <td>{{ $row['location'] }}</td>
                        <td>{{ $row['type_label'] }}</td>
                        <td>{{ $row['reference'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="customer-statement-amount">{{ (float) $row['debit'] > 0 ? number_format((float) $row['debit'], 2) . ' ' . $customerStatementCurrency : '—' }}</td>
                        <td class="customer-statement-amount">{{ (float) $row['credit'] > 0 ? number_format((float) $row['credit'], 2) . ' ' . $customerStatementCurrency : '—' }}</td>
                        <td class="customer-statement-amount"><strong>{{ number_format((float) $row['balance'], 2) }} {{ $customerStatementCurrency }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="print-empty">لا توجد حركات ضمن الفترة المحددة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
