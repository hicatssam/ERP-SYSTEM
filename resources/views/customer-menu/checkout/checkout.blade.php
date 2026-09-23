<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>إتمام الطلب - {{ $branding['name'] ?? 'حلويات دهب' }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
@include('customer-menu.partials.pwa-head')
</head>
<body class="crisp-customer-menu">
<div class="app crisp-menu-app">
@include('customer-menu.partials.topbar', ['pageTitle' => 'إتمام الطلب', 'pageSubtitle' => $location->name, 'backUrl' => route('customer-menu.cart', $location->code)])

<main class="menu-area">
 <div class="shell">
  <div class="eta-banner" id="etaBanner" hidden>
   <i class="fa-solid fa-clock"></i>
   <span id="etaText"></span>
  </div>

  <div class="order-summary">
   <div id="summaryItems"></div>
   <div class="cart-total"><span>الإجمالي</span><span id="summaryTotal">0.00 ₪</span></div>
  </div>

  <form id="checkoutForm" enctype="multipart/form-data">
   <div class="checkout-grid">
    <label>الاسم<input name="name" required maxlength="120"></label>
    <label>رقم الجوال<input name="phone" required maxlength="20" inputmode="tel"></label>
    <label>نوع الطلب
     <select name="service_type" id="serviceType" required>
      @foreach(($serviceOptions ?? []) as $option)
       <option value="{{ $option['value'] ?? $option }}">{{ $option['label'] ?? $option }}</option>
      @endforeach
     </select>
    </label>
    <label id="tableField" class="full" hidden>
     <span>الطاولة</span>
     <div class="table-legend">
      <span><span class="dot" style="background:#22a35c"></span> متاحة</span>
      <span><span class="dot" style="background:#d64545"></span> مشغولة</span>
     </div>
     <div class="table-grid" id="tableGrid">
      @forelse(($tables ?? []) as $table)
       @php $isAvailable = (bool) ($table['available'] ?? true); @endphp
       <button type="button"
               class="table-card {{ $isAvailable ? 'available' : 'occupied' }}"
               data-table-id="{{ $table['id'] }}"
               {{ $isAvailable ? '' : 'disabled' }}>
        <span class="dot {{ $isAvailable ? 'available' : 'occupied' }}"></span>
        {{ $table['name'] ?? ('طاولة '.$table['id']) }}
        <small>{{ $isAvailable ? 'متاحة' : 'مشغولة' }}</small>
       </button>
      @empty
       <div class="empty" style="padding:18px">لا توجد طاولات مفعّلة لهذا الفرع.</div>
      @endforelse
     </div>
     <input type="hidden" name="restaurant_table_id" id="tableIdInput">
    </label>
    <label class="full" id="addressField" hidden>عنوان التوصيل<textarea name="address" maxlength="500"></textarea></label>
    <label class="full">ملاحظات الطلب<textarea name="notes" maxlength="700"></textarea></label>
   </div>

   <h3>طريقة الدفع</h3>
   <div class="payment-methods" id="paymentMethods">جاري تحميل طرق الدفع...</div>
   <div id="paymentAccountDetails"></div>
   <input type="hidden" name="payment_method_id" id="paymentMethodId">
   <input type="hidden" name="payment_account_id" id="paymentAccountId">

   <div id="paymentReferenceField" hidden style="margin-top:12px">
    <label>رقم مرجع العملية (اختياري)
     <input type="text" name="payment_reference" id="paymentReferenceInput" maxlength="150" placeholder="مثال: TX123456">
    </label>
   </div>

   <div id="paymentProofField" hidden style="margin-top:12px">
    <label class="upload-field" for="paymentProofInput">
     <i class="fa-solid fa-cloud-arrow-up"></i>
     <span>إرفاق إشعار إثبات الدفع (صورة أو PDF) <span class="required-star" id="proofRequiredStar" hidden>*</span></span>
     <input type="file" id="paymentProofInput" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,.pdf">
    </label>
    <div class="upload-preview" id="uploadPreview" hidden></div>
   </div>

   <div class="error" id="checkoutError"></div>
   <button class="submit-order" id="checkoutSubmit" type="submit" disabled>تأكيد وإرسال الطلب</button>
  </form>
 </div>
</main>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'cart'])
@include('customer-menu.partials.pwa-install')
@include('customer-menu.partials.cart-engine')

<script>
const CM = window.CustomerMenu;
const ORDER_URL = @json(route('customer-menu.orders.store', $location->code));
const PAYMENT_URL = @json(route('customer-menu.payment-options', $location->code));
const QUEUE_URL = @json(route('customer-menu.queue-status', $location->code));
const CART_URL = @json(route('customer-menu.cart', $location->code));
const TRACK_BASE = @json(route('customer-menu.show', $location->code));
const REQUEST_TOKEN = @json($requestToken ?? '');
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let methods = [];

