@extends('layouts.app')
@section('title', 'قنوات البيع')
@section('content')
<div class="page-header sc-header">
    <div><h1 class="page-heading">قنوات البيع</h1><p class="page-subheading">إدارة التطبيقات والمنصات والخصومات والعمولات</p></div>
    <div class="sc-header-actions">
        @can('sales_channels.reports')<a class="btn btn-ghost" href="{{ route('settings.sales-channels.report') }}">التقرير المالي</a>@endcan
        @can('create', \App\Models\SalesChannel::class)<a class="btn btn-gold" href="{{ route('settings.sales-channels.create') }}">+ قناة جديدة</a>@endcan
    </div>
</div>

@if(session('success'))<div class="sc-alert sc-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="sc-alert sc-danger">{{ session('error') }}</div>@endif

<div class="card sc-filter"><div class="card-body"><form method="GET" class="sc-filter-grid">
    <input name="search" class="form-input" value="{{ request('search') }}" placeholder="ابحث بالاسم أو المعرّف">
    <select name="status" class="form-select"><option value="">كل الحالات</option><option value="active" @selected(request('status')==='active')>مفعلة</option><option value="inactive" @selected(request('status')==='inactive')>معطلة</option></select>
    <button class="btn btn-gold">تصفية</button><a class="btn btn-ghost" href="{{ route('settings.sales-channels.index') }}">إعادة</a>
</form></div></div>

<div class="card"><div class="card-body sc-table-wrap"><table class="data-table sc-table">
    <thead><tr><th>القناة</th><th>الخصم</th><th>العمولة</th><th>الطلبات</th><th>المبيعات الخام</th><th>صافي إيراد المطعم</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
    <tbody>
    @forelse($channels as $channel)
        <tr>
            <td><div class="sc-channel"><span class="sc-logo">@if($channel->logo_url)<img src="{{ $channel->logo_url }}" alt="">@else{{ mb_substr($channel->name,0,2) }}@endif</span><span><strong>{{ $channel->name }}</strong><small>{{ $channel->type->label() }} · {{ $channel->slug }}</small></span></div></td>
            <td><strong>{{ $channel->discountLabel() }}</strong><small class="sc-sub">القناة تتحمل {{ number_format((float)$channel->discount_funded_by_channel,0) }}%</small></td>
            <td>{{ $channel->commissionLabel() }}<small class="sc-sub">{{ $channel->commission_base->label() }}</small></td>
            <td>{{ number_format($channel->orders_count) }}</td>
            <td>₪{{ number_format((float)($channel->gross_sales ?? 0),2) }}</td>
            <td class="sc-net">₪{{ number_format((float)($channel->net_revenue ?? 0),2) }}</td>
            <td><span class="badge {{ $channel->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $channel->is_active ? 'مفعلة' : 'معطلة' }}</span></td>
            <td><div class="sc-actions">
                @can('view',$channel)<a class="btn btn-ghost btn-sm" href="{{ route('settings.sales-channels.show',$channel) }}">عرض</a>@endcan
                @can('update',$channel)<a class="btn btn-ghost btn-sm" href="{{ route('settings.sales-channels.edit',$channel) }}">تعديل</a>@endcan
                @can('toggleStatus',$channel)<form method="POST" action="{{ route('settings.sales-channels.toggle-status',$channel) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $channel->is_active ? 'تعطيل' : 'تفعيل' }}</button></form>@endcan
            </div></td>
        </tr>
    @empty<tr><td colspan="8" class="sc-empty">لا توجد قنوات بيع مطابقة.</td></tr>@endforelse
    </tbody>
</table></div></div>
<div style="margin-top:1rem">{{ $channels->links() }}</div>
<style>
.sc-header,.sc-header-actions,.sc-actions,.sc-channel{display:flex;align-items:center}.sc-header{justify-content:space-between;gap:1rem}.sc-header-actions,.sc-actions{gap:.5rem;flex-wrap:wrap}.sc-alert{padding:1rem;margin-bottom:1rem;border-radius:12px}.sc-success{color:#067647;background:#ecfdf3;border:1px solid #abefc6}.sc-danger{color:#b42318;background:#fef3f2;border:1px solid #fecdca}.sc-filter{margin-bottom:1rem}.sc-filter-grid{display:grid;grid-template-columns:minmax(220px,1fr) 180px auto auto;gap:.75rem}.sc-table-wrap{overflow:auto}.sc-table{min-width:1050px}.sc-channel{gap:.7rem}.sc-logo{width:44px;height:44px;display:flex;align-items:center;justify-content:center;overflow:hidden;color:var(--gold);font-weight:800;background:var(--gold-ultra);border:1px solid rgba(188,145,48,.25);border-radius:11px}.sc-logo img{width:100%;height:100%;object-fit:contain;background:#fff}.sc-channel small,.sc-sub{display:block;margin-top:.2rem;color:var(--text-muted);font-size:.74rem}.sc-net{color:#067647;font-weight:800}.sc-empty{text-align:center;color:var(--text-muted);padding:2rem!important}@media(max-width:760px){.sc-header{align-items:flex-start;flex-direction:column}.sc-filter-grid{grid-template-columns:1fr 1fr}.sc-filter-grid input{grid-column:1/-1}}
</style>
@endsection
