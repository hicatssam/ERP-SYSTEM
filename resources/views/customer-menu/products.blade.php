<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>المنيو - {{ $branding['name'] ?? 'حلويات دهب' }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
@include('customer-menu.partials.pwa-head')
</head>
<body class="crisp-customer-menu">
<div class="app crisp-menu-app">
@include('customer-menu.partials.topbar', ['pageTitle' => 'المنيو الكامل', 'pageSubtitle' => $location->name])

<main class="menu-area" id="menu">
 <div class="shell">
  <div class="tools">
   <div class="search"><i class="fa-solid fa-magnifying-glass"></i><input id="searchInput" type="search" placeholder="ابحث عن صنفك المفضل..." autocomplete="off" value="{{ $initialSearch }}"></div>
   <button class="filter-square" type="button" id="openFilters" aria-label="تصفية الأصناف"><i class="fa-solid fa-sliders"></i></button>
  </div>

  <div class="categories" id="categories">
   <button class="cat {{ $initialCategory === 'all' || $initialCategory === '' ? 'active' : '' }}" type="button" data-cat="all"><b>الكل</b></button>
   @foreach(($categories ?? []) as $category)
    <button class="cat {{ (string) $initialCategory === (string) $category['id'] ? 'active' : '' }}" type="button" data-cat="{{ $category['id'] }}">
     @if(!empty($category['image']))<img src="{{ $category['image'] }}" alt="" onerror="this.remove()">@endif
     <b>{{ $category['name'] }}</b>
    </button>
   @endforeach
  </div>

  <div class="section-title"><div><h2>كل الأصناف</h2></div><small id="resultCount" style="color:var(--muted);font-size:.8rem"></small></div>
  <div class="grid" id="productGrid"></div>
 </div>
</main>
</div>

<div class="filter-sheet-overlay" id="filterOverlay">
 <div class="filter-sheet">
  <div class="filter-sheet-head">
   <button class="close-btn" type="button" id="closeFilters"><i class="fa-solid fa-xmark"></i></button>
   <h2>تصفية الأصناف</h2>
   <button type="button" id="resetFilters">إعادة ضبط</button>
  </div>

  <div class="filter-group">
   <h4>نطاق السعر</h4>
   <div class="chip-row" id="priceChips">
    <button class="chip active" type="button" data-price="all">الكل</button>
    <button class="chip" type="button" data-price="0-20">أقل من 20 ₪</button>
    <button class="chip" type="button" data-price="20-50">20 - 50 ₪</button>
    <button class="chip" type="button" data-price="50-999999">أكثر من 50 ₪</button>
   </div>
  </div>

  <div class="filter-group">
   <h4>الترتيب</h4>
   <div class="chip-row" id="sortChips">
    <button class="chip active" type="button" data-sort="default">الافتراضي</button>
    <button class="chip" type="button" data-sort="price_asc">السعر: من الأقل</button>
    <button class="chip" type="button" data-sort="price_desc">السعر: من الأعلى</button>
   </div>
  </div>

  <button class="filter-apply" type="button" id="applyFilters">عرض النتائج</button>
 </div>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'menu'])
@include('customer-menu.partials.pwa-install')
@include('customer-menu.partials.cart-engine')

<script>
const CM = window.CustomerMenu;
const PRODUCT_URL_BASE = @json(route('customer-menu.product.show', [$location->code, '__ID__']));
function productUrl(id) { return PRODUCT_URL_BASE.replace('__ID__', encodeURIComponent(id)); }

let activeCat = @json($initialCategory ?: 'all');
let priceRange = 'all';
let sortMode = 'default';

