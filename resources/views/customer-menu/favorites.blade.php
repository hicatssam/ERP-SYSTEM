<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>المفضلة - {{ $branding['name'] ?? 'حلويات دهب' }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
</head>
<body class="crisp-customer-menu">
<div class="app crisp-menu-app">
@include('customer-menu.partials.topbar', ['pageTitle' => 'المفضلة', 'pageSubtitle' => $location->name])

<main class="menu-area">
 <div class="shell">
  <div id="favoritesItems"></div>
 </div>
</main>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'favorites'])
@include('customer-menu.partials.cart-engine')

<script>
const CM = window.CustomerMenu;
const PRODUCT_URL_BASE = @json(route('customer-menu.product.show', [$location->code, '__ID__']));
const PRODUCTS_URL = @json(route('customer-menu.products', $location->code));

function renderFavorites() {
    const rows = CM.favorites().map(id => CM.product(id)).filter(Boolean);
    document.getElementById('favoritesItems').innerHTML = rows.length
        ? rows.map(p => `
          <div class="fav-row">
            <div class="fav-thumb">${CM.image(p)}</div>
            <div class="row-copy"><strong>${CM.esc(p.name)}</strong><small>${CM.money(p.price)}</small></div>
            <div class="row-actions">
              <a href="${PRODUCT_URL_BASE.replace('__ID__', p.id)}"><i class="fa-solid fa-eye"></i></a>
              <button type="button" data-fav="${p.id}"><i class="fa-solid fa-heart"></i></button>
              <button type="button" data-add="${p.id}"><i class="fa-solid fa-bag-shopping"></i></button>
            </div>
          </div>`).join('')
        : `<div class="empty">لا توجد أصناف في المفضلة. <br><a href="${PRODUCTS_URL}">تصفّح المنيو</a></div>`;
}

document.addEventListener('click', e => {
    const fav = e.target.closest('[data-fav]'); if (fav) { CM.toggleFavorite(fav.dataset.fav); renderFavorites(); return; }
    const add = e.target.closest('[data-add]');
    if (add) {
        const p = CM.product(add.dataset.add);
        if (p && p.requiresChoices) { window.location.href = PRODUCT_URL_BASE.replace('__ID__', p.id); return; }
        CM.addToCart(add.dataset.add, 1);
        return;
    }
});

renderFavorites();
</script>
</body>
</html>
