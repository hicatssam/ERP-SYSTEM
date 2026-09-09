@extends('layouts.app')
@section('title', $salesChannel->name)
@section('content')
<div class="page-header sc-show-head"><div class="sc-title-wrap"><span class="sc-big-logo">@if($salesChannel->logo_url)<img src="{{ $salesChannel->logo_url }}" alt="">@else{{ mb_substr($salesChannel->name,0,2) }}@endif</span><div><h1 class="page-heading">{{ $salesChannel->name }}</h1><p class="page-subheading">{{ $salesChannel->type->label() }} · {{ $salesChannel->slug }}</p></div></div><div class="sc-show-actions">@can('update',$salesChannel)<a class="btn btn-gold" href="{{ route('settings.sales-channels.edit',$salesChannel) }}">تعديل</a>@endcan<a class="btn btn-ghost" href="{{ route('settings.sales-channels.index') }}">رجوع</a></div></div>
@if(session('success'))<div class="sc-note">{{ session('success') }}</div>@endif
<div class="sc-stats">
    <div class="card"><small>إجمالي الطلبات</small><strong>{{ number_format($stats['orders_count']) }}</strong></div>
    <div class="card"><small>المبيعات الخام</small><strong>₪{{ number_format($stats['gross_sales'],2) }}</strong></div>
    <div class="card"><small>خصومات القناة</small><strong class="danger">₪{{ number_format($stats['channel_discounts'],2) }}</strong></div>
    <div class="card"><small>عمولات التطبيق</small><strong class="danger">₪{{ number_format($stats['commissions'],2) }}</strong></div>
    <div class="card"><small>صافي إيراد المطعم</small><strong class="success">₪{{ number_format($stats['net_revenue'],2) }}</strong></div>
</div>
<div class="sc-columns">
    <div class="card"><div class="card-header"><span class="card-title">الشروط التجارية الحالية</span></div><div class="card-body"><dl class="sc-defs">
        <div><dt>الحالة</dt><dd><span class="badge {{ $salesChannel->is_active?'badge-active':'badge-inactive' }}">{{ $salesChannel->is_active?'مفعلة':'معطلة' }}</span></dd></div>
        <div><dt>خصم العميل</dt><dd>{{ $salesChannel->discountLabel() }}</dd></div>
        <div><dt>تحمل التطبيق للخصم</dt><dd>{{ number_format((float)$salesChannel->discount_funded_by_channel,2) }}%</dd></div>
        <div><dt>العمولة</dt><dd>{{ $salesChannel->commissionLabel() }}</dd></div>
        <div><dt>قاعدة العمولة</dt><dd>{{ $salesChannel->commission_base->label() }}</dd></div>
        <div><dt>رسوم التوصيل</dt><dd>{{ $salesChannel->delivery_fee_recipient->label() }}</dd></div>
        <div><dt>التسوية</dt><dd>{{ $salesChannel->settlement_cycle->label() }} @if($salesChannel->settlement_days) — خلال {{ $salesChannel->settlement_days }} يوم@endif</dd></div>
    </dl></div></div>
    <div class="card"><div class="card-header"><span class="card-title">المبيعات الشهرية</span></div><div class="card-body sc-table-wrap"><table class="data-table"><thead><tr><th>الشهر</th><th>الطلبات</th><th>الخام</th><th>الصافي</th></tr></thead><tbody>@forelse($monthlySales as $row)<tr><td>{{ $row->period }}</td><td>{{ number_format($row->orders_count) }}</td><td>₪{{ number_format($row->gross_sales,2) }}</td><td>₪{{ number_format($row->net_revenue,2) }}</td></tr>@empty<tr><td colspan="4">لا توجد مبيعات بعد.</td></tr>@endforelse</tbody></table></div></div>
</div>
<div class="card" style="margin-top:1.25rem"><div class="card-header"><span class="card-title">آخر الطلبات</span></div><div class="card-body sc-table-wrap"><table class="data-table"><thead><tr><th>الطلب</th><th>العميل</th><th>الفرع</th><th>الإجمالي</th><th>خصم القناة</th><th>الصافي</th></tr></thead><tbody>@forelse($recentOrders as $order)<tr><td><a href="{{ route('orders.show',$order) }}">{{ $order->order_number }}</a></td><td>{{ $order->customer?->name ?? 'عميل نقدي' }}</td><td>{{ $order->location?->name ?? '—' }}</td><td>₪{{ number_format((float)$order->total_amount,2) }}</td><td>₪{{ number_format((float)$order->channel_discount_amount,2) }}</td><td>₪{{ number_format((float)$order->channel_net_revenue,2) }}</td></tr>@empty<tr><td colspan="6">لا توجد طلبات.</td></tr>@endforelse</tbody></table></div></div>
<style>
.sc-show-head,.sc-title-wrap,.sc-show-actions{display:flex;align-items:center}.sc-show-head{justify-content:space-between;gap:1rem}.sc-title-wrap,.sc-show-actions{gap:.75rem}.sc-big-logo{width:62px;height:62px;display:flex;align-items:center;justify-content:center;overflow:hidden;color:var(--gold);font-weight:900;background:var(--gold-ultra);border-radius:15px}.sc-big-logo img{width:100%;height:100%;object-fit:contain;background:#fff}.sc-note{padding:1rem;margin-bottom:1rem;color:#067647;background:#ecfdf3;border:1px solid #abefc6;border-radius:12px}.sc-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:1rem;margin-bottom:1.25rem}.sc-stats .card{padding:1rem}.sc-stats small{display:block;color:var(--text-muted)}.sc-stats strong{display:block;margin-top:.45rem;font-size:1.25rem}.danger{color:#b42318}.success{color:#067647}.sc-columns{display:grid;grid-template-columns:minmax(300px,.8fr) minmax(400px,1.2fr);gap:1.25rem}.sc-defs{margin:0}.sc-defs>div{display:flex;justify-content:space-between;gap:1rem;padding:.7rem 0;border-bottom:1px solid var(--border)}.sc-defs>div:last-child{border:0}.sc-defs dt{color:var(--text-muted)}.sc-defs dd{margin:0;font-weight:700}.sc-table-wrap{overflow:auto}@media(max-width:1050px){.sc-stats{grid-template-columns:repeat(2,1fr)}.sc-columns{grid-template-columns:1fr}}@media(max-width:650px){.sc-show-head{align-items:flex-start;flex-direction:column}.sc-stats{grid-template-columns:1fr}}
</style>
@endsection
