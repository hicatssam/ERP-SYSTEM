@extends('layouts.print')
@section('document_title','تقرير صلاحية المخزون')
@section('document_number','EXP-'.now()->format('Ymd-His'))
@section('document_date',now()->format('Y-m-d'))
@section('document_subtitle','متابعة الدُفعات وتواريخ الصلاحية')
@section('signature_right','مسؤول المخزون')
@section('signature_left','اعتماد الإدارة')
@section('print_content')
<table class="print-summary" cellpadding="0" cellspacing="0"><tr><td><div class="print-summary-label">منتهي</div><div class="print-summary-value print-debit">{{ $summary['expired'] }}</div></td><td><div class="print-summary-label">7 أيام</div><div class="print-summary-value">{{ $summary['within_7_days'] }}</div></td><td><div class="print-summary-label">30 يوم</div><div class="print-summary-value">{{ $summary['within_30_days'] }}</div></td><td><div class="print-summary-label">60 يوم</div><div class="print-summary-value">{{ $summary['within_60_days'] }}</div></td></tr></table>
<div class="print-section"><div class="print-section-title">الدُفعات</div><table class="print-table"><thead><tr><th>المنتج</th><th>الموقع</th><th>Batch</th><th>الإنتاج</th><th>الصلاحية</th><th>الأيام</th><th>المتبقي</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row->product_name }}</td><td>{{ $row->location_name }}</td><td>{{ $row->batch_number ?: '—' }}</td><td>{{ $row->manufacturing_date ?: '—' }}</td><td>{{ $row->expiry_date }}</td><td class="{{ $row->days_left<=7?'print-debit':'' }}">{{ $row->days_left }}</td><td>{{ number_format((float)$row->available_quantity,3) }}</td></tr>@empty<tr><td colspan="7" class="print-empty">لا توجد بيانات.</td></tr>@endforelse</tbody></table></div>
@endsection
