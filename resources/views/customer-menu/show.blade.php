<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $branding['name'] ?? 'حلويات دهب' }} - {{ $location->name }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
<style>
.intro{position:fixed;inset:0;z-index:1000;background:var(--primary);display:grid;place-items:center;transition:opacity .5s ease,visibility .5s ease}
.intro.hide{opacity:0;visibility:hidden;pointer-events:none}
.intro-box{text-align:center;color:#fff;padding:24px;animation:introIn .7s ease both}
.intro-plate{width:104px;height:104px;border:3px solid rgba(255,255,255,.85);border-radius:50%;display:grid;place-items:center;margin:0 auto 20px;font-size:2.6rem}
.intro-logo{width:104px;height:104px;object-fit:contain;margin:0 auto 20px;border-radius:50%;background:#fff}
.intro h1{margin:0;font-size:2rem;font-weight:900}
.intro .accent-bar{width:56px;height:4px;background:var(--accent);border-radius:4px;margin:12px auto}
.intro p{color:rgba(255,255,255,.85);margin:0;font-size:.95rem}
@keyframes introIn{from{opacity:0;transform:scale(.9) translateY(10px)}to{opacity:1;transform:none}}
</style>
</head>
<body class="crisp-customer-menu">

<div id="intro" class="intro" aria-hidden="true">
 <div class="intro-box">
  @if($branding['logo'] ?? null)
   <img class="intro-logo" src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }}">
  @else
   <div class="intro-plate"><i class="fa-solid fa-utensils"></i></div>
  @endif
  <h1>{{ $branding['name'] ?? 'حلويات دهب' }}</h1>
  <div class="accent-bar"></div>
  <p>{{ $branding['tagline'] ?: 'معكم بكل فرحة' }}</p>
 </div>
</div>

<div class="app crisp-menu-app">
<main class="menu-area">
 <div class="shell">

  <div class="greet-head">
   <div class="brandline">
    @if($branding['logo'] ?? null)<img src="{{ $branding['logo'] }}" alt="" onerror="this.outerHTML='<div class=&quot;fallback&quot;>{{ mb_substr($branding['name'] ?? 'د', 0, 1) }}</div>'">@else<div class="fallback">{{ mb_substr($branding['name'] ?? 'د', 0, 1) }}</div>@endif
    <b>{{ $location->name }}</b>
    <div class="head-actions">
     <a class="icon-btn" href="{{ route('customer-menu.favorites', $location->code) }}" aria-label="المفضلة"><i class="fa-regular fa-heart"></i><span class="badge" id="favBadgeTop">0</span></a>
     <a class="icon-btn" href="{{ route('customer-menu.cart', $location->code) }}" aria-label="السلة"><i class="fa-solid fa-bag-shopping"></i><span class="badge" id="cartBadgeTop">0</span></a>
    </div>
   </div>
   <h1 class="hello">أهلًا بك 👋</h1>
   <p>شو بتحب تاكل اليوم؟</p>
  </div>

  <div class="tools">
   <div class="search"><i class="fa-solid fa-magnifying-glass"></i><input id="searchInput" type="search" placeholder="ابحث عن صنفك المفضل..." autocomplete="off"></div>
   <button class="filter-square" type="button" onclick="window.location.href='{{ route('customer-menu.products', $location->code) }}'" aria-label="المنيو الكامل"><i class="fa-solid fa-sliders"></i></button>
  </div>

  <section class="promo-card">
   <i class="fa-solid fa-bowl-food"></i>
   <span class="tag">خصم 30%</span>
   <h3>على أول طلب إلك اليوم</h3>
   <p>لفترة محدودة، طلباتك المفضلة بسعر أقل.</p>
  </section>

  <div class="categories" id="categories">
   <button class="cat active" type="button" data-cat="all"><b>الكل</b></button>
   @foreach(($categories ?? []) as $category)
    <button class="cat" type="button" data-cat="{{ $category['id'] }}">
     @if(!empty($category['image']))<img src="{{ $category['image'] }}" alt="" onerror="this.remove()">@endif
     <b>{{ $category['name'] }}</b>
    </button>
   @endforeach
  </div>

  <div class="section-title">
   <h2>أفضل الاقتراحات</h2>
   <a href="{{ route('customer-menu.products', $location->code) }}">عرض الكل</a>
  </div>
  <div class="grid" id="productGrid"></div>
 </div>
</main>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'home'])
@include('customer-menu.partials.cart-engine')

