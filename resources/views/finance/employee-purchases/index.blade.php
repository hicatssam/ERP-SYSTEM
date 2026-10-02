@extends('layouts.app')
@section('title', 'مشتريات الموظفين')
@section('page-title', 'مشتريات الموظفين')

@section('content')
<div class="page-header">
    <div><h1 class="page-heading">مشتريات الموظفين</h1><p class="page-subheading">ذمم مستقلة عن السلف والرواتب، مع متابعة الأقساط والسداد.</p></div>
    @can('accounting.employee_accounts.create')
        <a href="{{ route('accounting.employee-purchases.create', ['location_id' => $locationId]) }}" class="btn btn-primary">صرف منتجات لموظف</a>
    @endcan
</div>

<div class="stats-grid" style="margin-bottom:1rem">
    <div class="stat-card stat-blue"><div class="stat-info"><div class="stat-label">عدد العمليات في التصفية</div><div class="stat-value">{{ number_format($summary->purchase_count ?? 0) }}</div></div></div>
    <div class="stat-card stat-orange"><div class="stat-info"><div class="stat-label">المتبقي على الموظفين</div><div class="stat-value">{{ $baseCurrency?->symbol ?: $baseCurrency?->code }} {{ number_format((float) ($summary->outstanding_total ?? 0), 2) }}</div></div></div>
    <div class="stat-card stat-orange"><div class="stat-info"><div class="stat-label">حسابات لديها قسط متأخر</div><div class="stat-value">{{ number_format($overdueCount) }}</div></div></div>
</div>

<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">بحث وتصفية</span></div><div class="card-body">
    <form method="GET" action="{{ route('accounting.employee-purchases.index') }}" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:end">
        @if(request('employee_id'))<input type="hidden" name="employee_id" value="{{ request('employee_id') }}"><a href="{{ route('accounting.employee-purchases.index') }}" class="btn btn-outline btn-sm">إلغاء تصفية الموظف</a>@endif
        @if($locations->count() > 1)
            <label>الفرع<select name="location_id" class="form-input"><option value="">جميع الفروع</option>
                @foreach($locations as $location)<option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}</option>@endforeach
            </select></label>
        @endif
        <label>رقم العملية أو اسم الموظف<input name="q" class="form-input" value="{{ request('q') }}" maxlength="100"></label>
        <label>الحالة<select name="status" class="form-input"><option value="">جميع الحالات</option><option value="open" @selected(request('status') === 'open')>مفتوحة</option><option value="settled" @selected(request('status') === 'settled')>مسددة</option></select></label>
        <button class="btn btn-outline">عرض</button>
    </form>
</div></div>

<div class="card"><div class="card-header"><span class="card-title">حسابات المنتجات</span></div><div class="card-body">
    <div class="table-wrap"><table class="data-table"><thead><tr><th>العملية</th><th>التاريخ</th><th>الموظف</th><th>الفرع</th><th>الخطة</th><th>الإجمالي</th><th>المسدّد</th><th>المتبقي</th><th>الحالة</th><th></th></tr></thead><tbody>
        @forelse($purchases as $purchase)
            <tr>
                <td>{{ $purchase->number }}</td><td>{{ $purchase->purchased_at?->format('Y-m-d') }}</td>
                <td>{{ $purchase->employee?->full_name }} ({{ $purchase->employee?->employee_number }})</td>
                <td>{{ $purchase->location?->name }}</td><td>{{ $purchase->payment_plan === 'installments' ? 'أقساط' : 'على الحساب' }}</td>
                <td>{{ number_format((float) $purchase->total_amount, 2) }} {{ $purchase->currency?->code }}</td>
                <td>{{ number_format((float) $purchase->paid_amount, 2) }}</td><td>{{ number_format((float) $purchase->outstanding_amount, 2) }}</td>
                <td>{{ $purchase->status === 'settled' ? 'مسددة' : 'مفتوحة' }}</td>
                <td><a href="{{ route('accounting.employee-purchases.show', $purchase) }}" class="btn btn-outline btn-sm">التفاصيل</a></td>
            </tr>
        @empty
            <tr><td colspan="10" style="text-align:center">لا توجد عمليات ضمن هذه التصفية.</td></tr>
        @endforelse
    </tbody></table></div>
    <div style="margin-top:1rem">{{ $purchases->links() }}</div>
</div></div>

<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">أعلى 20 رصيدًا مستحقًا حسب الموظف</span></div><div class="card-body">
    <div class="table-wrap"><table class="data-table"><thead><tr><th>الموظف</th><th>عدد العمليات</th><th>المشتريات</th><th>المدفوع</th><th>المتبقي</th><th></th></tr></thead><tbody>
        @forelse($employeeBalances as $balance)
            <tr><td>{{ $balance->employee?->full_name }} · {{ $balance->employee?->employee_number }}</td><td>{{ $balance->operations_count }}</td>
                <td>{{ number_format((float) $balance->purchases_total, 2) }}</td><td>{{ number_format((float) $balance->paid_total, 2) }}</td><td>{{ number_format((float) $balance->balance_total, 2) }}</td>
                <td><a href="{{ route('accounting.employee-purchases.index', ['employee_id' => $balance->employee_id, 'location_id' => $locationId]) }}" class="btn btn-outline btn-sm">كشف الموظف</a></td></tr>
        @empty<tr><td colspan="6" style="text-align:center">لا توجد حسابات موظفين.</td></tr>@endforelse
    </tbody></table></div>
</div></div>
@endsection
