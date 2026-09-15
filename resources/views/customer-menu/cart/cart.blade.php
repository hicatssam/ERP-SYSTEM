<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>سلة الطلب - {{ $branding['name'] ?? 'حلويات دهب' }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
@include('customer-menu.partials.pwa-head')
<style>
.cart-page{padding:10px 0 118px}.cart-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin:2px 0 14px}.cart-head h2{margin:0;font-size:1.12rem;font-weight:900}.cart-head p{margin:4px 0 0;color:var(--muted);font-size:.72rem}.cart-count{white-space:nowrap;background:var(--surface);border:1px solid color-mix(in srgb,var(--text) 8%,transparent);border-radius:999px;padding:7px 10px;font-size:.68rem;color:var(--muted);font-weight:800}.cart-list{display:grid;gap:10px}.cart-item{display:grid;grid-template-columns:82px minmax(0,1fr);gap:11px;padding:10px;background:var(--surface);border:1px solid color-mix(in srgb,var(--text) 8%,transparent);border-radius:20px;box-shadow:0 7px 22px rgba(0,0,0,.04)}.cart-item-media{width:82px;height:82px;border-radius:16px;overflow:hidden;background:color-mix(in srgb,var(--primary) 8%,var(--surface))}.cart-item-media img{width:100%;height:100%;object-fit:cover}.cart-item-copy{min-width:0}.cart-item-copy strong{display:block;font-size:.9rem;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.cart-item-copy small{display:block;color:var(--muted);font-size:.67rem;margin-top:4px;line-height:1.5}.cart-item-price{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:10px}.cart-item-price b{color:var(--primary);font-size:.88rem}.qty-control{display:inline-flex;align-items:center;gap:4px;background:var(--bg);border-radius:12px;padding:3px}.qty-control button{width:31px;height:31px;border:0;border-radius:9px;background:var(--surface);color:var(--text);font-weight:900;font-size:1rem;display:grid;place-items:center}.qty-control span{min-width:27px;text-align:center;font-size:.78rem;font-weight:900}.remove-line{margin-top:8px;border:0;background:transparent;color:#b42318;font-size:.68rem;font-weight:800;padding:0;display:inline-flex;align-items:center;gap:5px}.cart-summary-card{margin-top:14px;background:var(--surface);border:1px solid color-mix(in srgb,var(--text) 8%,transparent);border-radius:20px;padding:15px;box-shadow:0 7px 22px rgba(0,0,0,.04)}.summary-row{display:flex;justify-content:space-between;gap:12px;padding:8px 0;color:var(--muted);font-size:.76rem}.cart-total{display:flex;justify-content:space-between;gap:12px;padding:13px 0 0;margin-top:3px;border-top:1px solid var(--line);font-size:1rem;font-weight:900}.cart-total span:last-child{color:var(--primary)}.cart-note{margin-top:9px;font-size:.7rem;color:var(--muted);line-height:1.65}.checkout-wrap{position:sticky;bottom:calc(70px + env(safe-area-inset-bottom,0px));z-index:20;padding-top:12px;background:linear-gradient(180deg,transparent,var(--bg) 30%)}.checkout-start{width:100%;min-height:54px;border:0;border-radius:16px;background:var(--primary);color:#fff;font-weight:900;font-size:.92rem;box-shadow:0 12px 26px color-mix(in srgb,var(--primary) 24%,transparent);display:flex;align-items:center;justify-content:center;gap:8px}.checkout-start:disabled{opacity:.5}.cart-empty{padding:58px 18px;text-align:center;background:var(--surface);border:1px dashed color-mix(in srgb,var(--text) 14%,transparent);border-radius:22px}.cart-empty i{width:58px;height:58px;border-radius:50%;display:grid;place-items:center;margin:0 auto 13px;background:color-mix(in srgb,var(--primary) 9%,var(--surface));color:var(--primary);font-size:1.3rem}.cart-empty h3{margin:0 0 6px;font-size:1rem}.cart-empty p{margin:0 0 17px;color:var(--muted);font-size:.76rem;line-height:1.7}.cart-empty a{display:inline-flex;padding:11px 19px;border-radius:13px;background:var(--primary);color:#fff;font-size:.78rem;font-weight:900}@media(max-width:360px){.cart-item{grid-template-columns:72px minmax(0,1fr);gap:9px}.cart-item-media{width:72px;height:72px}}
</style>
</head>
<body class="crisp-customer-menu">
<div class="app crisp-menu-app">
@include('customer-menu.partials.topbar', ['pageTitle' => 'سلة الطلب', 'pageSubtitle' => $location->name])
<main class="menu-area cart-page"><div class="shell">
<div class="cart-head"><div><h2>سلتك 🛍️</h2><p>راجع الكمية والإضافات قبل إتمام الطلب</p></div><span class="cart-count" id="cartCountBadge">0 صنف</span></div>
<div class="cart-list" id="cartItems"></div>
<div class="cart-summary-card" id="summaryCard"><div class="summary-row"><span>عدد القطع</span><span id="itemCount">0</span></div><div class="cart-total"><span>الإجمالي</span><span id="cartTotal">0.00 ₪</span></div><div class="cart-note">رسوم التوصيل — إن وُجدت — تظهر بعد اختيار نوع الاستلام في الخطوة التالية.</div></div>
<div class="checkout-wrap"><button class="checkout-start" id="checkoutStart" type="button"><i class="fa-solid fa-arrow-left"></i> إتمام الطلب</button></div>
</div></main>
</div>
@include('customer-menu.partials.bottom-nav', ['activeNav' => 'cart'])
@include('customer-menu.partials.pwa-install')
@include('customer-menu.partials.cart-engine')
<script>
const CM=window.CustomerMenu;
const PRODUCTS_URL=@json(route('customer-menu.products',$location->code));
function renderCart(){
 const rows=CM.cartRows(),count=rows.reduce((s,r)=>s+Number(r.quantity),0),holder=document.getElementById('cartItems');
 document.getElementById('cartCountBadge').textContent=`${count} قطعة`;
 document.getElementById('itemCount').textContent=count;
 document.getElementById('cartTotal').textContent=CM.money(CM.cartTotal());
 document.getElementById('checkoutStart').disabled=!rows.length;
 document.getElementById('summaryCard').hidden=!rows.length;
 holder.innerHTML=rows.length?rows.map(x=>{
   const modifierLine=(x.modifiers||[]).length?x.modifiers.map(m=>`${m.name}${m.quantity>1?' ×'+m.quantity:''}`).join('، '):'';
   const subtitle=[x.variant_name,modifierLine].filter(Boolean).join(' — ');
   return `<article class="cart-item"><div class="cart-item-media">${x.image?`<img src="${CM.esc(x.image)}" alt="">`:''}</div><div class="cart-item-copy"><strong>${CM.esc(x.name.split(' - ')[0])}</strong>${subtitle?`<small>${CM.esc(subtitle)}</small>`:''}<div class="cart-item-price"><b>${CM.money(x.price*x.quantity)}</b><div class="qty-control"><button type="button" data-dec="${CM.esc(x.key)}" aria-label="تقليل">−</button><span>${x.quantity}</span><button type="button" data-inc="${CM.esc(x.key)}" aria-label="زيادة">+</button></div></div><button class="remove-line" type="button" data-remove="${CM.esc(x.key)}"><i class="fa-regular fa-trash-can"></i> حذف من السلة</button></div></article>`;
 }).join(''):`<div class="cart-empty"><i class="fa-solid fa-bag-shopping"></i><h3>السلة فارغة</h3><p>اختر البرجر أو المقبلات أو المشروبات التي تحبها، ثم ارجع هنا لإتمام الطلب.</p><a href="${PRODUCTS_URL}">تصفّح المنيو</a></div>`;
}
document.addEventListener('click',e=>{const inc=e.target.closest('[data-inc]');if(inc){CM.changeCart(inc.dataset.inc,1);renderCart();return}const dec=e.target.closest('[data-dec]');if(dec){CM.changeCart(dec.dataset.dec,-1);renderCart();return}const rm=e.target.closest('[data-remove]');if(rm){CM.removeFromCart(rm.dataset.remove);renderCart();return}});
document.getElementById('checkoutStart').addEventListener('click',()=>{if(!CM.cartRows().length)return;window.location.href=@json(route('customer-menu.checkout',$location->code));});
renderCart();
</script>
</body></html>
