@extends('layouts.app')
@section('title', 'ميزان المراجعة والقوائم')
@section('page-title', 'ميزان المراجعة والقوائم')
@section('content')
<div class="page-header"><div><h1 class="page-heading">ميزان المراجعة والقوائم</h1><p class="page-subheading">الأرصدة من قيود الدفتر المزدوج المُرحّلة فقط، حتى {{ $to }}، بعملة {{ $report['currency']->code }}.</p></div><button type="button" class="btn btn-outline" onclick="window.print()">طباعة</button></div>
<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">نطاق التقرير</span></div><div class="card-body"><form method="GET" action="{{ route('accounting.books.statements') }}" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:end">
    @if($locations->count() > 1)<label>الفرع<select name="location_id" class="form-input"><option value="">جميع الفروع</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}</option>@endforeach</select></label>@endif
    <label>من<input type="date" name="from" value="{{ $from }}" class="form-input" required></label>
    <label>إلى<input type="date" name="to" value="{{ $to }}" class="form-input" max="{{ today()->toDateString() }}" required></label>
    <button class="btn btn-primary">عرض</button>
</form><p class="page-subheading" style="margin-top:.75rem">الرصيد الافتتاحي = القيود قبل {{ $from }}. الحركة = قيود الفترة. الرصيد الختامي = كل القيود حتى {{ $to }}.</p>
<p class="page-subheading"><strong>حدود التقرير:</strong> ترتبط العمليات الجديدة المدعومة من المبيعات والتحصيلات ومشتريات الموظفين والمصروفات بهذا الدفتر عند توفر فترة مالية وعملة أساس. يوجد {{ $report['unlinked_operations'] }} حركة تشغيلية سابقة أو غير مرحّلة حتى تاريخ التقرير. معاملات الرواتب والموردين والمخزون والأرصدة الافتتاحية لا تظهر تلقائيًا بعد؛ لا تعتمد هذه القوائم كمركز مالي كامل للشركة.</p>
</div></div>
<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">ميزان المراجعة</span></div><div class="card-body"><div class="table-wrap"><table class="data-table"><thead><tr><th>الحساب</th><th>التصنيف</th><th>افتتاحي مدين</th><th>افتتاحي دائن</th><th>حركة مدين</th><th>حركة دائن</th><th>ختامي مدين</th><th>ختامي دائن</th></tr></thead><tbody>
    @forelse($report['rows'] as $row)<tr><td>{{ $row['account']->code }} · {{ $row['account']->name }}</td><td>{{ $row['account']->typeLabel() }}</td>
        @foreach(['opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'] as $key)<td>{{ number_format($row[$key] / 100, 2) }}</td>@endforeach
    </tr>@empty<tr><td colspan="8" style="text-align:center">لا توجد قيود مرحّلة في هذا النطاق.</td></tr>@endforelse
    <tr><th colspan="2">الإجمالي</th>@foreach(['opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'] as $key)<th>{{ number_format($report['trial'][$key] / 100, 2) }}</th>@endforeach</tr>
</tbody></table></div>
@if($report['trial']['closing_debit'] !== $report['trial']['closing_credit'])<p style="color:#b91c1c">تنبيه: ميزان المراجعة غير متوازن، راجع سلامة البيانات قبل الاعتماد.</p>@endif
</div></div>
<div class="dashboard-row">
    <div class="card"><div class="card-header"><span class="card-title">قائمة الدخل · {{ $from }} إلى {{ $to }}</span></div><div class="card-body">
        <div class="table-wrap"><table class="data-table"><tbody>
            @foreach($report['rows'] as $row)@if($row['account']->type === 'revenue')<tr><td>{{ $row['account']->code }} · {{ $row['account']->name }}</td><td>{{ number_format(($row['period_credit'] - $row['period_debit']) / 100, 2) }}</td></tr>@endif @endforeach
            <tr><th>مجموع الإيرادات</th><th>{{ number_format($report['income']['revenue'] / 100, 2) }}</th></tr>
            @foreach($report['rows'] as $row)@if($row['account']->type === 'expense')<tr><td>{{ $row['account']->code }} · {{ $row['account']->name }}</td><td>{{ number_format(($row['period_debit'] - $row['period_credit']) / 100, 2) }}</td></tr>@endif @endforeach
            <tr><th>مجموع المصروفات</th><th>{{ number_format($report['income']['expenses'] / 100, 2) }}</th></tr>
            <tr><th>صافي نتيجة الفترة</th><th>{{ number_format($report['income']['net'] / 100, 2) }} {{ $report['currency']->code }}</th></tr>
        </tbody></table></div>
    </div></div>
    <div class="card"><div class="card-header"><span class="card-title">المركز المالي · حتى {{ $to }}</span></div><div class="card-body"><div class="table-wrap"><table class="data-table"><tbody>
        <tr><th>الأصول</th><th>{{ number_format($report['position']['assets'] / 100, 2) }}</th></tr>
        <tr><td>الالتزامات</td><td>{{ number_format($report['position']['liabilities'] / 100, 2) }}</td></tr>
        <tr><td>حقوق الملكية في الدفتر</td><td>{{ number_format($report['position']['equity'] / 100, 2) }}</td></tr>
        <tr><td>الأرباح أو الخسائر التراكمية في الدفتر</td><td>{{ number_format($report['position']['cumulative_earnings'] / 100, 2) }}</td></tr>
        <tr><th>الالتزامات وحقوق الملكية والنتيجة</th><th>{{ number_format($report['position']['liabilities_and_equity'] / 100, 2) }}</th></tr>
        <tr><th>الفرق المحاسبي</th><th>{{ number_format($report['position']['difference'] / 100, 2) }}</th></tr>
    </tbody></table></div></div></div>
</div>
@endsection
