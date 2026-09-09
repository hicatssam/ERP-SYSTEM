@extends('layouts.app')
@section('title', 'فواتير الموردين')
@section('content')
    @include('procurement.partials.flash')
    <div class="page-actions">
        <div class="page-actions-title">فواتير الموردين</div>
        @can('supplier_invoices.create')
            <a class="btn btn-gold" href="{{ route('supplier-invoices.create') }}">تسجيل فاتورة مورد</a>
        @endcan
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>المورد</th>
                    <th>الموقع</th>
                    <th>التاريخ/الاستحقاق</th>
                    <th>الإجمالي</th>
                    <th>المتبقي</th>
                    <th>الحالة</th>
                    <th>الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    <tr>
                        <td><strong>{{ $invoice->invoice_number }}</strong></td>
                        <td>{{ $invoice->supplier?->name }}</td>
                        <td>{{ $invoice->location?->name }}</td>
                        <td>{{ $invoice->invoice_date?->format('Y/m/d') }}<br><small>{{ $invoice->due_date?->format('Y/m/d') }}</small>
                        </td>
                        <td>{{ number_format($invoice->grand_total, 2) }} {{ $invoice->currency?->displayName() }}</td>
                        <td>{{ number_format($invoice->remaining_amount, 2) }}</td>
                        <td>{{ $invoice->statusLabel() }}</td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route('supplier-invoices.show', $invoice) }}">عرض</a>
                        </td>
                </tr>@empty<tr>
                        <td colspan="8">
                            <div class="empty-state-sm">لا توجد فواتير موردين.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:1rem">{{ $invoices->links() }}</div>
@endsection
