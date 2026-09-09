<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
    $brandLogo = $branding['logo'] ?? $branding['logo_small'] ?? $branding['favicon'] ?? null;
    $brandName = $branding['name'] ?? 'طلباتك';
@endphp
<title>طلباتي — {{ $brandName }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
<style>
:root{
 --primary:{{ $theme['primary'] ?? '#704C34' }};
 --accent:{{ $theme['accent'] ?? '#D79A55' }};
 --bg:{{ $theme['background'] ?? '#F7F3EE' }};
 --surface:{{ $theme['surface'] ?? '#fff' }};
 --text:{{ $theme['text'] ?? '#241D18' }};
 --muted:{{ $theme['muted'] ?? '#7D746C' }};
}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--text);font-family:Cairo,Tajawal,Arial,sans-serif;padding-bottom:82px}
a{text-decoration:none;color:inherit}.wrap{width:min(720px,calc(100% - 28px));margin:auto}
.topbar{position:sticky;top:0;z-index:30;background:color-mix(in srgb,var(--surface) 96%,transparent);backdrop-filter:blur(18px);border-bottom:1px solid color-mix(in srgb,var(--text) 7%,transparent)}
.topin{height:72px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{display:flex;align-items:center;gap:10px}.brand img{width:54px;height:54px;object-fit:contain}.brand b,.brand small{display:block}.brand small{font-size:.68rem;color:var(--muted);margin-top:2px}
.back{width:40px;height:40px;display:grid;place-items:center;border-radius:13px;background:var(--surface);border:1px solid color-mix(in srgb,var(--text) 8%,transparent);font-size:20px}
main{padding:22px 0}.titleRow{margin-bottom:18px}.titleRow h1{margin:0 0 4px;font-size:1.35rem}.titleRow p{margin:0;color:var(--muted);font-size:.8rem}
.orders{display:grid;gap:10px}.order{display:flex;align-items:center;gap:12px;padding:14px;background:var(--surface);border-radius:17px;border:1px solid color-mix(in srgb,var(--text) 7%,transparent)}
.orderIcon{width:48px;height:48px;flex:0 0 48px;display:grid;place-items:center;border-radius:14px;background:color-mix(in srgb,var(--accent) 18%,var(--surface));color:var(--primary);font-size:20px}
.orderInfo{min-width:0;flex:1}.orderInfo strong{display:block;font-size:.92rem}.orderInfo small{display:block;color:var(--muted);font-size:.7rem;margin-top:3px}
.arrow{color:var(--primary);font-size:20px}.empty{text-align:center;padding:70px 20px}.emptyIcon{font-size:42px}.empty h2{font-size:1rem;margin:12px 0 5px}.empty p{font-size:.78rem;color:var(--muted);margin:0 0 16px}.primary{display:inline-flex;padding:11px 18px;border-radius:13px;background:var(--primary);color:#fff;font-weight:900;font-size:.82rem}
.mobileNav{position:fixed;right:12px;bottom:12px;z-index:60;width:calc(100% - 24px);height:72px;display:grid;grid-template-columns:repeat(4,1fr);border-radius:22px;overflow:hidden;box-shadow:0 16px 45px #00000020;background:color-mix(in srgb,var(--surface) 97%,transparent);border-top:1px solid color-mix(in srgb,var(--text) 8%,transparent);backdrop-filter:blur(18px)}
.mobileNav a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;color:var(--muted);font-size:.61rem;font-weight:800}.mobileNav a.active{color:var(--primary)}.ico{font-size:18px}
@media(min-width:700px){body{padding-bottom:30px}.mobileNav{display:none}.wrap{width:min(920px,calc(100% - 40px))}.orders{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
</head>
<body>
<header class="topbar"><div class="wrap topin">
<a class="brand" href="{{ route('customer-menu.show',$location->code) }}">
@if($brandLogo)<img src="{{ $brandLogo }}" alt="{{ $brandName }}">@endif
<span><b>{{ $brandName }}</b><small>{{ $location->name }}</small></span>
</a>
<a class="back" href="{{ route('customer-menu.show',$location->code) }}" aria-label="العودة">←</a>
</div></header>
<main class="wrap">
<div class="titleRow"><h1>طلباتي</h1><p>الطلبات التي أنشأتها من هذا الجهاز.</p></div>
<section id="orders" class="orders" aria-live="polite"></section>
</main>
<nav class="mobileNav">
<a href="{{ route('customer-menu.show',$location->code) }}"><span class="ico">⌂</span><span>الرئيسية</span></a>
<a href="{{ route('customer-menu.show',$location->code) }}#grid"><span class="ico">⌕</span><span>استكشف</span></a>
<a class="active" href="#"><span class="ico">▣</span><span>طلباتي</span></a>
<a href="{{ route('customer-menu.show',$location->code) }}"><span class="ico">🛒</span><span>السلة</span></a>
</nav>
<script>
const root=document.querySelector('#orders');
const locationCode=@json($location->code);
const menuUrl=@json(route('customer-menu.show',$location->code));
function esc(value){return String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));}
let rows=[];try{rows=JSON.parse(localStorage.getItem('customer_menu_orders')||'[]')||[]}catch(e){rows=[]}
rows=rows.filter(item=>item&&item.location===locationCode&&item.url).slice(0,30);
root.innerHTML=rows.length?rows.map(item=>`
<a class="order" href="${esc(item.url)}">
<span class="orderIcon">✓</span>
<span class="orderInfo"><strong>طلب #${esc(item.number||'—')}</strong><small>اضغط لعرض التفاصيل وتتبع الحالة</small></span>
<span class="arrow">←</span>
</a>`).join(''):`<div class="empty"><div class="emptyIcon">🛍️</div><h2>لا توجد طلبات بعد</h2><p>بعد إرسال أول طلب سيظهر هنا تلقائيًا.</p><a class="primary" href="${menuUrl}">ابدأ طلبًا جديدًا</a></div>`;
</script>
</body></html>