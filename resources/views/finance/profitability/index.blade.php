@extends('layouts.app')
@section('title','الربحية والتكلفة')
@section('page-title','الربحية والتكلفة')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">الربحية والتكلفة</h1>
        <p class="page-subheading">صافي المبيعات، تكلفة البضاعة المباعة، المصروفات، وجودة بيانات الربحية.</p>
    </div>
    <div class="page-header-actions">
        @can('expenses.view')
            <a class="btn btn-outline" href="{{ route('costing.expenses.index') }}">المصروفات</a>
        @endcan
        @can('expense_categories.manage')
            <a class="btn btn-ghost" href="{{ route('costing.expense-categories.index') }}">تصنيفات المصروفات</a>
        @endcan
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-body">
        <form method="GET" class="filter-grid">
            @if($locations->isNotEmpty())
                <div class="filter-group">
                    <label class="filter-label">الموقع</label>
                    <select class="form-input" name="location_id">
                        <option value="">كل المواقع</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((int)$locationId===(int)$location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="filter-group"><label class="filter-label">من</label><input class="form-input" type="date" name="date_from" value="{{ $from }}"></div>
            <div class="filter-group"><label class="filter-label">إلى</label><input class="form-input" type="date" name="date_to" value="{{ $to }}"></div>
            <div class="filter-group" style="justify-content:flex-end"><label class="filter-label">&nbsp;</label><button class="btn btn-gold">تطبيق</button></div>
        </form>
    </div>
</div>

@if(!$summary['is_cost_complete'])
    <div class="alert alert-warning" style="margin-bottom:1rem">
        <strong>تنبيه جودة التكلفة:</strong>
        يوجد {{ number_format($summary['missing_cost_items']) }} سطر مبيعات بلا Cost Snapshot موثوق،
        بقيمة صافي إيراد تقريبية ₪{{ number_format($summary['missing_cost_revenue'],2) }}.
        تغطية تكلفة الطلبات العادية {{ number_format($summary['cost_coverage_percent'],1) }}%.
    </div>
@endif

@if(!$summary['is_scope_complete'])
    <div class="alert alert-warning" style="margin-bottom:1rem">
        <strong>تنبيه نطاق الربحية:</strong>
        يوجد ₪{{ number_format($summary['excluded_sales_revenue'],2) }} من المبيعات النشطة خارج نطاق Cost Snapshot الحالي
        (مثل أنواع طلبات لا تستخدم order_items العادية). لا يتم افتراض تكلفة صفر لها.
    </div>
@endif

@if(!$summary['is_profit_reliable'])
    <div class="alert alert-info" style="margin-bottom:1rem">
        الربح التشغيلي أدناه <strong>تقديري/جزئي</strong> إلى أن تصبح تغطية التكلفة ونطاق المبيعات مكتملين.
    </div>
@endif

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1rem">
    <div class="stat-card"><div class="stat-info"><div class="stat-label">صافي المبيعات</div><div class="stat-value">₪{{ number_format($summary['net_sales'],2) }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">COGS الموثوق</div><div class="stat-value">₪{{ number_format($summary['cogs'],2) }}</div><div class="stat-label">تغطية {{ number_format($summary['cost_coverage_percent'],1) }}%</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">الربح الإجمالي المعروف</div><div class="stat-value">₪{{ number_format($summary['gross_profit_costed'],2) }}</div><div class="stat-label">هامش {{ number_format($summary['gross_margin_costed'],1) }}%</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">{{ $summary['is_profit_reliable'] ? 'الربح التشغيلي' : 'الربح التشغيلي التقديري' }}</div><div class="stat-value">₪{{ number_format($summary['operating_profit'],2) }}</div><div class="stat-label">هامش {{ number_format($summary['operating_margin'],1) }}%</div></div></div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1rem">
    <div class="stat-card"><div class="stat-info"><div class="stat-label">مبيعات قابلة لحساب COGS</div><div class="stat-value">₪{{ number_format($summary['costable_net_sales'],2) }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">إيراد بتكلفة موثوقة</div><div class="stat-value">₪{{ number_format($summary['costed_net_sales'],2) }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">مصروفات تشغيلية مرحّلة</div><div class="stat-value">₪{{ number_format($summary['operating_expenses'],2) }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">مبيعات خارج نطاق COGS</div><div class="stat-value">₪{{ number_format($summary['excluded_sales_revenue'],2) }}</div></div></div>
</div>

@if($locationBreakdown->count() > 1)
<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">الربحية حسب الموقع</span></div>
    <div class="table-wrap" style="border:0;border-radius:0">
        <table class="data-table">
            <thead><tr><th>الموقع</th><th>صافي المبيعات</th><th>COGS</th><th>المصروفات</th><th>الربح التشغيلي</th><th>تغطية التكلفة</th><th>الموثوقية</th></tr></thead>
            <tbody>
            @foreach($locationBreakdown as $row)
                <tr>
                    <td>{{ $row->location_name }}</td>
                    <td>₪{{ number_format((float)$row->net_sales,2) }}</td>
                    <td>₪{{ number_format((float)$row->cogs,2) }}</td>
                    <td>₪{{ number_format((float)$row->operating_expenses,2) }}</td>
                    <td>₪{{ number_format((float)$row->operating_profit,2) }}</td>
                    <td>{{ number_format((float)$row->cost_coverage_percent,1) }}%</td>
                    <td>{{ $row->is_profit_reliable ? 'مكتملة' : 'جزئية' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">ربحية المنتجات — التكلفة المعروفة فقط</span></div>
    <div class="table-wrap" style="border:0;border-radius:0">
        <table class="data-table">
            <thead><tr><th>المنتج</th><th>الكمية</th><th>صافي الإيراد</th><th>إيراد بتكلفة</th><th>COGS</th><th>الربح المعروف</th><th>الهامش</th><th>التغطية</th></tr></thead>
            <tbody>
            @forelse($products as $row)
                <tr>
                    <td>{{ $row->product_name }}</td>
                    <td>{{ number_format((float)$row->qty,3) }}</td>
                    <td>₪{{ number_format((float)$row->revenue,2) }}</td>
                    <td>₪{{ number_format((float)$row->costed_revenue,2) }}</td>
                    <td>₪{{ number_format((float)$row->cogs,2) }}</td>
                    <td>₪{{ number_format((float)$row->profit,2) }}</td>
                    <td>{{ number_format((float)$row->margin,1) }}%</td>
                    <td>{{ number_format((float)$row->coverage_percent,1) }}%@if((int)$row->missing_cost_items > 0) <small>({{ (int)$row->missing_cost_items }} ناقص)</small>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8">لا توجد بيانات.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">المصروفات حسب التصنيف</span></div>
    <div class="table-wrap" style="border:0;border-radius:0">
        <table class="data-table">
            <thead><tr><th>التصنيف</th><th>الفئة</th><th>الصافي بعد العكس</th></tr></thead>
            <tbody>
            @forelse($expenseBreakdown as $row)
                <tr><td>{{ $row->name }}</td><td>{{ $row->classification_label }}</td><td>₪{{ number_format((float)$row->amount,2) }}</td></tr>
            @empty
                <tr><td colspan="3">لا توجد مصروفات مرحّلة في الفترة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@can('costing.backfill')
<div class="card">
    <div class="card-header"><span class="card-title">معالجة التكلفة التاريخية</span></div>
    <div class="card-body">
        <p style="color:var(--text-muted)">يسترجع Cost Snapshot فقط من حركات المخزون المرتبطة بالطلبات ضمن الفترة. لا يخمّن تكلفة غير موجودة ولا يستبدل Snapshot قائم.</p>
        <form method="POST" action="{{ route('costing.backfill') }}" onsubmit="return confirm('سيتم فحص الطلبات التاريخية واسترجاع التكلفة الموثوقة فقط. متابعة؟')">
            @csrf
            @if($locationId)<input type="hidden" name="location_id" value="{{ $locationId }}">@endif
            <input type="hidden" name="date_from" value="{{ $from }}">
            <input type="hidden" name="date_to" value="{{ $to }}">
            <button class="btn btn-outline">استرجاع التكاليف المفقودة للفترة</button>
        </form>
    </div>
</div>
@endcan
@endsection
