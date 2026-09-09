@extends('layouts.app')
@section('title', 'مرتجعات الموردين')
@section('content')
    @include('procurement.partials.flash')
    <div class="page-actions">
        <div class="page-actions-title">مرتجعات الموردين</div>
        @can('purchase_returns.create')
            <a class="btn btn-gold" href="{{ route('purchase-returns.create') }}">إنشاء مرتجع مورد</a>
        @endcan
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>رقم المرتجع</th>
                    <th>المورد</th>
                    <th>سند الاستلام</th>
                    <th>الموقع</th>
                    <th>القيمة</th>
                    <th>الحالة</th>
                    <th>الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($returns as $return)
                    <tr>
                        <td><strong>{{ $return->return_number }}</strong><br><small>{{ $return->items_count }} بنود</small>
                        </td>
                        <td>{{ $return->supplier?->name }}</td>
                        <td>{{ $return->goodsReceipt?->receipt_number }}</td>
                        <td>{{ $return->location?->name }}</td>
                        <td>{{ number_format($return->grand_total, 2) }} {{ $return->currency?->displayName() ?? 'غير محددة' }}</td>
                        <td>{{ $return->statusLabel() }}</td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route('purchase-returns.show', $return) }}">عرض</a></td>
                </tr>@empty<tr>
                        <td colspan="7">
                            <div class="empty-state-sm">لا توجد مرتجعات موردين.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:1rem">{{ $returns->links() }}</div>
@endsection
