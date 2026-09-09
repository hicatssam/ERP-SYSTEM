@if(!empty($salesChannelDashboard))
<div class="card scd-widget">
    <div class="card-header"><span class="card-title">قنوات البيع</span>@can('sales_channels.reports')<a href="{{ route('settings.sales-channels.report') }}">التقرير الكامل</a>@endcan</div>
    <div class="card-body">
        <div class="scd-summary"><div><small>الطلبات</small><strong>{{ number_format($salesChannelDashboard['orders_count']) }}</strong></div><div><small>خصومات القنوات</small><strong>₪{{ number_format($salesChannelDashboard['discounts'],2) }}</strong></div><div><small>صافي الإيراد</small><strong>₪{{ number_format($salesChannelDashboard['net_revenue'],2) }}</strong></div><div><small>القناة الأعلى</small><strong>{{ $salesChannelDashboard['top_channel']?->name ?? '—' }}</strong></div></div>
        <div class="scd-bars">@foreach($salesChannelDashboard['by_channel']->take(6) as $row) @php $max=max(1,(float)$salesChannelDashboard['by_channel']->max('gross_sales'));$width=((float)$row->gross_sales/$max)*100; @endphp <div><span>{{ $row->name }}</span><i><b style="width:{{ $width }}%"></b></i><strong>₪{{ number_format($row->gross_sales,0) }}</strong></div>@endforeach</div>
    </div>
</div>
<style>.scd-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem}.scd-summary>div{padding:.8rem;background:rgba(212,175,55,.06);border-radius:10px}.scd-summary small,.scd-summary strong{display:block}.scd-summary small{color:var(--text-muted)}.scd-summary strong{margin-top:.3rem}.scd-bars{display:grid;gap:.65rem;margin-top:1rem}.scd-bars>div{display:grid;grid-template-columns:110px 1fr 90px;gap:.7rem;align-items:center}.scd-bars i{height:8px;overflow:hidden;background:var(--border);border-radius:9px}.scd-bars b{display:block;height:100%;background:var(--gold);border-radius:9px}.scd-bars strong{text-align:left}@media(max-width:700px){.scd-summary{grid-template-columns:1fr 1fr}.scd-bars>div{grid-template-columns:90px 1fr 75px}}</style>
@endif