<script>
const CM = window.CustomerMenu;
const PRODUCTS_URL = @json(route('customer-menu.products', $location->code));
const PRODUCT_URL_BASE = @json(route('customer-menu.product.show', [$location->code, '__ID__']));
function productUrl(id) { return PRODUCT_URL_BASE.replace('__ID__', id); }

let activeCat = 'all';

function renderCard(p) {
    return `<a class="product" href="${productUrl(p.id)}" data-id="${CM.esc(p.id)}">
      <div class="product-media">${CM.image(p)}<button class="fav ${CM.isFavorite(p.id) ? 'active' : ''}" type="button" data-fav="${CM.esc(p.id)}"><i class="${CM.isFavorite(p.id) ? 'fa-solid' : 'fa-regular'} fa-heart"></i></button></div>
      <div class="product-body">
        ${!p.available ? `<div class="sold-out">غير متوفر حالياً</div>` : ``}
        <h3>${CM.esc(p.name)}</h3>
        <div class="product-rating"><i class="fa-solid fa-star"></i> 4.8 <small>(+100)</small></div>
        <div class="product-foot">
          <span class="price">${CM.money(p.price)}</span>
          <button class="details" type="button" data-add="${CM.esc(p.id)}">${!p.available ? 'غير متوفر' : (p.requiresChoices ? 'اختر' : 'أضف')}</button>
        </div>
      </div>
    </a>`;
}

function renderGrid() {
    const q = document.getElementById('searchInput').value.trim().toLowerCase();
    const rows = CM.PRODUCTS.filter(p =>
        (activeCat === 'all' || p.category === String(activeCat)) &&
        (!q || (p.name + ' ' + p.description + ' ' + p.category_name).toLowerCase().includes(q))
    ).slice(0, q || activeCat !== 'all' ? undefined : 8);

    document.getElementById('productGrid').innerHTML = rows.length
        ? rows.map(renderCard).join('')
        : `<div class="empty">لا توجد أصناف متاحة حاليًا.</div>`;
}

document.addEventListener('click', e => {
    const fav = e.target.closest('[data-fav]');
    if (fav) { e.preventDefault(); e.stopPropagation(); CM.toggleFavorite(fav.dataset.fav); renderGrid(); return; }

    const add = e.target.closest('[data-add]');
    if (add) {
        e.preventDefault(); e.stopPropagation();
        const p = CM.product(add.dataset.add);
        if (p && p.requiresChoices) { window.location.href = productUrl(p.id); return; }
        CM.addToCart(add.dataset.add, 1);
        return;
    }
});

document.getElementById('categories').addEventListener('click', e => {
    const btn = e.target.closest('[data-cat]');
    if (!btn) return;
    activeCat = btn.dataset.cat;
    document.querySelectorAll('.cat').forEach(x => x.classList.toggle('active', x === btn));
    renderGrid();
});

document.getElementById('searchInput').addEventListener('input', renderGrid);

renderGrid();

(function intro() {
    const key = 'dahab_menu_intro_seen_' + CM.LOCATION_CODE;
    const el = document.getElementById('intro');
    if (sessionStorage.getItem(key) === '1') { el.remove(); return; }
    setTimeout(() => {
        el.classList.add('hide');
        sessionStorage.setItem(key, '1');
        setTimeout(() => el.remove(), 600);
    }, 1500);
})();
</script>
</body>
</html>
