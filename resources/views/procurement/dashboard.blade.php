@extends('layouts.app')
@section('title', 'لوحة المشتريات والموردين')
@section('content')
    <div class="page-actions">
        <div class="page-actions-title">لوحة المشتريات والموردين</div>
        <div class="action-btns"><a class="btn btn-outline" href="{{ route('procurement.reports.index') }}">التقارير</a>
            @can('purchase_orders.create')
                <a class="btn btn-gold" href="{{ route('purchase-orders.create') }}">أمر شراء جديد</a>
            @endcan
        </div>
    </div>
    <div class="stats-grid">
        <div class="stat-card"><span class="stat-label">أوامر شراء مفتوحة</span><strong
                class="stat-value">{{ $stats['openOrders'] }}</strong></div>
        <div class="stat-card"><span class="stat-label">استلامات الشهر (بالعملة الأساسية)</span><strong
                class="stat-value">{{ number_format($stats['receiptsThisMonth'], 2) }}</strong></div>
        <div class="stat-card"><span class="stat-label">مرتجعات الشهر (بالعملة الأساسية)</span><strong
                class="stat-value">{{ number_format($stats['returnsThisMonth'], 2) }}</strong></div>
        <div class="stat-card"><span class="stat-label">ذمم الموردين (بالعملة الأساسية)</span><strong
                class="stat-value">{{ number_format($stats['outstanding'], 2) }}</strong></div>
        <div class="stat-card"><span class="stat-label">فواتير متأخرة</span><strong
                class="stat-value">{{ $stats['overdue'] }}</strong></div>
        <div class="stat-card"><span class="stat-label">قيمة المخزون (بالعملة الأساسية)</span><strong
                class="stat-value">{{ number_format($stats['inventoryValue'], 2) }}</strong></div>
    </div>
    <div class="dashboard-row" style="margin-top:1rem">
        <div class="card">
            <div class="card-header"><span class="card-title">حالة أوامر الشراء</span></div>
            <div class="card-body">
                @forelse($ordersByStatus as $status => $total)
                    <div class="detail-row"><span class="detail-label">@statusArabic($status)</span><span
                        class="detail-value">{{ $total }}</span></div>@empty<div class="empty-state-sm">لا توجد
                        أوامر شراء.</div>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">أعلى الموردين هذا الشهر</span></div>
            <div class="card-body">
                @forelse($topSuppliers as $supplier)
                    <div class="detail-row"><span class="detail-label">{{ $supplier->name }}</span><span
                        class="detail-value">{{ number_format($supplier->total, 2) }}</span></div>@empty<div
                        class="empty-state-sm">لا توجد فواتير ضمن الفترة.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="card" style="margin-top:1rem">
        <div class="card-header"><span class="card-title">أحدث سندات الاستلام</span><a class="btn btn-ghost btn-sm"
                href="{{ route('goods-receipts.index') }}">كل السندات</a></div>
        <div class="card-body">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>السند</th>
                            <th>المورد</th>
                            <th>الموقع</th>
                            <th>التاريخ</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentReceipts as $receipt)
                            <tr>
                                <td><a
                                        href="{{ route('goods-receipts.show', $receipt) }}">{{ $receipt->receipt_number }}</a>
                                </td>
                                <td>{{ $receipt->supplier?->name }}</td>
                                <td>{{ $receipt->location?->name }}</td>
                                <td>{{ $receipt->received_at?->format('Y/m/d') }}</td>
                                <td>{{ $receipt->statusLabel() }}</td>
                        </tr>@empty<tr>
                                <td colspan="5">
                                    <div class="empty-state-sm">لا توجد سندات استلام.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
