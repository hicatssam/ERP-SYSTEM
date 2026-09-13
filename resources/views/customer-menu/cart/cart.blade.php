<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>سلة الطلب - {{ $branding['name'] ?? 'حلويات دهب' }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
</head>
<body class="crisp-customer-menu">
<div class="app crisp-menu-app">
@include('customer-menu.partials.topbar', ['pageTitle' => 'سلة الطلب', 'pageSubtitle' => $location->name])

<main class="menu-area">
 <div class="shell">
  <div id="cartItems"></div>

  <div class="order-summary">
   <div class="summary-row"><span>عدد الأصناف</span><span id="itemCount">0</span></div>
   <div class="cart-total"><span>الإجمالي</span><span id="cartTotal">0.00 ₪</span></div>
   <div class="cart-note">قد تُضاف رسوم توصيل حسب نوع الطلب — تظهر بعد اختيار طريقة الاستلام بالخطوة التالية.</div>
  </div>
  <button class="checkout-start" id="checkoutStart" type="button">إتمام الطلب</button>
 </div>
</main>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'cart'])
@include('customer-menu.partials.cart-engine')

<script>
const CM = window.CustomerMenu;

function renderCart() {
    const rows = CM.cartRows();
    document.getElementById('cartItems').innerHTML = rows.length
        ? rows.map(x => {
            const modifierLine = (x.modifiers || []).length
                ? x.modifiers.map(m => `${m.name}${m.quantity > 1 ? ' ×' + m.quantity : ''}`).join('، ')
                : '';
            const subtitle = [x.variant_name, modifierLine].filter(Boolean).join(' — ');
            return `
          <div class="cart-row">
            <div class="cart-thumb">${x.image ? `<img src="${CM.esc(x.image)}" alt="">` : ''}</div>
            <div class="row-copy">
              <strong>${CM.esc(x.name.split(' - ')[0])}</strong>
              ${subtitle ? `<span style="display:block;font-size:.74rem;color:var(--muted)">${CM.esc(subtitle)}</span>` : ''}
              <small>${CM.money(x.price * x.quantity)}</small>
            </div>
            <div class="row-actions">
              <button type="button" data-dec="${CM.esc(x.key)}">−</button>
              <b>${x.quantity}</b>
              <button type="button" data-inc="${CM.esc(x.key)}">+</button>
              <button type="button" data-remove="${CM.esc(x.key)}" aria-label="حذف"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>`;
        }).join('')
        : `<div class="empty">السلة فارغة. <br><a href="${@json(route('customer-menu.products', $location->code))}">تصفّح المنيو</a></div>`;

    document.getElementById('itemCount').textContent = rows.reduce((s, r) => s + Number(r.quantity), 0);
    document.getElementById('cartTotal').textContent = CM.money(CM.cartTotal());
    document.getElementById('checkoutStart').disabled = !rows.length;
}

document.addEventListener('click', e => {
    const inc = e.target.closest('[data-inc]'); if (inc) { CM.changeCart(inc.dataset.inc, 1); renderCart(); return; }
    const dec = e.target.closest('[data-dec]'); if (dec) { CM.changeCart(dec.dataset.dec, -1); renderCart(); return; }
    const rm = e.target.closest('[data-remove]'); if (rm) { CM.removeFromCart(rm.dataset.remove); renderCart(); return; }
});

document.getElementById('checkoutStart').addEventListener('click', () => {
    if (!CM.cartRows().length) return;
    window.location.href = @json(route('customer-menu.checkout', $location->code));
});

renderCart();
</script>
</body>
</html>