function renderSummary() {
    const rows = CM.cartRows();
    if (!rows.length) { window.location.href = CART_URL; return; }
    document.getElementById('summaryItems').innerHTML = rows.map(x => {
        const modifierLine = (x.modifiers || []).length
            ? x.modifiers.map(m => `${m.name}${m.quantity > 1 ? ' ×' + m.quantity : ''}`).join('، ')
            : '';
        const subtitle = [x.variant_name, modifierLine].filter(Boolean).join(' — ');
        return `
      <div class="cart-row">
        <div class="cart-thumb">${x.image ? `<img src="${CM.esc(x.image)}" alt="">` : ''}</div>
        <div class="row-copy">
          <strong>${CM.esc(String(x.name || 'صنف').split(' - ')[0])}</strong>
          ${subtitle ? `<span style="display:block;font-size:.74rem;color:var(--muted)">${CM.esc(subtitle)}</span>` : ''}
          <small>${x.quantity} × ${CM.money(x.price)}</small>
        </div>
        <div class="row-copy"><strong>${CM.money(x.price * x.quantity)}</strong></div>
      </div>`;
    }).join('');
    document.getElementById('summaryTotal').textContent = CM.money(CM.cartTotal());
}

function serviceFields() {
    const value = document.getElementById('serviceType').value;
    document.getElementById('tableField').hidden = value !== 'dine_in';
    document.getElementById('addressField').hidden = value !== 'delivery';
}

