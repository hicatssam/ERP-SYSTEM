<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $product['name'] }} - {{ $branding['name'] ?? 'المنيو' }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
@include('customer-menu.partials.pwa-head')
<style>
:root{--pd-max:440px}
html,body{background:var(--bg)}
body.crisp-customer-menu{margin:0;color:var(--text)}
.product-page{padding-bottom:168px}
.product-page .shell{width:min(var(--pd-max),calc(100% - 20px));margin:auto}

.pd-backbar{display:flex;align-items:center;justify-content:space-between;padding:14px 0 10px}
.pd-back,.pd-share{width:42px;height:42px;border:1px solid var(--line);border-radius:14px;background:var(--surface);display:grid;place-items:center;color:var(--text);box-shadow:0 5px 16px color-mix(in srgb,var(--text) 6%,transparent)}
.pd-backbar strong{font-size:.9rem;font-weight:900;color:var(--muted)}

.pd-hero{position:relative;border-radius:28px;overflow:hidden;background:color-mix(in srgb,var(--primary) 8%,var(--surface));aspect-ratio:1/1;box-shadow:0 18px 42px color-mix(in srgb,var(--text) 10%,transparent)}
.pd-hero img{width:100%;height:100%;object-fit:cover;display:block}
.pd-hero .product-placeholder{width:100%;height:100%;display:grid;place-items:center;font-size:5rem;font-weight:900;color:var(--primary)}
.pd-fav{position:absolute;top:14px;left:14px;width:46px;height:46px;border:0;border-radius:50%;background:rgba(255,255,255,.94);color:var(--text);display:grid;place-items:center;font-size:1.1rem;box-shadow:0 6px 18px rgba(0,0,0,.16)}
.pd-fav.active{color:var(--primary)}
.pd-status{position:absolute;right:14px;bottom:14px;border-radius:999px;padding:7px 12px;font-size:.72rem;font-weight:900;background:rgba(255,255,255,.94);color:var(--text);box-shadow:0 5px 16px rgba(0,0,0,.12)}
.pd-status.off{background:#fff0f0;color:#b42318}

.pd-info{padding:19px 4px 5px}
.pd-kicker{display:flex;align-items:center;gap:7px;color:var(--primary);font-size:.76rem;font-weight:900;margin-bottom:6px}
.pd-info h1{margin:0;font-size:1.55rem;line-height:1.3;font-weight:950;letter-spacing:-.025em}
.pd-description{margin:8px 0 0;color:var(--muted);font-size:.9rem;line-height:1.75}
.pd-meta-line{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:15px}
.pd-price{font-size:1.45rem;font-weight:950;color:var(--primary)}
.pd-prep{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-size:.76rem;font-weight:800}

.pd-card{margin-top:14px;background:var(--surface);border:1px solid var(--line);border-radius:22px;padding:15px;box-shadow:0 10px 28px color-mix(in srgb,var(--text) 5%,transparent)}
.pd-card-title{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}.pd-card-title h3{margin:0;font-size:1rem;font-weight:900}.pd-card-title small{color:var(--muted);font-size:.72rem}
.pd-qty-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
.pd-qty-copy strong{display:block;font-size:.95rem}.pd-qty-copy small{display:block;color:var(--muted);font-size:.72rem;margin-top:3px}
.pd-qty{display:flex;align-items:center;gap:10px;background:var(--bg);border-radius:15px;padding:5px}
.pd-qty button{width:36px;height:36px;border:0;border-radius:11px;background:var(--surface);color:var(--text);font-size:1.05rem;box-shadow:0 3px 10px color-mix(in srgb,var(--text) 6%,transparent)}
.pd-qty strong{min-width:24px;text-align:center}

#pickerContainer:empty{display:none}.picker-group{margin-top:14px;background:var(--surface);border:1px solid var(--line);border-radius:20px;padding:14px}.picker-group-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:9px}.picker-group-head h4{margin:0;font-size:.93rem}.picker-group-head small{color:var(--muted);font-size:.7rem}.picker-required{font-size:.67rem;font-weight:900;color:var(--primary);background:color-mix(in srgb,var(--primary) 8%,var(--surface));padding:5px 9px;border-radius:999px}.option-row{display:flex;align-items:center;gap:10px;padding:11px 4px;border-top:1px solid var(--line);cursor:pointer}.option-row:first-of-type{border-top:0}.mark{width:19px;height:19px;border:2px solid color-mix(in srgb,var(--muted) 45%,transparent);display:inline-block;flex:0 0 auto}.mark.round{border-radius:50%}.mark.square{border-radius:6px}.option-row.selected .mark{border-color:var(--primary);box-shadow:inset 0 0 0 4px var(--surface);background:var(--primary)}.opt-copy{flex:1;font-size:.82rem;font-weight:800}.opt-price{font-size:.74rem;font-weight:900;color:var(--muted)}.opt-price.has-cost{color:var(--primary)}.modifier-qty{display:flex;align-items:center;gap:5px}.modifier-qty button{width:25px;height:25px;border:0;border-radius:8px;background:var(--bg)}
.picker-error{margin-top:10px;padding:10px 12px;border-radius:12px;background:#fff0f0;color:#b42318;font-size:.78rem;font-weight:800}

.pd-section{margin-top:24px}.pd-section-head{display:flex;align-items:end;justify-content:space-between;gap:12px;margin-bottom:10px}.pd-section-head h2{margin:0;font-size:1.18rem;font-weight:950}.pd-section-head p{margin:4px 0 0;color:var(--muted);font-size:.75rem}.pd-section-head a{font-size:.74rem;font-weight:900;color:var(--primary)}
.upsell-row{display:flex;gap:10px;overflow-x:auto;scrollbar-width:none;padding:2px 1px 8px;scroll-snap-type:x proximity}.upsell-row::-webkit-scrollbar{display:none}.upsell-card{flex:0 0 148px;background:var(--surface);border:1px solid var(--line);border-radius:18px;overflow:hidden;scroll-snap-align:start;color:var(--text);box-shadow:0 8px 20px color-mix(in srgb,var(--text) 5%,transparent)}.upsell-media{height:108px;background:var(--bg);overflow:hidden}.upsell-media img{width:100%;height:100%;object-fit:cover}.upsell-media .product-placeholder{width:100%;height:100%;display:grid;place-items:center;color:var(--primary);font-size:1.5rem;font-weight:900}.upsell-body{padding:10px}.upsell-body h3{font-size:.8rem;margin:0 0 5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.upsell-foot{display:flex;align-items:center;justify-content:space-between;gap:5px}.upsell-price{font-size:.75rem;font-weight:950;color:var(--primary)}.upsell-go{width:28px;height:28px;border-radius:9px;background:var(--primary);color:#fff;display:grid;place-items:center;font-size:.65rem}

.pd-action-dock{position:fixed;z-index:120;left:50%;transform:translateX(-50%);bottom:calc(67px + env(safe-area-inset-bottom,0px));width:min(440px,calc(100% - 20px));background:color-mix(in srgb,var(--surface) 94%,transparent);backdrop-filter:blur(16px);border:1px solid var(--line);box-shadow:0 -10px 32px rgba(0,0,0,.10);border-radius:20px;padding:9px;display:grid;grid-template-columns:1fr auto;gap:8px}
.pd-order-now{height:52px;border:0;border-radius:15px;background:var(--primary);color:#fff;font-weight:950;font-size:.93rem;display:flex;align-items:center;justify-content:center;gap:8px}.pd-order-now:disabled{opacity:.5}.pd-add-more{height:52px;min-width:54px;border:1px solid var(--line);border-radius:15px;background:var(--surface);color:var(--primary);font-size:1rem}.pd-dock-price{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;padding:0 4px 2px;font-size:.73rem;color:var(--muted)}.pd-dock-price strong{font-size:.9rem;color:var(--text)}

.pd-toast{position:fixed;z-index:400;left:50%;transform:translate(-50%,14px);bottom:154px;background:#171717;color:#fff;border-radius:999px;padding:10px 16px;font-size:.78rem;font-weight:900;opacity:0;pointer-events:none;transition:.22s}.pd-toast.show{opacity:1;transform:translate(-50%,0)}

@media(max-width:390px){.product-page .shell{width:calc(100% - 16px)}.pd-hero{border-radius:24px}.pd-info h1{font-size:1.38rem}.pd-action-dock{width:calc(100% - 16px)}}
</style>
</head>
<body class="crisp-customer-menu">
<div class="app crisp-menu-app product-page">
<main class="menu-area">
 <div class="shell">
  <div class="pd-backbar">
   <a class="pd-back" href="{{ route('customer-menu.products', $location->code) }}" aria-label="رجوع"><i class="fa-solid fa-arrow-right"></i></a>
   <strong>{{ $location->name }}</strong>
   <button class="pd-share" id="detailShare" type="button" aria-label="مشاركة"><i class="fa-solid fa-arrow-up-from-bracket"></i></button>
  </div>

  <section class="pd-hero" id="detailMedia">
   @if(!empty($product['image']))
    <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" onerror="this.remove(); this.parentElement.insertAdjacentHTML('afterbegin','<div class=&quot;product-placeholder&quot;>{{ mb_substr($product['name'],0,1) }}</div>')">
   @else
    <div class="product-placeholder">{{ mb_substr($product['name'],0,1) }}</div>
   @endif
   <button class="pd-fav" type="button" id="detailFav" aria-label="أضف للمفضلة"><i class="fa-regular fa-heart"></i></button>
   <span class="pd-status {{ $product['available'] ? '' : 'off' }}">{{ $product['available'] ? 'متوفر الآن' : 'غير متوفر حالياً' }}</span>
  </section>

  <section class="pd-info">
   <div class="pd-kicker"><i class="fa-solid fa-utensils"></i><span>{{ $product['category'] ?? 'منيو' }}</span></div>
   <h1>{{ $product['name'] }}</h1>
   <p class="pd-description">{{ $product['description'] ?: 'صنف محضر بعناية وطازج عند الطلب.' }}</p>
   <div class="pd-meta-line">
    <div class="pd-price" id="detailPrice">{{ number_format($product['price'],2) }} ₪</div>
    @if(!empty($product['prep_time_minutes']))
     <span class="pd-prep"><i class="fa-regular fa-clock"></i> حوالي {{ $product['prep_time_minutes'] }} دقيقة</span>
    @endif
   </div>
  </section>

  <div id="pickerContainer"></div>

  <section class="pd-card">
   <div class="pd-qty-row">
    <div class="pd-qty-copy"><strong>الكمية</strong><small>اختر العدد المناسب لطلبك</small></div>
    <div class="pd-qty">
     <button type="button" id="detailMinus">−</button>
     <strong id="detailQty">1</strong>
     <button type="button" id="detailPlus">+</button>
    </div>
   </div>
   <div class="picker-error" id="pickerError" hidden></div>
  </section>

  @if(($upsellItems ?? collect())->isNotEmpty())
   <section class="pd-section">
    <div class="pd-section-head">
     <div><h2>كمّل طلبك 😋</h2><p>مقبلات ومشروبات بتزبط كثير مع اختيارك</p></div>
     <a href="{{ route('customer-menu.products', $location->code) }}">عرض الكل</a>
    </div>
    <div class="upsell-row">
     @foreach($upsellItems as $extra)
      <a class="upsell-card" href="{{ route('customer-menu.product.show', [$location->code,$extra['product_id']]) }}">
       <div class="upsell-media">
        @if(!empty($extra['image']))<img src="{{ $extra['image'] }}" alt="{{ $extra['name'] }}">@else<div class="product-placeholder">{{ mb_substr($extra['name'],0,1) }}</div>@endif
       </div>
       <div class="upsell-body">
        <h3>{{ $extra['name'] }}</h3>
        <div class="upsell-foot"><span class="upsell-price">{{ number_format($extra['price'],2) }} ₪</span><span class="upsell-go"><i class="fa-solid fa-arrow-left"></i></span></div>
       </div>
      </a>
     @endforeach
    </div>
   </section>
  @endif

  @if($relatedItems->isNotEmpty())
   <section class="pd-section">
    <div class="pd-section-head"><div><h2>خيارات مشابهة</h2><p>إذا حاب تجرب شيء ثاني من نفس الفئة</p></div></div>
    <div class="upsell-row">
     @foreach($relatedItems as $related)
      <a class="upsell-card" href="{{ route('customer-menu.product.show', [$location->code,$related['product_id']]) }}">
       <div class="upsell-media">@if(!empty($related['image']))<img src="{{ $related['image'] }}" alt="{{ $related['name'] }}">@else<div class="product-placeholder">{{ mb_substr($related['name'],0,1) }}</div>@endif</div>
       <div class="upsell-body"><h3>{{ $related['name'] }}</h3><div class="upsell-foot"><span class="upsell-price">{{ number_format($related['price'],2) }} ₪</span><span class="upsell-go"><i class="fa-solid fa-arrow-left"></i></span></div></div>
      </a>
     @endforeach
    </div>
   </section>
  @endif
 </div>
</main>
</div>

<div class="pd-action-dock">
 <div class="pd-dock-price"><span>الإجمالي الحالي</span><strong id="dockTotal">{{ number_format($product['price'],2) }} ₪</strong></div>
 <button class="pd-order-now" id="detailOrderNow" type="button" {{ $product['available'] ? '' : 'disabled' }}><i class="fa-solid fa-bag-shopping"></i><span>{{ $product['available'] ? 'اطلب الآن' : 'غير متوفر' }}</span></button>
 <button class="pd-add-more" id="detailAdd" type="button" title="أضف للسلة وأكمل التسوق" {{ $product['available'] ? '' : 'disabled' }}><i class="fa-solid fa-plus"></i></button>
</div>
<div class="pd-toast" id="pdToast">تمت الإضافة للسلة</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'menu'])
@include('customer-menu.partials.pwa-install')
@include('customer-menu.partials.cart-engine')

<script>
const CM = window.CustomerMenu;
const PRODUCT_ID = @json($product['product_id']);
const PRODUCT = CM.product(PRODUCT_ID);
let qty = 1;
let selectedVariantId = null;
const selectedModifiers = {};
const variants = PRODUCT.variants || [];
const modifierGroups = PRODUCT.modifierGroups || [];

if (PRODUCT.isVariantProduct && variants.length) {
    const def = variants.find(v => v.is_default) || variants[0];
    selectedVariantId = def.id;
}
modifierGroups.forEach(group => (group.modifiers || []).forEach(m => { if (m.is_default) selectedModifiers[m.id] = 1; }));

function updateMedia(){
    if(!PRODUCT.isVariantProduct)return;
    const v=variants.find(v=>String(v.id)===String(selectedVariantId));
    if(!v||!v.image)return;
    const media=document.getElementById('detailMedia');
    let img=media.querySelector('img');
    if(!img){img=document.createElement('img');media.querySelector('.product-placeholder')?.remove();media.prepend(img)}
    img.src=v.image;img.alt=PRODUCT.name;
}
function currentPrice(){
    let base=Number(PRODUCT.price);
    if(PRODUCT.isVariantProduct){const v=variants.find(v=>String(v.id)===String(selectedVariantId));if(v)base=Number(v.price)}
    modifierGroups.forEach(group=>(group.modifiers||[]).forEach(m=>{if(selectedModifiers[m.id])base+=Number(m.price_delta)*selectedModifiers[m.id]}));
    return base;
}
function updatePriceDisplay(){
    const unit=currentPrice();
    document.getElementById('detailPrice').textContent=unit.toFixed(2)+' ₪';
    document.getElementById('dockTotal').textContent=(unit*qty).toFixed(2)+' ₪';
    updateMedia();
}
function renderPicker(){
    let html='';
    if(PRODUCT.isVariantProduct&&variants.length){
        html+=`<div class="picker-group"><div class="picker-group-head"><h4>الحجم / النوع</h4><span class="picker-required">مطلوب</span></div>${variants.map(v=>`<div class="option-row ${String(v.id)===String(selectedVariantId)?'selected':''}" data-variant="${v.id}"><span class="mark round"></span><span class="opt-copy">${CM.esc(v.name)}</span><span class="opt-price">${Number(v.price).toFixed(2)} ₪</span></div>`).join('')}</div>`;
    }
    modifierGroups.forEach(group=>{
        const isSingle=group.selection_type==='single'||group.max===1;
        const hint=group.required?(group.max&&group.max>1?`اختر من ${group.min} إلى ${group.max}`:'اختيار مطلوب'):(group.max?`اختر حتى ${group.max}`:'اختياري');
        html+=`<div class="picker-group" data-group="${group.id}"><div class="picker-group-head"><h4>${CM.esc(group.name)}</h4>${group.required?`<span class="picker-required">${hint}</span>`:`<small>${hint}</small>`}</div>${(group.modifiers||[]).map(m=>{const checked=!!selectedModifiers[m.id];const qtyNow=selectedModifiers[m.id]||1;return `<div class="option-row ${checked?'selected':''}" data-modifier="${m.id}" data-group="${group.id}" data-single="${isSingle?1:0}"><span class="mark ${isSingle?'round':'square'}"></span><span class="opt-copy">${CM.esc(m.name)}</span>${m.allow_quantity&&checked?`<span class="modifier-qty"><button type="button" data-qty-minus="${m.id}">−</button><span>${qtyNow}</span><button type="button" data-qty-plus="${m.id}" data-max="${m.max_quantity}">+</button></span>`:''}<span class="opt-price ${Number(m.price_delta)?'has-cost':''}">${Number(m.price_delta)?'+'+Number(m.price_delta).toFixed(2)+' ₪':''}</span></div>`}).join('')}</div>`;
    });
    document.getElementById('pickerContainer').innerHTML=html;updatePriceDisplay();
}
function validateSelection(){
    if(PRODUCT.isVariantProduct&&variants.length&&!selectedVariantId)return 'اختر الحجم/النوع أولاً.';
    for(const group of modifierGroups){const count=(group.modifiers||[]).filter(m=>selectedModifiers[m.id]).length;const min=group.required?Math.max(1,group.min):group.min;if(count<min)return `مجموعة "${group.name}" تتطلب اختيار ${min} على الأقل.`;if(group.max&&count>group.max)return `مجموعة "${group.name}" تسمح بحد أقصى ${group.max}.`;}
    return null;
}
function cartPayload(){return Object.entries(selectedModifiers).map(([modifier_id,quantity])=>({modifier_id:Number(modifier_id),quantity}));}
function addConfiguredProduct(){
    const error=validateSelection();const errorBox=document.getElementById('pickerError');
    if(error){errorBox.textContent=error;errorBox.hidden=false;errorBox.scrollIntoView({behavior:'smooth',block:'center'});return false}
    errorBox.hidden=true;
    return CM.addToCart(PRODUCT_ID,qty,{variantId:selectedVariantId,modifiers:cartPayload()});
}
function showToast(text='تمت الإضافة للسلة'){
    const toast=document.getElementById('pdToast');toast.textContent=text;toast.classList.add('show');setTimeout(()=>toast.classList.remove('show'),1200);
}

document.getElementById('pickerContainer').addEventListener('click',e=>{
    const variantRow=e.target.closest('[data-variant]');if(variantRow){selectedVariantId=variantRow.dataset.variant;renderPicker();return}
    const qtyMinus=e.target.closest('[data-qty-minus]');if(qtyMinus){const id=qtyMinus.dataset.qtyMinus;selectedModifiers[id]=Math.max(1,(selectedModifiers[id]||1)-1);renderPicker();return}
    const qtyPlus=e.target.closest('[data-qty-plus]');if(qtyPlus){const id=qtyPlus.dataset.qtyPlus;const max=Number(qtyPlus.dataset.max||20);selectedModifiers[id]=Math.min(max,(selectedModifiers[id]||1)+1);renderPicker();return}
    const modRow=e.target.closest('[data-modifier]');if(modRow){const id=modRow.dataset.modifier;const groupId=modRow.dataset.group;const isSingle=modRow.dataset.single==='1';if(selectedModifiers[id])delete selectedModifiers[id];else{if(isSingle){modifierGroups.find(g=>String(g.id)===String(groupId))?.modifiers.forEach(m=>delete selectedModifiers[m.id])}selectedModifiers[id]=1}renderPicker();}
});

renderPicker();
document.getElementById('detailPlus').onclick=()=>{qty=Math.min(50,qty+1);document.getElementById('detailQty').textContent=qty;updatePriceDisplay()};
document.getElementById('detailMinus').onclick=()=>{qty=Math.max(1,qty-1);document.getElementById('detailQty').textContent=qty;updatePriceDisplay()};

document.getElementById('detailOrderNow').onclick=()=>{if(!addConfiguredProduct())return;window.location.href=@json(route('customer-menu.cart',$location->code));};
document.getElementById('detailAdd').onclick=()=>{if(!addConfiguredProduct())return;showToast('تمت الإضافة — كمل اختيارك براحتك');};

const favBtn=document.getElementById('detailFav');
function paintFav(){const active=CM.isFavorite(PRODUCT_ID);favBtn.classList.toggle('active',active);favBtn.innerHTML=`<i class="fa-${active?'solid':'regular'} fa-heart"></i>`}
favBtn.addEventListener('click',()=>{CM.toggleFavorite(PRODUCT_ID);paintFav()});paintFav();

document.getElementById('detailShare')?.addEventListener('click',async()=>{
    const data={title:PRODUCT.name,text:PRODUCT.name,url:window.location.href};
    if(navigator.share){try{await navigator.share(data)}catch(e){}}else{try{await navigator.clipboard.writeText(window.location.href);showToast('تم نسخ رابط المنتج')}catch(e){}}
});
</script>
</body>
</html>
