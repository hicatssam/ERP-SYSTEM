<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
@php
    $brandLogo = $branding['logo'] ?? $branding['logo_small'] ?? $branding['favicon'] ?? null;
    $brandName = $branding['name'] ?? 'طلباتك';
@endphp
<title>طلباتي — {{ $brandName }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
@include('customer-menu.partials.pwa-head')
<style>
body{padding-bottom:calc(92px + env(safe-area-inset-bottom,0px))}
.wrap{width:min(560px,calc(100% - 28px));margin:auto}
.topbar{position:sticky;top:0;z-index:30;background:color-mix(in srgb,var(--surface) 96%,transparent);backdrop-filter:blur(18px);border-bottom:1px solid var(--line)}
.topin{height:72px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{display:flex;align-items:center;gap:10px}.brand img{width:46px;height:46px;object-fit:contain;border-radius:12px}.brand b,.brand small{display:block}.brand small{font-size:.68rem;color:var(--muted)}
.back{width:40px;height:40px;display:grid;place-items:center;border-radius:13px;background:var(--surface);border:1px solid var(--line);font-size:16px}
main{padding:18px 0 24px}.titleRow{margin-bottom:14px}.titleRow h1{margin:0 0 4px;font-size:1.25rem}.titleRow p{margin:0;color:var(--muted);font-size:.78rem;line-height:1.6}
.orders{display:grid;gap:12px}.order{display:block;padding:14px;background:var(--surface);border-radius:18px;border:1px solid var(--line);box-shadow:0 8px 26px color-mix(in srgb,var(--text) 5%,transparent)}
.orderTop{display:flex;align-items:center;gap:12px}.orderIcon{width:46px;height:46px;flex:0 0 46px;display:grid;place-items:center;border-radius:14px;background:color-mix(in srgb,var(--primary) 10%,var(--surface));color:var(--primary);font-size:18px}.orderInfo{min-width:0;flex:1}.orderInfo strong{display:block;font-size:.92rem}.orderInfo small{display:block;color:var(--muted);font-size:.69rem;margin-top:3px}
.badges{display:flex;flex-wrap:wrap;gap:6px;margin-top:11px}.status-badge{padding:6px 9px;border-radius:999px;font-size:.67rem;font-weight:900;background:#00000008}.status-badge.orderState{color:var(--primary)}.status-badge.success{color:#177245;background:#17724512}.status-badge.warning{color:#9a6700;background:#9a670012}.status-badge.danger{color:#b42318;background:#b4231812}
.orderMeta{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:11px}.meta{padding:9px;border-radius:11px;background:var(--bg)}.meta small{display:block;color:var(--muted);font-size:.63rem}.meta b{display:block;margin-top:2px;font-size:.77rem}.actions{display:flex;gap:8px;margin-top:11px}.action{flex:1;text-align:center;padding:10px;border-radius:11px;font-size:.73rem;font-weight:900}.action.primary{background:var(--primary);color:#fff}.action.secondary{border:1px solid var(--line);color:var(--primary)}
.loading{opacity:.65}.empty{text-align:center;padding:60px 20px}.emptyIcon{font-size:38px}.empty h2{font-size:1rem;margin:12px 0 5px}.empty p{font-size:.78rem;color:var(--muted);margin:0 0 16px}.newOrder{display:inline-flex;padding:11px 18px;border-radius:13px;background:var(--primary);color:#fff;font-weight:900;font-size:.82rem}
@media(max-width:520px){.orderMeta{grid-template-columns:repeat(3,1fr)}.actions{flex-direction:column}}
</style>
</head>
<body class="crisp-customer-menu">
<header class="topbar"><div class="wrap topin"><a class="brand" href="{{ route('customer-menu.show',$location->code) }}">@if($brandLogo)<img src="{{ $brandLogo }}" alt="{{ $brandName }}">@endif<span><b>{{ $brandName }}</b><small>{{ $location->name }}</small></span></a><a class="back" href="{{ route('customer-menu.show',$location->code) }}" aria-label="العودة"><i class="fa-solid fa-arrow-left"></i></a></div></header>
<main class="wrap"><div class="titleRow"><h1>طلباتي</h1><p>الحالة وحالة الدفع تتحدثان تلقائيًا للطلبات التي أنشأتها من هذا الجهاز.</p></div><section id="orders" class="orders" aria-live="polite"></section></main>
@include('customer-menu.partials.bottom-nav', ['activeNav' => 'orders'])
@include('customer-menu.partials.pwa-install')
@include('customer-menu.partials.cart-engine')
<script>
const root=document.querySelector('#orders');const locationCode=@json($location->code);const menuUrl=@json(route('customer-menu.show',$location->code));
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}function money(v){return `${Number(v||0).toFixed(2)} ₪`}function statusUrl(track){return String(track).replace(/\/$/,'')+'/status'}
let rows=[];try{rows=JSON.parse(localStorage.getItem('customer_menu_orders')||'[]')||[]}catch(e){rows=[]}rows=rows.filter(x=>x&&x.location===locationCode&&x.url).slice(0,30);
function tone(data){if(String(data?.payment?.latest_payment?.status||'')==='rejected')return'danger';const s=String(data?.payment_status||'');if(['paid','refunded'].includes(s))return'success';return'warning'}
function skeleton(item,i){return `<article class="order loading" data-order="${i}"><div class="orderTop"><span class="orderIcon"><i class="fa-solid fa-receipt"></i></span><span class="orderInfo"><strong>طلب #${esc(item.order_number||item.number||'—')}</strong><small>جاري تحميل الحالة...</small></span></div><div class="actions"><a class="action primary" href="${esc(item.url)}">تتبع الطلب</a></div></article>`}
async function loadOne(item,i){try{const r=await fetch(statusUrl(item.url),{headers:{Accept:'application/json'},cache:'no-store'});const d=await r.json();if(!r.ok)throw d;const p=d.payment||{};const inv=d.invoice;const el=root.querySelector(`[data-order="${i}"]`);if(!el)return;el.classList.remove('loading');el.innerHTML=`<div class="orderTop"><span class="orderIcon">${d.state==='completed'?'<i class="fa-solid fa-check"></i>':'<i class="fa-solid fa-receipt"></i>'}</span><span class="orderInfo"><strong>طلب #${esc(d.order_number||item.order_number||'—')}</strong><small>${esc(d.service_label||'طلب')} · ${esc(d.location?.name||'')}</small></span></div><div class="badges"><span class="status-badge orderState">${esc(d.label||'حالة الطلب')}</span><span class="status-badge ${tone(d)}">${esc(p.label||'حالة الدفع')}</span></div><div class="orderMeta"><div class="meta"><small>الإجمالي</small><b>${money(p.total_amount??d.total)}</b></div><div class="meta"><small>المدفوع</small><b>${money(p.paid_amount)}</b></div><div class="meta"><small>المتبقي</small><b>${money(p.remaining_amount)}</b></div></div><div class="actions"><a class="action primary" href="${esc(d.track_url||item.url)}">تتبع الطلب</a>${inv?.url?`<a class="action secondary" href="${esc(inv.url)}">الفاتورة</a>`:''}</div>`}catch(e){const el=root.querySelector(`[data-order="${i}"]`);if(el){el.classList.remove('loading');const small=el.querySelector('small');if(small)small.textContent='تعذر تحديث الحالة الآن'}}}
if(!rows.length){root.innerHTML=`<div class="empty"><div class="emptyIcon">🛍️</div><h2>لا توجد طلبات بعد</h2><p>بعد إرسال أول طلب سيظهر هنا تلقائيًا.</p><a class="newOrder" href="${menuUrl}">ابدأ طلبًا جديدًا</a></div>`}else{root.innerHTML=rows.map(skeleton).join('');rows.forEach(loadOne);setInterval(()=>rows.forEach(loadOne),15000)}
</script>
</body></html>