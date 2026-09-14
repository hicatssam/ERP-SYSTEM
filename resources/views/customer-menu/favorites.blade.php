<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>المفضلة - {{ $branding['name'] ?? 'حلويات دهب' }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
@include('customer-menu.partials.pwa-head')
<style>
.favorites-page{padding-top:12px;padding-bottom:100px}.favorites-head{display:flex;align-items:end;justify-content:space-between;gap:12px;margin-bottom:14px}.favorites-head h2{margin:0;font-size:1.05rem;font-weight:900}.favorites-head small{color:var(--muted);font-size:.72rem}.favorites-list{display:grid;gap:10px}.favorite-card{display:grid;grid-template-columns:78px minmax(0,1fr) auto;align-items:center;gap:12px;padding:10px;background:var(--surface);border:1px solid color-mix(in srgb,var(--text) 8%,transparent);border-radius:18px;box-shadow:0 5px 18px rgba(0,0,0,.035)}.favorite-media{width:78px;height:78px;border-radius:14px;overflow:hidden;background:color-mix(in srgb,var(--primary) 8%,var(--surface));position:relative}.favorite-media img{width:100%;height:100%;object-fit:cover;display:block}.favorite-copy{min-width:0}.favorite-copy strong{display:block;font-size:.88rem;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.favorite-copy small{display:block;color:var(--muted);font-size:.68rem;margin-top:4px}.favorite-copy .fav-price{display:block;color:var(--primary);font-size:.82rem;font-weight:900;margin-top:7px}.favorite-actions{display:flex;flex-direction:column;gap:7px}.favorite-actions button,.favorite-actions a{width:34px;height:34px;border:1px solid color-mix(in srgb,var(--text) 8%,transparent);border-radius:11px;background:var(--surface);display:grid;place-items:center;color:var(--text);font-size:.76rem}.favorite-actions .remove{color:var(--primary);background:color-mix(in srgb,var(--primary) 7%,var(--surface))}.favorite-actions .add{background:var(--primary);color:#fff;border-color:var(--primary)}.favorite-actions button:disabled{opacity:.45}.favorites-empty{padding:52px 18px;text-align:center;background:var(--surface);border:1px dashed color-mix(in srgb,var(--text) 14%,transparent);border-radius:20px}.favorites-empty i{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;margin:0 auto 12px;background:color-mix(in srgb,var(--primary) 9%,var(--surface));color:var(--primary);font-size:1.2rem}.favorites-empty h3{margin:0 0 5px;font-size:1rem}.favorites-empty p{margin:0 0 16px;color:var(--muted);font-size:.76rem}.favorites-empty a{display:inline-flex;padding:10px 18px;border-radius:12px;background:var(--primary);color:#fff;font-size:.78rem;font-weight:900}@media(max-width:360px){.favorite-card{grid-template-columns:68px minmax(0,1fr) auto;gap:9px}.favorite-media{width:68px;height:68px}}
</style>
</head>
<body class="crisp-customer-menu">
<div class="app crisp-menu-app">
@include('customer-menu.partials.topbar', ['pageTitle' => 'المفضلة', 'pageSubtitle' => $location->name])
<main class="menu-area favorites-page"><div class="shell"><div class="favorites-head"><div><h2>الأصناف المحفوظة</h2><small>ارجع لطلباتك المفضلة بسرعة</small></div><small id="favoriteCount"></small></div><div class="favorites-list" id="favoritesItems"></div></div></main>
</div>
@include('customer-menu.partials.bottom-nav', ['activeNav' => 'favorites'])
@include('customer-menu.partials.pwa-install')
@include('customer-menu.partials.cart-engine')
<script>
const CM=window.CustomerMenu;
const PRODUCT_URL_BASE=@json(route('customer-menu.product.show',[$location->code,'__ID__']));
const PRODUCTS_URL=@json(route('customer-menu.products',$location->code));
function productUrl(id){return PRODUCT_URL_BASE.replace('__ID__',id)}
function renderFavorites(){const rows=CM.favorites().map(id=>CM.product(id)).filter(Boolean);document.getElementById('favoriteCount').textContent=rows.length?`${rows.length} صنف`:'';document.getElementById('favoritesItems').innerHTML=rows.length?rows.map(p=>`<article class="favorite-card"><a class="favorite-media" href="${productUrl(p.id)}">${CM.image(p)}</a><div class="favorite-copy"><strong>${CM.esc(p.name)}</strong><small>${CM.esc(p.category_name||'من المنيو')}</small><span class="fav-price">${CM.money(p.price)}</span></div><div class="favorite-actions"><a href="${productUrl(p.id)}" aria-label="عرض المنتج"><i class="fa-solid fa-eye"></i></a><button class="remove" type="button" data-fav="${CM.esc(p.id)}" aria-label="إزالة من المفضلة"><i class="fa-solid fa-heart"></i></button><button class="add" type="button" data-add="${CM.esc(p.id)}" ${!p.available?'disabled':''} aria-label="${p.requiresChoices?'اختيار التفاصيل':'إضافة للسلة'}"><i class="${p.requiresChoices?'fa-solid fa-sliders':'fa-solid fa-bag-shopping'}"></i></button></div></article>`).join(''):`<div class="favorites-empty"><i class="fa-regular fa-heart"></i><h3>المفضلة فارغة</h3><p>احفظ البرجر والأصناف التي تحبها لتصل إليها بسرعة.</p><a href="${PRODUCTS_URL}">تصفّح المنيو</a></div>`}
document.addEventListener('click',e=>{const fav=e.target.closest('[data-fav]');if(fav){CM.toggleFavorite(fav.dataset.fav);renderFavorites();return}const add=e.target.closest('[data-add]');if(add){const p=CM.product(add.dataset.add);if(!p||!p.available)return;if(p.requiresChoices){location.href=productUrl(p.id);return}CM.addToCart(add.dataset.add,1)}});renderFavorites();
</script>
</body></html>