@php
    $statementCurrency = $selectedCurrency?->symbol ?: ($selectedCurrency?->code ?? '');
@endphp

@extends('layouts.print')

@section('document_title', 'كشف حساب مورد')
@section('document_number', $supplier->supplier_code ?? '')
@section('document_date', now()->format('Y-m-d H:i'))
@section('document_subtitle', $supplier->name)
@section('document_meta', 'العملة: ' . ($selectedCurrency?->code ?? '—'))
@section('signature_right', 'إعداد الكشف')
@section('signature_left', 'اعتماد الإدارة')

@push('print_styles')
<style>
    .statement-info {
        width: 100%;
        border-spacing: 6px 0;
        margin-bottom: 12px;
        table-layout: fixed;
    }
    .statement-info td {
        padding: 9px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        vertical-align: top;
    }
    .statement-info strong { display: block; margin-bottom: 5px; }
    .statement-info p { margin: 3px 0; }
    .statement-number { direction: ltr; text-align: left; white-space: nowrap; }
    .statement-note { margin-top: 12px; color: #6b7280; font-size: 8pt; }
</style>
@endpush

@section('print_content')
    <table class="statement-info" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <strong>بيانات المورد</strong>
                <p>الاسم: {{ $supplier->name }}</p>
                @if($supplier->company_name)<p>الشركة: {{ $supplier->company_name }}</p>@endif
                @if($supplier->contact_person)<p>مسؤول التواصل: {{ $supplier->contact_person }}</p>@endif
                @if($supplier->phone)<p>الهاتف: {{ $supplier->phone }}</p>@endif
                @if($supplier->email)<p>البريد الإلكتروني: {{ $supplier->email }}</p>@endif
                @if($supplier->address)<p>العنوان: {{ $supplier->address }}</p>@endif
                @if($supplier->tax_number)<p>الرقم الضريبي: {{ $supplier->tax_number }}</p>@endif
                @if($supplier->commercial_registration)
                    <p>السجل التجاري: {{ $supplier->commercial_registration }}</p>
                @endif
            </td>
            <td>
                <strong>نطاق الكشف</strong>
                <p>العملة: {{ $selectedCurrency?->code ?? '—' }}</p>
                <p>من: {{ $from ? \Carbon\Carbon::parse($from)->format('Y/m/d') : 'بداية الحساب' }}</p>
                <p>إلى: {{ $to ? \Carbon\Carbon::parse($to)->format('Y/m/d') : 'حتى الآن' }}</p>
                <p>عدد الحركات: {{ number_format(collect($rows)->count()) }}</p>
            </td>
        </tr>
    </table>

    <div class="print-section">
        <div class="print-section-title">تفاصيل الحركات</div>
        <table class="print-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>التاريخ</th>
                    <th>المرجع</th>
                    <th>البيان</th>
                    <th>مدين</th>
                    <th>دائن</th>
                    <th>الرصيد</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $row['date']?->format('Y/m/d') ?? '—' }}</td>
                        <td>{{ $row['reference'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="statement-number">
                            {{ (float) $row['debit'] ? number_format((float) $row['debit'], 2) . ' ' . $statementCurrency : '—' }}
                        </td>
                        <td class="statement-number">
                            {{ (float) $row['credit'] ? number_format((float) $row['credit'], 2) . ' ' . $statementCurrency : '—' }}
                        </td>
                        <td class="statement-number">
                            {{ number_format((float) $row['balance'], 2) }} {{ $statementCurrency }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="print-empty">لا توجد حركات ضمن الفترة المحددة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <table class="print-summary" cellpadding="0" cellspacing="0">
        <tr>
            <td><div class="print-summary-label">إجمالي المدين</div><strong class="print-summary-value">{{ number_format((float) $totalDebit, 2) }} {{ $statementCurrency }}</strong></td>
            <td><div class="print-summary-label">إجمالي الدائن</div><strong class="print-summary-value">{{ number_format((float) $totalCredit, 2) }} {{ $statementCurrency }}</strong></td>
            <td><div class="print-summary-label">الرصيد النهائي</div><strong class="print-summary-value">{{ number_format((float) $finalBalance, 2) }} {{ $statementCurrency }}</strong></td>
        </tr>
    </table>

    <div class="statement-note">
        هذا الكشف يعرض الحركات المسجلة ضمن العملة والفترة المحددتين حسب تسلسلها في النظام.
    </div>
@endsection
