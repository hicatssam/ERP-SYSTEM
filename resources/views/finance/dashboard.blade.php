@extends('layouts.app')
@section('title', 'لوحة المالية')
@section('page-title', 'لوحة المالية')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">لوحة المالية</h1>
        <p class="page-subheading">{{ now()->format('F Y') }} — المبيعات والتحصيلات لهذا الشهر، والمستحقات الحالية</p>
    </div>
    <div class="page-header-actions">
        @if(isset($locations) && $locations->count() > 0)
        <form method="GET" action="{{ route('financial.dashboard') }}" style="display:flex;gap:.5rem;align-items:center">
            <select name="location_id" class="form-input" style="max-width:180px;padding:.45rem .75rem;font-size:.83rem" onchange="this.form.submit()">
                <option value="">جميع الفروع</option>
                @foreach($locations as $loc)
                <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                @endforeach
            </select>
        </form>
        @endif
        @can('reports.view')
        <a href="{{ route('reports.show', 'monthly-sales') }}" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
            التقرير المفصّل
        </a>
        @endcan
    </div>
</div>

{{-- ── KPI Grid ── --}}
<div class="stats-grid">
    <div class="stat-card stat-green">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div class="stat-info">
            <div class="stat-value">{{ $currencySymbol }}{{ number_format($data['netSales'] ?? 0, 2) }}</div>
            <div class="stat-label">المبيعات بعد الخصم والضريبة</div>
        </div>
    </div>
    <div class="stat-card stat-gold">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></div>
        <div class="stat-info">
            <div class="stat-value">{{ $currencySymbol }}{{ number_format($data['collections'] ?? 0, 2) }}</div>
            <div class="stat-label">تحصيلات الطلبات والعملاء</div>
        </div>
    </div>
    <div class="stat-card stat-orange">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 12l-4 4-4-4M12 8v8"/></svg></div>
        <div class="stat-info">
            <div class="stat-value">{{ $currencySymbol }}{{ number_format($data['outstanding'] ?? 0, 2) }}</div>
            <div class="stat-label">المستحقات الحالية لجميع الأشهر</div>
        </div>
    </div>
    <div class="stat-card stat-blue">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div class="stat-info">
            <div class="stat-value">{{ $currencySymbol }}{{ number_format($data['pendingVerification'] ?? 0, 2) }}</div>
            <div class="stat-label">بانتظار التحقق</div>
        </div>
    </div>
    <div class="stat-card stat-green">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg></div>
        <div class="stat-info">
            <div class="stat-value">{{ number_format($data['invoiceCount'] ?? 0) }}</div>
            <div class="stat-label">فواتير هذا الشهر</div>
        </div>
    </div>
    <div class="stat-card stat-gold">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg></div>
        <div class="stat-info">
            <div class="stat-value">{{ $currencySymbol }}{{ number_format($data['grossSales'] ?? 0, 2) }}</div>
            <div class="stat-label">المبيعات قبل الخصم والضريبة</div>
        </div>
    </div>
</div>

@can('accounting.employee_accounts.view')
<div class="card" style="margin-top:1rem">
    <div class="card-header"><span class="card-title">ذمم مشتريات الموظفين</span></div>
    <div class="card-body">
        <div class="kpi-grid">
            <div class="kpi-item"><div class="kpi-label">مشتريات هذا الشهر</div><div class="kpi-value">{{ $currencySymbol }}{{ number_format($data['employeeSales'] ?? 0, 2) }}</div></div>
            <div class="kpi-item"><div class="kpi-label">سداد هذا الشهر</div><div class="kpi-value green">{{ $currencySymbol }}{{ number_format($data['employeeCollections'] ?? 0, 2) }}</div></div>
            <div class="kpi-item"><div class="kpi-label">المتبقي على الموظفين</div><div class="kpi-value orange">{{ $currencySymbol }}{{ number_format($data['employeeOutstanding'] ?? 0, 2) }}</div></div>
        </div>
        <a href="{{ route('accounting.employee-purchases.index') }}" class="btn btn-outline btn-sm" style="margin-top:1rem">عرض حسابات الموظفين</a>
    </div>
</div>
@endcan

{{-- ── Financial activity and current receivables ── --}}
<div class="card" style="margin-top:1.25rem">
    <div class="card-header">
        <span class="card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
            حركة هذا الشهر والمستحقات الحالية
        </span>
    </div>
    <div class="card-body" style="padding-bottom:.5rem">
        <div id="financeChart" style="min-height:260px"></div>
    </div>
</div>

