@extends('layouts.app')

@section('title', 'تقارير الرواتب')

@section('content')
<style>
.pr-page{max-width:1280px;margin:0 auto}.pr-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem}.pr-filters{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}.pr-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.8rem;margin:1rem 0}.pr-stat{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:1rem}.pr-stat small{display:block;color:var(--text-muted)}.pr-stat strong{display:block;margin-top:.35rem;font-size:1.25rem}.pr-table{width:100%;border-collapse:collapse}.pr-table th,.pr-table td{padding:.8rem;border-bottom:1px solid var(--border);text-align:right;white-space:nowrap}.pr-table th{font-size:.72rem;color:var(--text-muted);background:color-mix(in srgb,var(--surface) 88%,var(--background))}.pr-table td{font-size:.8rem}@media(max-width:900px){.pr-filters,.pr-stats{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.pr-head{flex-direction:column}.pr-filters,.pr-stats{grid-template-columns:1fr}}
</style>

<div class="pr-page">
    <div class="pr-head">
        <div>
            <h1 class="page-heading">تقارير الرواتب</h1>
            <p class="page-subheading">تحليل الرواتب حسب الدورة والموظف والموقع والحالة.</p>
        </div>
        <div style="display:flex;gap:.6rem">
            <a class="btn btn-ghost" href="{{ route('payroll.index') }}">الرواتب</a>
            <a class="btn btn-outline" href="{{ route('payroll.reports.csv', request()->query()) }}">تصدير CSV</a>
        </div>
    </div>

    <form method="GET" class="card">
        <div class="card-body">
            <div class="pr-filters">
                <div class="form-group">
                    <label class="form-label">الدورة</label>
                    <select class="form-input" name="period_id">
                        <option value="">كل الدورات</option>
                        @foreach($periods as $period)
                            <option value="{{ $period->id }}" @selected((int)request('period_id') === $period->id)>{{ $period->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الموظف</label>
                    <select class="form-input" name="employee_id">
                        <option value="">كل الموظفين</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int)request('employee_id') === $employee->id)>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الموقع</label>
                    <select class="form-input" name="location_id">
                        <option value="">كل المواقع</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((int)request('location_id') === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الحالة</label>
                    <select class="form-input" name="status">
                        <option value="">كل الحالات</option>
                        @foreach(['calculated'=>'محتسب','approved'=>'معتمد','paid'=>'مدفوع'] as $value=>$label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:.6rem">
                <a class="btn btn-ghost" href="{{ route('payroll.reports.index') }}">إعادة ضبط</a>
                <button class="btn btn-gold" type="submit">تطبيق</button>
            </div>
        </div>
    </form>

    <div class="pr-stats">
        <div class="pr-stat"><small>الموظفون</small><strong>{{ $summary['employees'] }}</strong></div>
        <div class="pr-stat"><small>إجمالي الرواتب</small><strong>{{ number_format($summary['gross'],2) }}</strong></div>
        <div class="pr-stat"><small>الخصومات</small><strong>{{ number_format($summary['deductions'],2) }}</strong></div>
        <div class="pr-stat"><small>صافي الرواتب</small><strong>{{ number_format($summary['net'],2) }}</strong></div>
        <div class="pr-stat"><small>المتبقي</small><strong>{{ number_format($summary['payable'],2) }}</strong></div>
    </div>

    <div class="card">
        <div class="card-body" style="padding:0;overflow:auto">
            <table class="pr-table">
                <thead>
                    <tr><th>الدورة</th><th>الموظف</th><th>الأساسي</th><th>بدلات</th><th>مكافآت</th><th>خصومات</th><th>الصافي</th><th>المدفوع</th><th>المتبقي</th><th>الحالة</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->period?->name }}</td>
                            <td>{{ $row->employee?->full_name }}</td>
                            <td>{{ number_format((float)$row->base_salary,2) }}</td>
                            <td>{{ number_format((float)$row->allowances_total,2) }}</td>
                            <td>{{ number_format((float)$row->bonuses_total,2) }}</td>
                            <td>{{ number_format((float)$row->deductions_total,2) }}</td>
                            <td><strong>{{ number_format((float)$row->net_salary,2) }}</strong></td>
                            <td>{{ number_format((float)($row->paid_total ?? 0),2) }}</td>
                            <td>{{ number_format((float)$row->payable_amount,2) }}</td>
                            <td>@statusArabic($row->status)</td>
                            <td>
                                <a class="btn btn-sm btn-outline" href="{{ route('payroll.payslip',$row) }}" target="_blank">قسيمة</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" style="text-align:center;padding:2rem">لا توجد نتائج.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top:1rem">{{ $rows->links() }}</div>
</div>
@endsection