document.getElementById('tableGrid').addEventListener('click', e => {
    const card = e.target.closest('.table-card');
    if (!card || card.disabled) return;
    document.querySelectorAll('.table-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    document.getElementById('tableIdInput').value = card.dataset.tableId;
});

async function loadPayments() {
    try {
        const response = await fetch(PAYMENT_URL, { headers: { Accept: 'application/json' } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'تعذر تحميل طرق الدفع');
        methods = Array.isArray(data.payment_methods || data.methods)
            ? (data.payment_methods || data.methods)
            : [];
        renderPayments();
        document.getElementById('checkoutSubmit').disabled = methods.length === 0;
    } catch (e) {
        methods = [];
        document.getElementById('checkoutSubmit').disabled = true;
        document.getElementById('paymentMethods').innerHTML =
            '<div class="error">تعذر تحميل طرق الدفع. حدّث الصفحة أو راجع إعدادات الفرع.</div>';
    }
}

function paymentIcon(type) {
    switch (String(type || '').toLowerCase()) {
        case 'cash': return 'fa-solid fa-money-bill-wave';
        case 'card_pos': return 'fa-solid fa-credit-card';
        case 'bank_transfer': return 'fa-solid fa-building-columns';
        case 'electronic_wallet': return 'fa-solid fa-wallet';
        default: return 'fa-solid fa-circle-dollar-to-slot';
    }
}

function renderPayments() {
    document.getElementById('paymentMethods').innerHTML = methods.length
        ? methods.map(m => `
          <button class="payment-card" type="button" data-pay="${m.id}">
            <span class="radio"></span>
            <span class="p-icon">${m.logo ? `<img src="${CM.esc(m.logo)}" alt="${CM.esc(m.name)}">` : `<i class="${paymentIcon(m.type)}"></i>`}</span>
            <span class="p-copy"><strong>${CM.esc(m.name)}</strong><small>${CM.esc(m.type || '')}</small></span>
          </button>`).join('')
        : 'لا توجد طرق دفع مفعلة.';
}

function choosePayment(id) {
    const method = methods.find(x => String(x.id) === String(id));
    if (!method) return;
    document.getElementById('paymentMethodId').value = method.id;
    document.querySelectorAll('[data-pay]').forEach(b => b.classList.toggle('active', String(b.dataset.pay) === String(id)));
    const accounts = method.accounts || [];
    document.getElementById('paymentAccountDetails').innerHTML = accounts.length
        ? `<label>الحساب<select id="paymentAccountSelect"><option value="">اختر الحساب</option>${accounts.map(a => `<option value="${a.id}">${CM.esc(a.name || a.provider_name || a.account_number || 'حساب')}</option>`).join('')}</select></label>`
        : '';

    const isPureCash = String(method.type || '').toLowerCase().includes('cash') && !method.requires_reference && !method.requires_verification;

    const refField = document.getElementById('paymentReferenceField');
    const refInput = document.getElementById('paymentReferenceInput');
    refField.hidden = isPureCash;
    if (isPureCash) refInput.value = '';

    const proofField = document.getElementById('paymentProofField');
    const proofInput = document.getElementById('paymentProofInput');
    const proofStar = document.getElementById('proofRequiredStar');
    proofField.hidden = isPureCash;
    proofInput.required = !!method.requires_verification;
    proofStar.hidden = !method.requires_verification;
    if (isPureCash) { proofInput.value = ''; document.getElementById('uploadPreview').hidden = true; }
}

document.getElementById('paymentProofInput').addEventListener('change', e => {
    const file = e.target.files[0];
    const preview = document.getElementById('uploadPreview');
    if (!file) { preview.hidden = true; return; }
    const isImage = file.type.startsWith('image/');
    preview.hidden = false;
    preview.innerHTML = `${isImage ? `<img src="${URL.createObjectURL(file)}" alt="">` : `<i class="fa-solid fa-file-pdf" style="font-size:1.6rem;color:var(--primary)"></i>`}<span>${CM.esc(file.name)}</span>`;
});

document.addEventListener('click', e => {
    const pay = e.target.closest('[data-pay]');
    if (pay) choosePayment(pay.dataset.pay);
});

document.addEventListener('change', e => {
    if (e.target.id === 'paymentAccountSelect') document.getElementById('paymentAccountId').value = e.target.value;
});

document.getElementById('serviceType').addEventListener('change', serviceFields);

document.getElementById('checkoutForm').addEventListener('submit', async e => {
    e.preventDefault();
    const rows = CM.cartRows();
    if (!rows.length) return;

    const serviceType = document.getElementById('serviceType').value;
    const paymentMethodId = document.getElementById('paymentMethodId').value;
    const tableId = document.getElementById('tableIdInput').value;
    const address = e.currentTarget.elements.address?.value?.trim() || '';
    const error = document.getElementById('checkoutError');

    if (serviceType === 'dine_in' && !tableId) {
        error.textContent = 'اختر طاولة متاحة لطلب داخل المطعم.';
        document.getElementById('tableField').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    if (serviceType === 'delivery' && !address) {
        error.textContent = 'أدخل عنوان التوصيل.';
        document.getElementById('addressField').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    if (!paymentMethodId) {
        error.textContent = 'اختر طريقة الدفع قبل تأكيد الطلب.';
        document.getElementById('paymentMethods').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    error.textContent = '';
    const button = document.getElementById('checkoutSubmit');
    button.disabled = true;
    button.textContent = 'جاري إرسال الطلب...';

    try {
        const formData = new FormData(e.currentTarget);
        formData.append('request_token', REQUEST_TOKEN);
        rows.forEach((row, i) => {
            formData.append(`items[${i}][product_id]`, row.product_id);
            formData.append(`items[${i}][quantity]`, row.quantity);
            if (row.variant_id) {
                formData.append(`items[${i}][product_variant_id]`, row.variant_id);
            }
            (row.modifiers || []).forEach((m, j) => {
                formData.append(`items[${i}][modifiers][${j}][modifier_id]`, m.modifier_id);
                formData.append(`items[${i}][modifiers][${j}][quantity]`, m.quantity);
            });
        });

        const response = await fetch(ORDER_URL, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: formData,
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'تعذر إرسال الطلب');

        CM.pushOrder({
            order_id: data.order_id || null,
            order_number: data.order_number,
            location: CM.LOCATION_CODE,
            url: data.track_url,
            status_url: data.status_url || null,
            state: data.state || 'received',
            status_label: data.status_label || 'تم استلام الطلب',
            created_at: data.created_at || new Date().toLocaleString('ar'),
        });

        CM.clearCart();
        CM.toast('تم استلام طلبك بنجاح');
        setTimeout(() => { window.location.href = data.track_url || TRACK_BASE; }, 700);
    } catch (err) {
        document.getElementById('checkoutError').textContent = err.message;
    } finally {
        button.disabled = false;
        button.textContent = 'تأكيد وإرسال الطلب';
    }
});

// Start checkout infrastructure first. Even if a malformed legacy cart row
// slips through, tables and payment methods must never remain stuck loading.
serviceFields();
loadPayments();
renderSummary();

(async function loadEta() {
    try {
        const response = await fetch(QUEUE_URL, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) return;

        const cartMax = Math.max(0, ...CM.cartRows().map(r => Number(r.prep_time_minutes) || 0));
        const basePrep = cartMax || Number(data.default_prep_minutes) || 12;
        const totalMinutes = basePrep + (Number(data.queue_count) || 0) * (Number(data.queue_minutes_per_order) || 0);

        document.getElementById('etaText').textContent = `الوقت المتوقع لتجهيز طلبك: حوالي ${totalMinutes} دقيقة`;
        document.getElementById('etaBanner').hidden = false;
    } catch (e) {
        // Silently skip the ETA banner if the queue endpoint is unreachable —
        // it's a nice-to-have, not something that should block checkout.
    }
})();
</script>
</body>
</html>
