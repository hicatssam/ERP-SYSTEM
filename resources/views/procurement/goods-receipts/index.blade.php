@extends('layouts.app')
@section('title', 'سندات استلام المشتريات')
@section('content')
    @include('procurement.partials.flash')
    <div class="page-actions">
        <div class="page-actions-title">سندات استلام المشتريات</div>
        @can('goods_receipts.create')
            <a class="btn btn-gold" href="{{ route('goods-receipts.create') }}">إنشاء سند استلام</a>
        @endcan
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>السند</th>
                    <th>أمر الشراء</th>
                    <th>المورد</th>
                    <th>الموقع</th>
                    <th>تاريخ الاستلام</th>
                    <th>الحالة</th>
                    <th>الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipts as $receipt)
                    <tr>
                        <td><strong>{{ $receipt->receipt_number }}</strong><br><small>{{ $receipt->items_count }}
                                بنود</small></td>
                        <td>{{ $receipt->purchaseOrder?->purchase_order_number }}</td>
                        <td>{{ $receipt->supplier?->name }}</td>
                        <td>{{ $receipt->location?->name }}</td>
                        <td>{{ $receipt->received_at?->format('Y/m/d H:i') }}</td>
                        <td>{{ $receipt->statusLabel() }}</td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route('goods-receipts.show', $receipt) }}">عرض</a></td>
                </tr>@empty<tr>
                        <td colspan="7">
                            <div class="empty-state-sm">لا توجد سندات استلام.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:1rem">{{ $receipts->links() }}</div>
@endsection
