@extends('layouts.app')
@section('title','تقرير قنوات البيع')
@section('content')
<div class="page-header"><div><h1 class="page-heading">تقرير قنوات البيع</h1><p class="page-subheading">المبيعات والخصومات والعمولات وصافي إيراد المطعم</p></div><a class="btn btn-ghost" href="{{ route('settings.sales-channels.index') }}">قنوات البيع</a></div>
<div class="card" style="margin-bottom:1rem"><div class="card-body"><form method="GET" class="scr-filters">
    <input type="date" name="date_from" class="form-input" value="{{ $filters['date_from']??'' }}">
    <input type="date" name="date_to" class="form-input" value="{{ $filters['date_to']??'' }}">
    <select name="sales_channel_id" class="form-select"><option value="">كل القنوات</option>@foreach($channels as $channel)<option value="{{ $channel->id }}" @selected((string)($filters['sales_channel_id']??'')===(string)$channel->id)>{{ $channel->name }}</option>@endforeach</select>
    <select name="group_by" class="form-select"><option value="daily" @selected($groupBy==='daily')>يومي</option><option value="monthly" @selected($groupBy==='monthly')>شهري</option><option value="yearly" @selected($groupBy==='yearly')>سنوي</option></select>
    <button class="btn btn-gold">تطبيق</button>
</form></div></div>
<div class="card"><div class="card-body scr-table"><table class="data-table"><thead><tr><th>الفترة</th><th>القناة</th><th>الطلبات</th><th>المبيعات الخام</th><th>خصم القناة</th><th>العمولة</th><th>صافي إيراد المطعم</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ $row->period }}</td><td><strong>{{ $row->sales_channel_name }}</strong></td><td>{{ number_format($row->orders_count) }}</td><td>₪{{ number_format($row->gross_sales,2) }}</td><td class="scr-danger">− ₪{{ number_format($row->channel_discounts,2) }}</td><td class="scr-danger">− ₪{{ number_format($row->commissions,2) }}</td><td class="scr-net">₪{{ number_format($row->net_revenue,2) }}</td></tr>@empty<tr><td colspan="7" style="text-align:center;padding:2rem">لا توجد بيانات ضمن الفترة المحددة.</td></tr>@endforelse
</tbody></table></div></div><div style="margin-top:1rem">{{ $rows->links() }}</div>
<style>.scr-filters{display:grid;grid-template-columns:repeat(2,minmax(150px,1fr)) minmax(180px,1.2fr) 150px auto;gap:.75rem}.scr-table{overflow:auto}.scr-table table{min-width:900px}.scr-danger{color:#b42318}.scr-net{color:#067647;font-weight:800}@media(max-width:850px){.scr-filters{grid-template-columns:1fr 1fr}.scr-filters button{grid-column:1/-1}}</style>
@endsection
