<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    @php
        $brandLogo = $branding['logo'] ?? $branding['logo_small'] ?? $branding['favicon'] ?? null;
        $brandName = $branding['name'] ?? $branding['brand_name'] ?? 'طلبك';
    @endphp
    <title>تتبع {{ $order->order_number }} — {{ $brandName }}</title>
    @if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
    @include('customer-menu.partials.pwa-head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{--primary:{{ $theme['primary'] ?? '#704C34' }};--accent:{{ $theme['accent'] ?? '#D79A55' }};--bg:{{ $theme['background'] ?? '#F7F3EE' }};--surface:{{ $theme['surface'] ?? '#fff' }};--text:{{ $theme['text'] ?? '#241D18' }};--muted:{{ $theme['muted'] ?? '#7D746C' }};--danger:#b42318;--success:#177245;--warning:#9a6700}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Tajawal,Arial,sans-serif}a{text-decoration:none;color:inherit}.wrap{width:min(820px,calc(100% - 24px));margin:auto}.topbar{position:sticky;top:0;z-index:20;background:color-mix(in srgb,var(--surface) 95%,transparent);backdrop-filter:blur(12px);border-bottom:1px solid #00000010}.topin{min-height:68px;display:flex;align-items:center;justify-content:space-between;gap:12px}.brand{display:flex;align-items:center;gap:10px}.brand img{width:42px;height:42px;object-fit:contain}.brand b,.brand small{display:block}.brand small{font-size:.72rem;color:var(--muted)}.back{font-weight:900;color:var(--primary)}main{padding:20px 0 96px}.card{background:var(--surface);border:1px solid #00000010;border-radius:22px;padding:clamp(18px,4vw,32px);box-shadow:0 20px 55px #00000012}.orderHead{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;border-bottom:1px solid #0000000f;padding-bottom:20px}.eyebrow{color:var(--muted);font-size:.76rem;font-weight:800}.orderHead h1{margin:4px 0 0;font-size:clamp(24px,5vw,36px)}.status{display:inline-flex;align-items:center;gap:7px;padding:9px 13px;border-radius:999px;background:color-mix(in srgb,var(--accent) 20%,var(--surface));color:var(--primary);font-weight:900;font-size:.82rem}.statusDot{width:8px;height:8px;border-radius:50%;background:var(--accent)}.progress{padding:26px 0}.progressLine{display:grid;grid-template-columns:repeat(4,1fr);position:relative}.progressLine:before{content:"";position:absolute;top:16px;right:12.5%;left:12.5%;height:3px;background:#00000012}.progressItem{position:relative;z-index:1;text-align:center;font-size:.72rem;color:var(--muted);font-weight:800}.dot{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;margin:0 auto 7px;background:var(--bg);border:3px solid #00000014}.progressItem.on{color:var(--primary)}.progressItem.on .dot{background:var(--primary);border-color:var(--primary);color:#fff}.progressItem.current .dot{box-shadow:0 0 0 5px color-mix(in srgb,var(--accent) 28%,transparent)}.cancelled .progress{opacity:.4}.notice{display:none;padding:13px 14px;margin-bottom:14px;border-radius:14px;background:color-mix(in srgb,var(--accent) 15%,var(--surface));font-size:.88rem}.notice.show{display:block}.paymentBox{display:none;margin:14px 0 22px;padding:16px;border-radius:16px;border:1px solid #00000010;background:color-mix(in srgb,var(--bg) 55%,var(--surface))}.paymentBox.show{display:block}.paymentTop{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.paymentTitle{font-weight:900}.paymentMessage{font-size:.8rem;color:var(--muted);margin-top:4px}.paymentBadge{font-size:.74rem;font-weight:900;padding:7px 10px;border-radius:999px;background:#0000000a}.paymentBox[data-tone="success"] .paymentBadge{color:var(--success);background:#17724512}.paymentBox[data-tone="warning"] .paymentBadge{color:var(--warning);background:#9a670012}.paymentBox[data-tone="danger"] .paymentBadge{color:var(--danger);background:#b4231812}.paymentAmounts{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:14px}.amount{padding:10px;border-radius:12px;background:var(--surface);border:1px solid #0000000a}.amount small{display:block;color:var(--muted);font-size:.68rem}.amount b{display:block;margin-top:2px}.invoiceAction{display:none;margin-top:12px}.invoiceAction.show{display:block}.invoiceAction a{display:inline-flex;padding:10px 14px;border-radius:12px;background:var(--primary);color:#fff;font-weight:900;font-size:.8rem}.sectionTitle{display:flex;justify-content:space-between;align-items:center;margin:10px 0}.sectionTitle h2{margin:0;font-size:1rem}.live{font-size:.7rem;color:var(--muted)}.live.ok{color:var(--success)}.live.error{color:var(--danger)}.items{border-top:1px solid #0000000e}.item{display:flex;justify-content:space-between;gap:12px;padding:13px 0;border-bottom:1px solid #0000000e}.item small{display:block;color:var(--muted);margin-top:3px}.item b{white-space:nowrap;color:var(--primary)}.total{display:flex;justify-content:space-between;padding-top:18px;font-weight:900;font-size:1.1rem}.footerActions{display:flex;gap:10px;margin-top:24px}.action{flex:1;text-align:center;padding:12px;border-radius:12px;font-weight:900}.primary{background:var(--primary);color:#fff}.secondary{border:1px solid #00000016;color:var(--primary)}@media(max-width:560px){.orderHead{flex-direction:column}.status{align-self:flex-start}.paymentAmounts{grid-template-columns:1fr}.footerActions{flex-direction:column}.progressItem{font-size:.62rem}}
    </style>
</head>
<body>
<header class="topbar"><div class="wrap topin">
    <a class="brand" href="{{ route('customer-menu.show',$order->location->code) }}">@if($brandLogo)<img src="{{ $brandLogo }}" alt="{{ $brandName }}">@endif<span><b>{{ $brandName }}</b><small>{{ $order->location->name }}</small></span></a>
    <a class="back" href="{{ route('customer-menu.my-orders',$order->location->code) }}">طلباتي ←</a>
</div></header>
<main class="wrap"><section class="card" id="trackingCard">
    <div class="orderHead"><div><span class="eyebrow">تفاصيل الطلب</span><h1>#{{ $order->order_number }}</h1></div><span class="status" id="label"><span class="statusDot"></span><span>جاري تحديث الحالة</span></span></div>
    <div class="progress"><div class="progressLine">
        <div class="progressItem on" data-stage="0"><span class="dot">1</span><span>تم الاستلام</span></div>
        <div class="progressItem" data-stage="1"><span class="dot">2</span><span>تم التأكيد</span></div>
        <div class="progressItem" data-stage="2"><span class="dot">3</span><span>قيد التحضير</span></div>
        <div class="progressItem" data-stage="3"><span class="dot">4</span><span>جاهز</span></div>
    </div></div>
    <div class="notice" id="eta"></div>
    <div class="paymentBox" id="paymentBox" data-tone="warning">
        <div class="paymentTop"><div><div class="paymentTitle" id="paymentTitle">حالة الدفع</div><div class="paymentMessage" id="paymentMessage"></div></div><span class="paymentBadge" id="paymentBadge">—</span></div>
        <div class="paymentAmounts"><div class="amount"><small>الإجمالي</small><b id="paymentTotal">0.00 ₪</b></div><div class="amount"><small>المدفوع</small><b id="paymentPaid">0.00 ₪</b></div><div class="amount"><small>المتبقي</small><b id="paymentRemaining">0.00 ₪</b></div></div>
        <div class="invoiceAction" id="invoiceAction"><a id="invoiceLink" href="#">عرض الفاتورة وحفظها كصورة</a></div>
    </div>
    @include('customer-menu.partials.order-live-notifications')
    <div class="sectionTitle"><h2>ملخص الطلب</h2><span class="live" id="liveState">تحديث مباشر</span></div>
    <div class="items">@foreach($order->items as $item)<div class="item"><span>{{ $item->product_name }}<small>الكمية: {{ $item->quantity }}</small></span><b>{{ number_format($item->line_total,2) }} ₪</b></div>@endforeach</div>
    <div class="total"><span>الإجمالي</span><span>{{ number_format($order->total_amount,2) }} ₪</span></div>
    <div class="footerActions"><a class="action primary" href="{{ route('customer-menu.show',$order->location->code) }}">طلب جديد</a><a class="action secondary" href="{{ route('customer-menu.my-orders',$order->location->code) }}">كل طلباتي</a></div>
</section></main>
@include('customer-menu.partials.pwa-install')
<script>
const statusUrl=@json(route('customer-menu.status',$order->public_token));
const initial=@json($statusPayload ?? []);
const label=document.querySelector('#label span:last-child');
const card=document.getElementById('trackingCard');
const stages=[...document.querySelectorAll('[data-stage]')];
const eta=document.getElementById('eta');
const paymentBox=document.getElementById('paymentBox');
const liveState=document.getElementById('liveState');
const money=v=>`${Number(v||0).toFixed(2)} ₪`;
let lastFingerprint=initial?.fingerprint||null;
let polling=false;
let timer=null;
function paymentTone(p){const latest=String(p?.latest_payment?.status||'');if(latest==='rejected')return'danger';if(['paid','refunded'].includes(String(p?.status||'')))return'success';return'warning'}
function render(data){
 const state=String(data.state||'received').toLowerCase(),cancelled=state==='cancelled',stage=cancelled?0:Math.max(0,Math.min(3,Number(data.step||1)-1));
 stages.forEach(el=>{const v=Number(el.dataset.stage);el.classList.toggle('on',!cancelled&&v<=stage);el.classList.toggle('current',!cancelled&&v===stage)});
 card.classList.toggle('cancelled',cancelled);label.textContent=data.label||'جاري تجهيز طلبك';
 const minutes=Number(data.estimated_ready_minutes);eta.textContent=!cancelled&&Number.isFinite(minutes)&&minutes>0?`⏱️ الوقت المتوقع لتجهيز طلبك: حوالي ${minutes} دقيقة`:'';eta.classList.toggle('show',!!eta.textContent);
 const p=data.payment||{};paymentBox.classList.add('show');paymentBox.dataset.tone=paymentTone(p);document.getElementById('paymentTitle').textContent=p.label||'حالة الدفع';document.getElementById('paymentMessage').textContent=p.message||'';document.getElementById('paymentBadge').textContent=p.label||'—';document.getElementById('paymentTotal').textContent=money(p.total_amount??data.total);document.getElementById('paymentPaid').textContent=money(p.paid_amount);document.getElementById('paymentRemaining').textContent=money(p.remaining_amount);
 const invoice=data.invoice,invoiceAction=document.getElementById('invoiceAction');if(invoice?.url){document.getElementById('invoiceLink').href=invoice.url;invoiceAction.classList.add('show')}else invoiceAction.classList.remove('show');
}
function markLive(ok){liveState.classList.toggle('ok',ok);liveState.classList.toggle('error',!ok);liveState.textContent=ok?'تم التحديث الآن':'تعذر التحديث — سنحاول مجددًا'}
async function poll(){
 if(polling)return;polling=true;
 try{
   const separator=statusUrl.includes('?')?'&':'?';
   const r=await fetch(`${statusUrl}${separator}_=${Date.now()}`,{headers:{Accept:'application/json','Cache-Control':'no-cache'},cache:'no-store'}),d=await r.json();
   if(!r.ok)throw d;
   const nextFingerprint=d?.fingerprint||null;
   if(lastFingerprint&&nextFingerprint&&nextFingerprint!==lastFingerprint){window.CustomerOrderNotify?.changed('تحديث على طلبك',d.label||d?.payment?.message||'تم تحديث حالة طلبك');}
   lastFingerprint=nextFingerprint||lastFingerprint;render(d);markLive(true);
 }catch(e){markLive(false)}finally{polling=false}
}
function startPolling(){clearInterval(timer);poll();timer=setInterval(poll,5000)}
render(initial);startPolling();
document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll()});
window.addEventListener('focus',poll);
window.addEventListener('online',poll);
</script>
</body></html>