{{-- ── Summary + Quick Links ── --}}
<div class="dashboard-row" style="margin-top:1rem">
    <div class="card">
        <div class="card-header"><span class="card-title">ملخص مالي تفصيلي</span></div>
        <div class="card-body">
            <div class="kpi-grid">
                <div class="kpi-item">
                    <div class="kpi-label">المبيعات الإجمالية</div>
                    <div class="kpi-value gold">{{ $currencySymbol }}{{ number_format($data['grossSales'] ?? 0, 2) }}</div>
                </div>
                <div class="kpi-item">
                    <div class="kpi-label">الخصومات</div>
                    <div class="kpi-value orange">{{ $currencySymbol }}{{ number_format($data['discounts'] ?? 0, 2) }}</div>
                </div>
                <div class="kpi-item">
                    <div class="kpi-label">الضريبة</div>
                    <div class="kpi-value">{{ $currencySymbol }}{{ number_format($data['taxes'] ?? 0, 2) }}</div>
                </div>
                <div class="kpi-item">
                    <div class="kpi-label">الاستردادات</div>
                    <div class="kpi-value orange">{{ $currencySymbol }}{{ number_format($data['refunds'] ?? 0, 2) }}</div>
                </div>
                <div class="kpi-item">
                    <div class="kpi-label">التحصيلات بعد الاسترداد</div>
                    <div class="kpi-value green">{{ $currencySymbol }}{{ number_format($data['netCollections'] ?? 0, 2) }}</div>
                </div>
                <div class="kpi-item">
                    <div class="kpi-label">الفواتير الملغاة</div>
                    <div class="kpi-value">{{ $data['cancelledCount'] ?? 0 }}</div>
                </div>
            </div>

            <p class="page-subheading" style="margin-top:1rem">التحصيلات تشمل دفعات فواتير قديمة؛ والاستردادات تُحسب في تاريخ تنفيذها.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">الأقسام المالية</span></div>
        <div class="card-body" style="display:grid;gap:.625rem">
            @can('financial.cash.view')<a href="{{ route('daily-cash.index') }}" class="btn btn-outline btn-full">مطابقة خزينة الفرع</a>@endcan
            @can('payments.view')<a href="{{ route('payments.index') }}" class="btn btn-outline btn-full">المدفوعات والتحقق</a>@endcan
            @can('expenses.view')<a href="{{ route('costing.expenses.index') }}" class="btn btn-outline btn-full">المصروفات</a>@endcan
            @can('accounting.books.view')<a href="{{ route('accounting.books.vouchers') }}" class="btn btn-outline btn-full">سندات القبض والصرف</a>@endcan
            @can('accounting.reports.view')<a href="{{ route('accounting.books.statements') }}" class="btn btn-outline btn-full">ميزان المراجعة والقوائم</a>@endcan
            @can('invoices.view')
            <a href="{{ route('invoices.index') }}" class="btn btn-outline btn-full">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                الفواتير
            </a>
            @endcan
            @can('financial.periods.view')
            <a href="{{ route('financial-periods.index') }}" class="btn btn-outline btn-full">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                الفترات المالية
            </a>
            @endcan
            @can('financial.reports.view')
                <a href="{{ route('accounting.ledger.index') }}" class="btn btn-outline btn-full">سجل الترحيلات المالية</a>
            @endcan
            @can('reports.view')
            <a href="{{ route('reports.show', 'collections') }}" class="btn btn-outline btn-full">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                تقرير التحصيلات
            </a>
            <a href="{{ route('reports.show', 'outstanding') }}" class="btn btn-outline btn-full">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                تقرير المستحقات
            </a>
            @endcan
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const financeEl = document.getElementById('financeChart');
    if (!financeEl || typeof ApexCharts === 'undefined') return;

    const currency      = @json($currencySymbol);
    const grossSales    = {{ (float)($data['grossSales']    ?? 0) }};
    const collections   = {{ (float)($data['collections']   ?? 0) }};
    const netCollections = {{ (float)($data['netCollections'] ?? 0) }};
    const outstanding   = {{ (float)($data['outstanding']   ?? 0) }};
    const discounts     = {{ (float)($data['discounts']     ?? 0) }};
    const refunds       = {{ (float)($data['refunds']       ?? 0) }};
    const pendingVerif  = {{ (float)($data['pendingVerification'] ?? 0) }};

    new ApexCharts(financeEl, {
        chart: {
            type: 'bar',
            height: 260,
            fontFamily: "'Cairo', sans-serif",
            toolbar: { show: false },
        },
        series: [
            { name: 'المبلغ', data: [grossSales, collections, netCollections, outstanding, discounts, refunds, pendingVerif] }
        ],
        xaxis: {
            categories: ['المبيعات قبل الخصم', 'التحصيلات', 'بعد الاسترداد', 'المستحقات الحالية', 'الخصومات', 'الاستردادات', 'بانتظار التحقق'],
            labels: { style: { colors: '#868E96', fontSize: '12px' } },
        },
        yaxis: {
            labels: { style: { colors: '#868E96', fontSize: '11px' }, formatter: v => currency + Number(v).toLocaleString('ar') }
        },
        colors: ['#FFD700', '#1A7A34', '#B85C00', '#C0392B', '#0A5275'],
        plotOptions: {
            bar: { distributed: true, borderRadius: 6, columnWidth: '50%' }
        },
        legend: { show: false },
        grid: { borderColor: '#F1F3F5', strokeDashArray: 3 },
        dataLabels: { enabled: false },
        tooltip: {
            style: { fontFamily: "'Cairo', sans-serif" },
            y: { formatter: v => currency + ' ' + Number(v).toLocaleString('ar-SA', { minimumFractionDigits: 2 }) }
        },
    }).render();
});
</script>
@endpush
