<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $branding['name'] ?? 'حلويات دهب' }} - {{ $location->name }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
@php
    $brandLogo = $branding['logo'] ?? $branding['logo_small'] ?? null;
    $cover = $theme['cover'] ?? null;
    $brandName = $branding['name'] ?? 'حلويات دهب';
    $tagline = $branding['tagline'] ?? 'معكم بكل فرحة';
@endphp
<style>
:root{
 --gold:{{ $theme['accent'] ?? '#C9A84C' }};--gold2:#f0d68b;--dark:#090704;--dark2:#151009;
 --surface:#fffaf0;--ink:#24180b;--muted:#7b6b58;--line:#eadfc8;--danger:#a83a2e;
 --safe-bottom:env(safe-area-inset-bottom,0px);--shadow:0 18px 50px rgba(24,14,4,.14)
}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Tajawal,sans-serif;background:#f7f1e7;color:var(--ink)}
body{overflow-x:hidden}button,input,select,textarea{font:inherit}button{cursor:pointer}img{display:block;max-width:100%}
[hidden]{display:none!important}.no-scroll{overflow:hidden!important}
.app{width:100%;min-height:100vh}.shell{width:min(1180px,calc(100% - 32px));margin:auto}
.topbar{position:sticky;top:0;z-index:80;background:rgba(9,7,4,.94);backdrop-filter:blur(16px);color:white;border-bottom:1px solid rgba(201,168,76,.18)}
.topbar-inner{height:72px;display:flex;align-items:center;gap:14px}.brand{display:flex;align-items:center;gap:10px;min-width:0}
.brand img{width:44px;height:44px;object-fit:contain}.brand-fallback{width:44px;height:44px;border:1px solid var(--gold);border-radius:50%;display:grid;place-items:center;color:var(--gold);font-weight:900}
.brand-copy{min-width:0}.brand-copy strong{display:block;color:var(--gold2);font-size:1.05rem}.brand-copy small{display:block;color:#c9bea9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.top-actions{margin-inline-start:auto;display:flex;gap:8px}.icon-btn{width:42px;height:42px;border:1px solid rgba(201,168,76,.28);border-radius:14px;background:#171108;color:var(--gold2);position:relative}
.badge{position:absolute;top:-5px;left:-5px;min-width:19px;height:19px;padding:0 5px;border-radius:20px;background:var(--gold);color:#100b04;font-size:11px;font-weight:900;display:grid;place-items:center}
.hero{background:linear-gradient(180deg,#0b0805 0%,#171006 100%);color:white;position:relative;overflow:hidden}
.hero.has-cover:before{content:"";position:absolute;inset:0;background-image:var(--cover);background-size:cover;background-position:center;opacity:.35}
.hero:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(7,5,3,.12),rgba(7,5,3,.82))}
.hero-inner{position:relative;z-index:2;min-height:330px;padding:70px 0 54px;display:flex;flex-direction:column;justify-content:flex-end;align-items:flex-start}
.hero-kicker{color:var(--gold2);font-weight:700}.hero h1{font-size:clamp(2rem,6vw,4.2rem);margin:7px 0 5px;line-height:1.05}.hero p{margin:0;color:#ddd1bd;font-size:1.04rem}
.hero-cta{display:flex;gap:10px;margin-top:22px}.gold-btn,.ghost-btn{border:0;border-radius:999px;padding:12px 20px;font-weight:800}.gold-btn{background:linear-gradient(135deg,var(--gold),var(--gold2));color:#181006}.ghost-btn{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.18);color:white}
.menu-area{padding:28px 0 110px}.tools{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;margin-bottom:16px}
.search{position:relative}.search i{position:absolute;right:16px;top:50%;transform:translateY(-50%);color:#9a876d}.search input{width:100%;height:50px;border:1px solid var(--line);border-radius:17px;background:white;padding:0 45px 0 15px;outline:none}
.search input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,168,76,.13)}
.branch-pill{height:50px;padding:0 16px;border-radius:17px;border:1px solid var(--line);background:white;color:#6d5638;font-weight:700}
.categories{display:flex;gap:9px;overflow:auto;padding:3px 0 15px;scrollbar-width:none}.categories::-webkit-scrollbar{display:none}
.cat{flex:0 0 auto;border:1px solid var(--line);background:white;border-radius:999px;padding:9px 15px;color:#67563f;font-weight:700}
.cat.active{background:#171006;color:var(--gold2);border-color:#171006}
.section-title{display:flex;align-items:end;justify-content:space-between;margin:8px 0 15px}.section-title h2{margin:0;font-size:1.35rem}.section-title small{color:var(--muted)}
.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.product{background:white;border:1px solid #eee2cc;border-radius:23px;overflow:hidden;box-shadow:0 7px 24px rgba(45,27,7,.07);position:relative;transition:.25s}
.product:hover{transform:translateY(-3px);box-shadow:var(--shadow)}.product{cursor:pointer}.product:focus-visible{outline:3px solid rgba(201,168,76,.28);outline-offset:3px}.product-media{height:210px;background:#efe6d6;position:relative;overflow:hidden}
.product-media img{width:100%;height:100%;object-fit:cover}.product-placeholder{width:100%;height:100%;display:grid;place-items:center;background:radial-gradient(circle,#f5e5bd,#e7d5ae);font-size:2rem;font-weight:900;color:#a7823b}
.fav{position:absolute;top:11px;left:11px;width:39px;height:39px;border:0;border-radius:50%;background:rgba(255,255,255,.92);color:#7b6548;box-shadow:0 4px 15px rgba(0,0,0,.1);z-index:2}.fav.active{color:#b92828}
.product-body{padding:15px}.sold-out{display:inline-block;margin:2px 0 5px;padding:3px 8px;border-radius:999px;background:#f7ece8;color:#9b3329;font-size:.72rem;font-weight:800}.product h3{font-size:1.04rem;margin:0 0 5px}.product-desc{height:42px;overflow:hidden;color:var(--muted);font-size:.86rem;line-height:1.55}
.product-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:13px}.price{font-weight:900;color:#8a6116}.details{border:0;background:#171006;color:var(--gold2);border-radius:12px;padding:9px 12px;font-weight:800}
.empty{grid-column:1/-1;text-align:center;padding:55px 20px;color:var(--muted);background:white;border-radius:20px;border:1px dashed var(--line)}
.overlay{position:fixed;inset:0;z-index:120;background:rgba(5,3,2,.68);backdrop-filter:blur(5px);display:none;align-items:flex-end;justify-content:center;padding:18px}
.overlay.open{display:flex}.sheet{width:min(650px,100%);max-height:min(88vh,820px);overflow:auto;background:#fffaf1;border-radius:28px;box-shadow:0 30px 100px rgba(0,0,0,.4);position:relative}
.sheet-close{position:sticky;float:left;top:14px;left:14px;z-index:4;width:42px;height:42px;border:0;border-radius:50%;background:#171006;color:var(--gold2);margin:14px}
.detail-media{height:300px;background:#eadfc8}.detail-media img{width:100%;height:100%;object-fit:cover}.detail-body{padding:22px}.detail-body h2{margin:0 0 7px;font-size:1.55rem}.detail-description{color:var(--muted);line-height:1.8;white-space:pre-line}
.detail-meta{display:flex;justify-content:space-between;align-items:center;margin-top:18px;padding-top:16px;border-top:1px solid var(--line)}.detail-price{font-size:1.35rem;font-weight:900;color:#8a6116}
.qty{display:flex;align-items:center;gap:13px}.qty button{width:38px;height:38px;border-radius:12px;border:1px solid var(--line);background:white;font-size:1.2rem}.qty strong{min-width:24px;text-align:center}
.add-detail{width:100%;border:0;border-radius:16px;background:linear-gradient(135deg,#b78a2c,#ead083);color:#171006;padding:14px;font-weight:900;font-size:1rem;margin-top:18px}.add-detail:disabled{opacity:.55;cursor:not-allowed}.detail-cart-link{width:100%;border:0;background:transparent;color:#6e5634;padding:12px;font-weight:800}.detail-cart-link span{display:inline-grid;place-items:center;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:#171006;color:var(--gold2);margin-inline-start:5px}
.drawer-title{padding:22px 22px 12px}.drawer-title h2{margin:0}.drawer-content{padding:0 22px 24px}
.cart-row,.fav-row{display:grid;grid-template-columns:62px 1fr auto;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid var(--line)}
.cart-thumb,.fav-thumb{width:62px;height:62px;border-radius:15px;background:#eee1c8;overflow:hidden}.cart-thumb img,.fav-thumb img{width:100%;height:100%;object-fit:cover}
.row-copy strong{display:block}.row-copy small{color:#8a6116;font-weight:800}.row-actions{display:flex;align-items:center;gap:7px}.row-actions button{border:1px solid var(--line);background:white;border-radius:9px;min-width:31px;height:31px}
.cart-total{display:flex;justify-content:space-between;font-size:1.15rem;font-weight:900;padding:17px 0}.checkout-start{width:100%;border:0;border-radius:15px;background:#171006;color:var(--gold2);padding:14px;font-weight:900}
.checkout{padding:0 22px 28px}.checkout-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.checkout-grid label{font-size:.83rem;font-weight:700;color:#5e4c36}.checkout-grid input,.checkout-grid select,.checkout-grid textarea{display:block;width:100%;margin-top:6px;border:1px solid var(--line);border-radius:13px;background:white;padding:11px;outline:none}.checkout-grid textarea{min-height:85px;resize:vertical}.full{grid-column:1/-1}
.payment-methods{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin:10px 0}.payment-card{border:1px solid var(--line);background:white;border-radius:14px;padding:12px;text-align:right}.payment-card.active{border-color:var(--gold);box-shadow:0 0 0 2px rgba(201,168,76,.15)}
.error{color:#a52f24;font-size:.85rem;margin:8px 0}.submit-order{width:100%;border:0;border-radius:15px;background:linear-gradient(135deg,#b78a2c,#ead083);padding:14px;font-weight:900}
.orders-list{display:grid;gap:10px}.order-card{border:1px solid var(--line);border-radius:16px;padding:14px;background:white}.order-card a{color:#8a6116;font-weight:800;text-decoration:none}
.bottom-nav{display:none}
.intro{position:fixed;inset:0;z-index:1000;background:#080604;display:grid;place-items:center;transition:opacity .55s ease,visibility .55s ease}
.intro.hide{opacity:0;visibility:hidden;pointer-events:none}.intro-box{text-align:center;color:white;padding:24px;animation:introIn .8s ease both}.intro-logo{width:132px;height:132px;object-fit:contain;margin:0 auto 18px;filter:drop-shadow(0 10px 24px rgba(0,0,0,.28))}
.intro-mark{width:120px;height:120px;border:1px solid var(--gold);border-radius:50%;display:grid;place-items:center;margin:0 auto 18px;color:var(--gold2);font-size:2rem;font-weight:900;box-shadow:0 10px 30px rgba(0,0,0,.22)}
.intro h1{color:var(--gold2);margin:0;font-size:1.9rem}.intro p{color:#cfc2aa;margin:7px 0 0}@keyframes introIn{from{opacity:0;transform:scale(.92) translateY(8px)}to{opacity:1;transform:none}}
.toast{position:fixed;z-index:1500;bottom:100px;left:50%;transform:translate(-50%,20px);background:#171006;color:white;border:1px solid rgba(201,168,76,.3);padding:10px 16px;border-radius:999px;opacity:0;pointer-events:none;transition:.25s}.toast.show{opacity:1;transform:translate(-50%,0)}
@media(max-width:780px){
 html,body,.app{width:100%!important;max-width:none!important;min-width:0!important}.shell{width:100%!important;max-width:none!important;padding-inline:14px}
 .topbar-inner{height:64px}.top-actions .desktop-only{display:none}.brand-copy strong{font-size:.95rem}.brand-copy small{max-width:180px}
 .hero-inner{min-height:270px;padding:52px 14px 36px}.hero h1{font-size:2.35rem}.hero-cta{width:100%}.hero-cta button{flex:1;padding-inline:10px}
 .menu-area{padding-top:18px;padding-bottom:108px}.tools{grid-template-columns:1fr}.branch-pill{display:none}.grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
 .product{border-radius:18px}.product-media{height:145px}.product-body{padding:11px}.product h3{font-size:.91rem}.product-desc{font-size:.76rem;height:36px}.product-foot{align-items:flex-end}.price{font-size:.86rem}.details{padding:8px 9px;font-size:.76rem}
 .fav{width:35px;height:35px}.overlay{padding:0;align-items:flex-end}.sheet{border-radius:27px 27px 0 0;max-height:91dvh;padding-bottom:var(--safe-bottom)}
 .detail-media{height:260px}.checkout-grid{grid-template-columns:1fr}.full{grid-column:auto}.payment-methods{grid-template-columns:1fr}
 .bottom-nav{position:fixed;z-index:100;bottom:0;right:0;left:0;height:calc(72px + var(--safe-bottom));padding:7px 8px var(--safe-bottom);background:rgba(13,9,5,.97);border-top:1px solid rgba(201,168,76,.2);display:grid;grid-template-columns:repeat(5,1fr);align-items:center;backdrop-filter:blur(16px)}
 .nav-btn{height:58px;border:0;background:transparent;color:#b7aa96;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;font-size:10px;position:relative}.nav-btn i{font-size:18px}.nav-btn.active{color:var(--gold2)}
 .nav-cart{width:58px;height:58px;border-radius:50%;background:linear-gradient(135deg,var(--gold),var(--gold2));color:#171006;margin:-29px auto 0;box-shadow:0 8px 25px rgba(201,168,76,.35)}.nav-cart i{font-size:21px}
 .nav-badge{position:absolute;top:1px;left:50%;margin-left:-22px;min-width:18px;height:18px;border-radius:12px;background:#fff;color:#171006;font-size:10px;font-weight:900;display:grid;place-items:center;padding:0 4px}
 .top-actions{display:none}.cart-row,.fav-row{grid-template-columns:54px 1fr auto}.cart-thumb,.fav-thumb{width:54px;height:54px}
}
@media(max-width:390px){.grid{gap:8px}.product-media{height:128px}.product-desc{display:none}.details{font-size:.7rem}.price{font-size:.78rem}}
</style>
</head>
<body>
<div id="intro" class="intro" aria-hidden="true">
 <div class="intro-box">
  @if($brandLogo)<img class="intro-logo" src="{{ $brandLogo }}" alt="{{ $brandName }}">@else<div class="intro-mark">دهب</div>@endif
  <h1>{{ $brandName }}</h1><p>{{ $tagline ?: 'معكم بكل فرحة' }}</p>
 </div>
</div>

<div class="app">
<header class="topbar">
 <div class="shell topbar-inner">
  <div class="brand">
   @if($brandLogo)<img src="{{ $brandLogo }}" alt="{{ $brandName }}">@else<div class="brand-fallback">د</div>@endif
   <div class="brand-copy"><strong>{{ $brandName }}</strong><small>{{ $location->name }}</small></div>
  </div>
  <div class="top-actions">
   <button class="icon-btn" type="button" data-open="favorites" aria-label="المفضلة"><i class="fa-regular fa-heart"></i><span class="badge" id="favBadgeTop">0</span></button>
   <button class="icon-btn" type="button" data-open="cart" aria-label="السلة"><i class="fa-solid fa-bag-shopping"></i><span class="badge" id="cartBadgeTop">0</span></button>
  </div>
 </div>
</header>

<section class="hero {{ $cover ? 'has-cover' : '' }}" @if($cover) style="--cover:url('{{ $cover }}')" @endif>
 <div class="shell hero-inner">
  <span class="hero-kicker">{{ $tagline ?: 'معكم بكل فرحة' }}</span>
  <h1>{{ $brandName }}</h1>
  <p>اختار اللي بتحبه، وشوف التفاصيل قبل ما تضيفه للسلة.</p>
  <div class="hero-cta"><button class="gold-btn" id="heroMenu" type="button">عرض المنيو</button><button class="ghost-btn" data-open="orders" type="button">طلباتي</button></div>
 </div>
</section>

<main class="menu-area" id="menu">
 <div class="shell">
  <div class="tools">
   <div class="search"><i class="fa-solid fa-magnifying-glass"></i><input id="searchInput" type="search" placeholder="ابحث عن صنف..." autocomplete="off"></div>
   <div class="branch-pill"><i class="fa-solid fa-location-dot"></i> {{ $location->name }}</div>
  </div>
  <div class="categories" id="categories">
   <button class="cat active" type="button" data-cat="all">الكل</button>
   @foreach(($categories ?? []) as $category)
    <button class="cat" type="button" data-cat="{{ $category['id'] }}">{{ $category['name'] }}</button>
   @endforeach
  </div>
  <div class="section-title"><h2>المنيو</h2><small id="resultCount"></small></div>
  <div class="grid" id="productGrid"></div>
 </div>
</main>
</div>

<div class="overlay" id="productOverlay">
 <div class="sheet">
  <button class="sheet-close" type="button" data-close="product"><i class="fa-solid fa-xmark"></i></button>
  <div class="detail-media" id="detailMedia"></div>
  <div class="detail-body">
   <h2 id="detailName"></h2><div class="detail-description" id="detailDescription"></div>
   <div class="detail-meta"><div class="detail-price" id="detailPrice"></div><div class="qty"><button type="button" id="detailMinus">−</button><strong id="detailQty">1</strong><button type="button" id="detailPlus">+</button></div></div>
   <button class="add-detail" id="detailAdd" type="button"><i class="fa-solid fa-bag-shopping"></i> إضافة للسلة</button>
   <button class="detail-cart-link" id="detailOpenCart" type="button"><i class="fa-solid fa-basket-shopping"></i> عرض السلة <span id="detailCartCount">0</span></button>
  </div>
 </div>
</div>

<div class="overlay" id="favoritesOverlay"><div class="sheet"><button class="sheet-close" type="button" data-close="favorites"><i class="fa-solid fa-xmark"></i></button><div class="drawer-title"><h2>المفضلة</h2></div><div class="drawer-content" id="favoritesItems"></div></div></div>

<div class="overlay" id="cartOverlay"><div class="sheet"><button class="sheet-close" type="button" data-close="cart"><i class="fa-solid fa-xmark"></i></button><div class="drawer-title"><h2>سلة الطلب</h2></div><div class="drawer-content"><div id="cartItems"></div><div class="cart-total"><span>الإجمالي</span><span id="cartTotal">0.00 ₪</span></div><button class="checkout-start" id="checkoutStart" type="button">إتمام الطلب</button></div></div></div>

<div class="overlay" id="checkoutOverlay"><div class="sheet"><button class="sheet-close" type="button" data-close="checkout"><i class="fa-solid fa-xmark"></i></button><div class="drawer-title"><h2>إتمام الطلب</h2></div>
 <form class="checkout" id="checkoutForm" enctype="multipart/form-data">
  <div class="checkout-grid">
   <label>الاسم<input name="name" required maxlength="120"></label>
   <label>رقم الجوال<input name="phone" required maxlength="20" inputmode="tel"></label>
   <label>نوع الطلب<select name="service_type" id="serviceType" required>@foreach(($serviceOptions ?? []) as $option)<option value="{{ $option['value'] ?? $option }}">{{ $option['label'] ?? $option }}</option>@endforeach</select></label>
   <label id="tableField" hidden>الطاولة<select name="restaurant_table_id"><option value="">اختر الطاولة</option>@foreach(($tables ?? []) as $table)<option value="{{ $table['id'] }}" @selected(($selectedTable['id'] ?? null)==$table['id'])>{{ $table['name'] ?? $table['label'] ?? ('طاولة '.$table['id']) }}</option>@endforeach</select></label>
   <label class="full" id="addressField" hidden>عنوان التوصيل<textarea name="address" maxlength="500"></textarea></label>
   <label class="full">ملاحظات الطلب<textarea name="notes" maxlength="700"></textarea></label>
  </div>
  <h3>طريقة الدفع</h3><div class="payment-methods" id="paymentMethods">جاري تحميل طرق الدفع...</div><div id="paymentAccountDetails"></div>
  <input type="hidden" name="payment_method_id" id="paymentMethodId"><input type="hidden" name="payment_account_id" id="paymentAccountId"><div id="paymentExtraFields"></div>
  <div class="error" id="checkoutError"></div><button class="submit-order" id="checkoutSubmit" type="submit">تأكيد وإرسال الطلب</button>
 </form>
</div></div>

<div class="overlay" id="ordersOverlay"><div class="sheet"><button class="sheet-close" type="button" data-close="orders"><i class="fa-solid fa-xmark"></i></button><div class="drawer-title"><h2>طلباتي</h2></div><div class="drawer-content orders-list" id="ordersList"></div></div></div>

<nav class="bottom-nav" aria-label="التنقل">
 <button class="nav-btn active" type="button" data-nav="home"><i class="fa-solid fa-house"></i><span>الرئيسية</span></button>
 <button class="nav-btn" type="button" data-open="favorites"><i class="fa-regular fa-heart"></i><span>المفضلة</span><b class="nav-badge" id="favBadgeNav">0</b></button>
 <button class="nav-btn nav-cart" type="button" data-open="cart"><i class="fa-solid fa-bag-shopping"></i><span>السلة</span><b class="nav-badge" id="cartBadgeNav">0</b></button>
 <button class="nav-btn" type="button" data-open="orders"><i class="fa-solid fa-receipt"></i><span>طلباتي</span></button>
 <button class="nav-btn" type="button" data-nav="menu"><i class="fa-solid fa-utensils"></i><span>المنيو</span></button>
</nav>
<div class="toast" id="toast"></div>

<script>
const RAW_PRODUCTS=@json($menuItems ?? []);
const PRODUCTS=(Array.isArray(RAW_PRODUCTS)?RAW_PRODUCTS:Object.values(RAW_PRODUCTS||{})).map(x=>({
 id:x.product_id??x.id??null,menu_item_id:x.menu_item_id??null,name:x.name??x.name_ar??'',
 description:x.description??'',image:x.image??'',category:String(x.category_id??x.category?.id??'uncategorized'),
 category_name:x.category??'',price:Number(x.price??0),available:x.available!==false&&x.available!==0&&x.available!=='0'
})).filter(x=>x.id!==null);
const LOCATION_CODE=@json($location->code);
const CART_KEY='dahab_cart_'+LOCATION_CODE,FAV_KEY='dahab_favorites_'+LOCATION_CODE,ORDERS_KEY='customer_menu_orders';
const ORDER_URL=@json(route('customer-menu.orders.store',['location'=>$location->code]));
const PAYMENT_URL=@json(route('customer-menu.payment-options',['location'=>$location->code]));
const REQUEST_TOKEN=@json($requestToken ?? '');
const CSRF=document.querySelector('meta[name="csrf-token"]').content;
let cart=safeObject(CART_KEY),favorites=safeArray(FAV_KEY),methods=[],activeCat='all',activeProduct=null,detailQty=1;
const $=id=>document.getElementById(id);
function safeObject(k){try{const v=JSON.parse(localStorage.getItem(k)||'{}');return v&&typeof v==='object'&&!Array.isArray(v)?v:{}}catch(e){return{}}}
function safeArray(k){try{const v=JSON.parse(localStorage.getItem(k)||'[]');return Array.isArray(v)?v:[]}catch(e){return[]}}
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function product(id){return PRODUCTS.find(p=>String(p.id)===String(id))}
function money(v){return Number(v||0).toFixed(2)+' ₪'}
function image(p,cls=''){return p.image?`<img class="${cls}" src="${esc(p.image)}" alt="${esc(p.name)}" onerror="this.remove()">`:`<div class="product-placeholder">${esc((p.name||'د').slice(0,1))}</div>`}
function toast(t){$('toast').textContent=t;$('toast').classList.add('show');clearTimeout(window.__toast);window.__toast=setTimeout(()=>$('toast').classList.remove('show'),1500)}
function renderProducts(){
 const q=$('searchInput').value.trim().toLowerCase();
 const rows=PRODUCTS.filter(p=>(activeCat==='all'||p.category===activeCat)&&(!q||(p.name+' '+p.description+' '+p.category_name).toLowerCase().includes(q)));
 $('resultCount').textContent=rows.length+' صنف';
 $('productGrid').innerHTML=rows.length?rows.map(p=>`<article class="product" data-id="${esc(p.id)}" data-details="${esc(p.id)}" role="button" tabindex="0" aria-label="عرض تفاصيل ${esc(p.name)}">
  <div class="product-media">${image(p)}<button class="fav ${favorites.map(String).includes(String(p.id))?'active':''}" type="button" data-fav="${esc(p.id)}"><i class="${favorites.map(String).includes(String(p.id))?'fa-solid':'fa-regular'} fa-heart"></i></button></div>
  <div class="product-body"><h3>${esc(p.name)}</h3>${!p.available?`<div class="sold-out">غير متوفر حالياً</div>`:``}<div class="product-desc">${esc(p.description||'')}</div><div class="product-foot"><span class="price">${money(p.price)}</span><button class="details" type="button" data-details="${esc(p.id)}">التفاصيل</button></div></div>
 </article>`).join(''):`<div class="empty">لا توجد أصناف مطابقة.</div>`;
}
function openOverlay(name){document.querySelectorAll('.overlay.open').forEach(x=>x.classList.remove('open'));$(name+'Overlay').classList.add('open');document.body.classList.add('no-scroll')}
function closeOverlay(name){$(name+'Overlay')?.classList.remove('open');if(!document.querySelector('.overlay.open'))document.body.classList.remove('no-scroll')}
function openProduct(id){const p=product(id);if(!p)return;activeProduct=p;detailQty=1;$('detailName').textContent=p.name;$('detailDescription').textContent=p.description||'لا يوجد وصف إضافي لهذا الصنف.';$('detailPrice').textContent=money(p.price);$('detailQty').textContent='1';$('detailMedia').innerHTML=image(p);$('detailAdd').disabled=!p.available;$('detailAdd').innerHTML=p.available?'<i class="fa-solid fa-bag-shopping"></i> إضافة للسلة':'غير متوفر حالياً';openOverlay('product')}
function saveCart(){localStorage.setItem(CART_KEY,JSON.stringify(cart));renderCart();syncBadges()}
function addToCart(id,qty=1){const p=product(id);if(!p||!p.available)return;const key=String(p.id);if(!cart[key])cart[key]={product_id:p.id,name:p.name,price:Number(p.price),quantity:0,image:p.image||''};cart[key].quantity=Math.min(50,Number(cart[key].quantity||0)+Number(qty||1));saveCart();toast('تمت الإضافة للسلة')}
function changeCart(id,d){const key=String(id);if(!cart[key])return;cart[key].quantity+=d;if(cart[key].quantity<=0)delete cart[key];saveCart()}
function cartRows(){return Object.values(cart)}
function cartTotal(){return cartRows().reduce((s,x)=>s+Number(x.price)*Number(x.quantity),0)}
function renderCart(){
 const rows=cartRows();$('cartItems').innerHTML=rows.length?rows.map(x=>`<div class="cart-row"><div class="cart-thumb">${x.image?`<img src="${esc(x.image)}" alt="">`:''}</div><div class="row-copy"><strong>${esc(x.name)}</strong><small>${money(x.price*x.quantity)}</small></div><div class="row-actions"><button type="button" data-dec="${x.product_id}">−</button><b>${x.quantity}</b><button type="button" data-inc="${x.product_id}">+</button></div></div>`).join(''):'<div class="empty">السلة فارغة.</div>';
 $('cartTotal').textContent=money(cartTotal());$('checkoutStart').disabled=!rows.length
}
function toggleFav(id){const k=String(id);favorites=favorites.map(String).includes(k)?favorites.filter(x=>String(x)!==k):[...favorites,k];localStorage.setItem(FAV_KEY,JSON.stringify(favorites));renderProducts();renderFavorites();syncBadges()}
function renderFavorites(){const rows=favorites.map(product).filter(Boolean);$('favoritesItems').innerHTML=rows.length?rows.map(p=>`<div class="fav-row"><div class="fav-thumb">${image(p)}</div><div class="row-copy"><strong>${esc(p.name)}</strong><small>${money(p.price)}</small></div><div class="row-actions"><button type="button" data-details="${p.id}"><i class="fa-solid fa-eye"></i></button><button type="button" data-fav="${p.id}"><i class="fa-solid fa-heart"></i></button></div></div>`).join(''):'<div class="empty">لا توجد أصناف في المفضلة.</div>'}
function syncBadges(){const c=cartRows().reduce((s,x)=>s+Number(x.quantity),0),f=favorites.length;['cartBadgeTop','cartBadgeNav'].forEach(id=>$(id).textContent=c);['favBadgeTop','favBadgeNav'].forEach(id=>$(id).textContent=f);if($('detailCartCount'))$('detailCartCount').textContent=c}
function renderOrders(){const all=safeArray(ORDERS_KEY).filter(x=>String(x.location||x.location_code||'')===String(LOCATION_CODE));$('ordersList').innerHTML=all.length?all.slice().reverse().map(x=>`<div class="order-card"><strong>${esc(x.order_number||'طلب')}</strong><div>${esc(x.created_at||'')}</div>${x.url?`<a href="${esc(x.url)}">تتبع الطلب <i class="fa-solid fa-arrow-left"></i></a>`:''}</div>`).join(''):'<div class="empty">لا توجد طلبات محفوظة على هذا الجهاز.</div>'}
async function loadPayments(){
 if(methods.length)return renderPayments();
 try{const r=await fetch(PAYMENT_URL,{headers:{Accept:'application/json'}});const j=await r.json();if(!r.ok)throw new Error(j.message||'تعذر تحميل طرق الدفع');methods=j.payment_methods||j.methods||[];renderPayments()}catch(e){$('paymentMethods').innerHTML='<div class="error">'+esc(e.message)+'</div>'}
}
function renderPayments(){$('paymentMethods').innerHTML=methods.length?methods.map(m=>`<button class="payment-card" type="button" data-pay="${m.id}"><strong>${esc(m.name)}</strong><br><small>${esc(m.type||'')}</small></button>`).join(''):'لا توجد طرق دفع مفعلة.'}
function choosePayment(id){const m=methods.find(x=>String(x.id)===String(id));if(!m)return;$('paymentMethodId').value=m.id;document.querySelectorAll('[data-pay]').forEach(b=>b.classList.toggle('active',String(b.dataset.pay)===String(id)));const accounts=m.accounts||[];$('paymentAccountDetails').innerHTML=accounts.length?`<label>الحساب<select id="paymentAccountSelect"><option value="">اختر الحساب</option>${accounts.map(a=>`<option value="${a.id}">${esc(a.name||a.provider_name||a.account_number||'حساب')}</option>`).join('')}</select></label>`:''}
function serviceFields(){const v=$('serviceType').value;$('tableField').hidden=v!=='dine_in';$('addressField').hidden=v!=='delivery'}
document.addEventListener('click',e=>{
 const fav=e.target.closest('[data-fav]');if(fav){e.preventDefault();e.stopPropagation();toggleFav(fav.dataset.fav);return}
 const details=e.target.closest('[data-details]');if(details){e.preventDefault();openProduct(details.dataset.details);return}
 const open=e.target.closest('[data-open]');if(open){const n=open.dataset.open;if(n==='cart')renderCart();if(n==='favorites')renderFavorites();if(n==='orders')renderOrders();openOverlay(n);return}
 const close=e.target.closest('[data-close]');if(close){closeOverlay(close.dataset.close);return}
 const inc=e.target.closest('[data-inc]');if(inc){changeCart(inc.dataset.inc,1);return}
 const dec=e.target.closest('[data-dec]');if(dec){changeCart(dec.dataset.dec,-1);return}
 const pay=e.target.closest('[data-pay]');if(pay){choosePayment(pay.dataset.pay);return}
 const nav=e.target.closest('[data-nav]');if(nav){document.querySelectorAll('.nav-btn').forEach(x=>x.classList.remove('active'));nav.classList.add('active');document.getElementById(nav.dataset.nav==='home'?'menu':'menu').scrollIntoView({behavior:'smooth'});return}
 if(e.target.classList.contains('overlay')){e.target.classList.remove('open');document.body.classList.remove('no-scroll')}
});
$('detailOpenCart').onclick=()=>{closeOverlay('product');renderCart();openOverlay('cart')}
$('detailPlus').onclick=()=>{$('detailQty').textContent=String(detailQty=Math.min(50,detailQty+1))}
$('detailMinus').onclick=()=>{$('detailQty').textContent=String(detailQty=Math.max(1,detailQty-1))}
$('detailAdd').onclick=()=>{
 if(!activeProduct)return;
 addToCart(activeProduct.id,detailQty);
 const count=cart[String(activeProduct.id)]?.quantity||detailQty;
 $('detailAdd').innerHTML='<i class="fa-solid fa-check"></i> تمت الإضافة ('+count+')';
 setTimeout(()=>{$('detailAdd').innerHTML='<i class="fa-solid fa-bag-shopping"></i> إضافة المزيد للسلة'},900);
}
document.addEventListener('keydown',e=>{
 const card=e.target.closest?.('.product[data-details]');
 if(card&&(e.key==='Enter'||e.key===' ')){e.preventDefault();openProduct(card.dataset.details)}
});
$('searchInput').addEventListener('input',renderProducts);
$('categories').addEventListener('click',e=>{const b=e.target.closest('[data-cat]');if(!b)return;activeCat=b.dataset.cat;document.querySelectorAll('.cat').forEach(x=>x.classList.toggle('active',x===b));renderProducts()});
$('heroMenu').onclick=()=>document.getElementById('menu').scrollIntoView({behavior:'smooth'});
$('checkoutStart').onclick=()=>{if(!cartRows().length)return;closeOverlay('cart');openOverlay('checkout');loadPayments();serviceFields()}
$('serviceType').addEventListener('change',serviceFields);
document.addEventListener('change',e=>{if(e.target.id==='paymentAccountSelect')$('paymentAccountId').value=e.target.value});
$('checkoutForm').addEventListener('submit',async e=>{
 e.preventDefault();if(!cartRows().length)return;$('checkoutError').textContent='';const btn=$('checkoutSubmit');btn.disabled=true;btn.textContent='جاري إرسال الطلب...';
 try{
  const fd=new FormData(e.currentTarget);fd.append('request_token',REQUEST_TOKEN);
  cartRows().forEach((x,i)=>{fd.append(`items[${i}][product_id]`,x.product_id);fd.append(`items[${i}][quantity]`,x.quantity)});
  const r=await fetch(ORDER_URL,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':CSRF},body:fd});const j=await r.json();if(!r.ok)throw new Error(j.message||Object.values(j.errors||{}).flat()[0]||'تعذر إرسال الطلب');
  const orders=safeArray(ORDERS_KEY);orders.push({order_id:j.order_id||null,order_number:j.order_number,location:LOCATION_CODE,url:j.track_url,created_at:j.created_at||new Date().toLocaleString('ar')});localStorage.setItem(ORDERS_KEY,JSON.stringify(orders));
  cart={};saveCart();closeOverlay('checkout');toast('تم استلام طلبك بنجاح');if(j.track_url)setTimeout(()=>location.href=j.track_url,700);
 }catch(err){$('checkoutError').textContent=err.message}finally{btn.disabled=false;btn.textContent='تأكيد وإرسال الطلب'}
});
(function intro(){
 const key='dahab_menu_intro_seen_'+LOCATION_CODE;const el=$('intro');
 if(sessionStorage.getItem(key)==='1'){el.remove();return}
 setTimeout(()=>{el.classList.add('hide');sessionStorage.setItem(key,'1');setTimeout(()=>el.remove(),650)},1900)
})();
renderProducts();renderCart();renderFavorites();syncBadges();serviceFields();
</script>
</body>
</html>
