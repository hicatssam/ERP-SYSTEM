@props(['record'])
@php $channel = $record->salesChannel; @endphp
<div class="sc-breakdown">
    <div><span>قناة البيع</span><strong>{{ $channel?->name ?? 'غير محددة' }}</strong></div>
    <div><span>إجمالي قبل خصم القناة</span><strong>₪{{ number_format((float)$record->subtotal - (float)($record->discount_amount ?? 0),2) }}</strong></div>
    <div><span>خصم قناة البيع</span><strong class="sc-minus">− ₪{{ number_format((float)($record->channel_discount_amount ?? 0),2) }}</strong></div>
    <div><span>الإجمالي النهائي للعميل</span><strong>₪{{ number_format((float)$record->total_amount,2) }}</strong></div>
    @can('sales_channels.reports')
        <div><span>عمولة التطبيق</span><strong class="sc-minus">− ₪{{ number_format((float)($record->channel_commission_amount ?? 0),2) }}</strong></div>
        <div class="sc-total"><span>صافي إيراد المطعم</span><strong>₪{{ number_format((float)($record->channel_net_revenue ?? 0),2) }}</strong></div>
    @endcan
</div>
<style>.sc-breakdown{padding:1rem;background:rgba(212,175,55,.06);border:1px solid rgba(212,175,55,.25);border-radius:12px}.sc-breakdown>div{display:flex;justify-content:space-between;gap:1rem;padding:.35rem 0}.sc-breakdown span{color:var(--text-muted)}.sc-breakdown .sc-minus{color:#b42318}.sc-breakdown .sc-total{margin-top:.4rem;padding-top:.8rem;border-top:1px solid var(--border)}.sc-breakdown .sc-total strong{color:#067647;font-size:1.15rem}</style>