function renderCard(p) {
    const description = p.description || p.category_name || 'محضّر بعناية إلك';
    const favoriteLabel = CM.isFavorite(p.id) ? 'إزالة من المفضلة' : 'إضافة للمفضلة';
    const addLabel = !p.available ? 'غير متوفر' : (p.requiresChoices ? 'اختر الخيارات' : 'إضافة');

    return `<article class="product" data-id="${CM.esc(p.id)}">
      <a class="product-media" href="${productUrl(p.id)}" aria-label="عرض ${CM.esc(p.name)}">${CM.image(p)}</a>
      <span class="product-status${p.available ? '' : ' is-unavailable'}">${p.available ? 'متوفر' : 'غير متوفر'}</span>
      <button class="fav ${CM.isFavorite(p.id) ? 'active' : ''}" type="button" data-fav="${CM.esc(p.id)}" aria-label="${favoriteLabel}"><i class="${CM.isFavorite(p.id) ? 'fa-solid' : 'fa-regular'} fa-heart"></i></button>
      <div class="product-body">
        <a class="product-copy" href="${productUrl(p.id)}">
          <h3>${CM.esc(p.name)}</h3>
          <p class="product-desc">${CM.esc(description)}</p>
        </a>
        <div class="product-foot">
          <div class="product-price-row"><span class="price">${CM.money(p.price)}</span></div>
          <button class="details" type="button" data-add="${CM.esc(p.id)}"${p.available ? '' : ' disabled'}><i class="fa-solid fa-plus"></i><span>${addLabel}</span></button>
        </div>
      </div>
    </article>`;
}

function priceMatches(price) {
    if (priceRange === 'all') return true;
    const [min, max] = priceRange.split('-').map(Number);
    return price >= min && price <= max;
}

function renderProducts() {
    const q = document.getElementById('searchInput').value.trim().toLowerCase();
    let rows = CM.PRODUCTS.filter(p =>
        (activeCat === 'all' || p.category === String(activeCat)) &&
        priceMatches(Number(p.price)) &&
        (!q || (p.name + ' ' + p.description + ' ' + p.category_name).toLowerCase().includes(q))
    );

    if (sortMode === 'price_asc') rows = rows.slice().sort((a, b) => a.price - b.price);
    if (sortMode === 'price_desc') rows = rows.slice().sort((a, b) => b.price - a.price);

    document.getElementById('resultCount').textContent = rows.length + ' صنف';
    document.getElementById('productGrid').innerHTML = rows.length
        ? rows.map(renderCard).join('')
        : `<div class="empty">لا توجد أصناف مطابقة.</div>`;

    document.getElementById('openFilters').classList.toggle('has-filters', priceRange !== 'all' || sortMode !== 'default');
}

document.addEventListener('click', e => {
    const fav = e.target.closest('[data-fav]');
    if (fav) { e.preventDefault(); e.stopPropagation(); CM.toggleFavorite(fav.dataset.fav); renderProducts(); return; }

    const add = e.target.closest('[data-add]');
    if (add) {
        e.preventDefault(); e.stopPropagation();
        const p = CM.product(add.dataset.add);
        if (!p || !p.available) return;
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
    renderProducts();
});

document.getElementById('searchInput').addEventListener('input', renderProducts);

// filter sheet
const filterOverlay = document.getElementById('filterOverlay');
document.getElementById('openFilters').addEventListener('click', () => filterOverlay.classList.add('open'));
document.getElementById('closeFilters').addEventListener('click', () => filterOverlay.classList.remove('open'));
document.getElementById('applyFilters').addEventListener('click', () => { filterOverlay.classList.remove('open'); renderProducts(); });
filterOverlay.addEventListener('click', e => { if (e.target === filterOverlay) filterOverlay.classList.remove('open'); });

document.getElementById('priceChips').addEventListener('click', e => {
    const chip = e.target.closest('[data-price]'); if (!chip) return;
    priceRange = chip.dataset.price;
    document.querySelectorAll('#priceChips .chip').forEach(c => c.classList.toggle('active', c === chip));
});
document.getElementById('sortChips').addEventListener('click', e => {
    const chip = e.target.closest('[data-sort]'); if (!chip) return;
    sortMode = chip.dataset.sort;
    document.querySelectorAll('#sortChips .chip').forEach(c => c.classList.toggle('active', c === chip));
});
document.getElementById('resetFilters').addEventListener('click', () => {
    priceRange = 'all'; sortMode = 'default';
    document.querySelectorAll('#priceChips .chip').forEach(c => c.classList.toggle('active', c.dataset.price === 'all'));
    document.querySelectorAll('#sortChips .chip').forEach(c => c.classList.toggle('active', c.dataset.sort === 'default'));
    renderProducts();
});

renderProducts();
</script>
</body>
</html>
