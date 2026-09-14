<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $branding['name'] ?? 'المنيو' }} - {{ $location->name }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
@include('customer-menu.partials.pwa-head')
<style>
/* Customer-menu home only. Everything remains driven by branding variables. */
.crisp-customer-menu{background:var(--bg)}
.crisp-menu-app{padding-bottom:92px}
.crisp-menu-app .shell{width:min(560px,calc(100% - 24px))}
.home-head{padding:18px 0 8px}
.home-brandline{display:flex;align-items:center;gap:10px}
.home-logo{width:44px;height:44px;border-radius:14px;object-fit:cover;background:var(--surface);border:1px solid var(--line);padding:3px}
.home-logo-fallback{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:var(--primary);color:var(--on-primary);font-weight:900}
.home-branch{font-size:.84rem;font-weight:800;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:190px}
.home-actions{margin-inline-start:auto;display:flex;gap:8px}
.home-icon{width:42px;height:42px;border:1px solid var(--line);border-radius:14px;background:var(--surface);display:grid;place-items:center;position:relative;font-size:1.05rem;box-shadow:0 5px 18px color-mix(in srgb,var(--text) 7%,transparent)}
.home-greeting{margin-top:12px}
.home-greeting h1{margin:0;font-size:1.55rem;line-height:1.25;font-weight:900;letter-spacing:-.02em}
.home-greeting p{margin:4px 0 0;color:var(--muted);font-size:.92rem;font-weight:500}
.home-tools{display:flex;gap:9px;align-items:center;margin:14px 0}
.home-search{position:relative;flex:1}
.home-search i{position:absolute;right:15px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.92rem}
.home-search input{width:100%;height:48px;border:1px solid var(--line);border-radius:15px;background:var(--surface);padding:0 42px 0 14px;color:var(--text);outline:none}
.home-search input:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent)}
.home-filter{width:48px;height:48px;flex:0 0 48px;border:1px solid var(--line);border-radius:15px;background:var(--surface);color:var(--text)}

/* Promotional slider: one card only, 5-second autoplay. */
.offer-slider{position:relative;margin:4px 0 12px;overflow:hidden;border-radius:20px;background:var(--surface);border:1px solid var(--line);box-shadow:var(--shadow)}
.offer-track{display:flex;direction:ltr;transition:transform .55s cubic-bezier(.22,.61,.36,1);will-change:transform}
.offer-slide{position:relative;flex:0 0 100%;aspect-ratio:16/9;overflow:hidden;background:color-mix(in srgb,var(--primary) 8%,var(--surface))}
.offer-slide>img{width:100%;height:100%;object-fit:cover}
.offer-shade{position:absolute;inset:0;background:linear-gradient(180deg,transparent 30%,rgba(0,0,0,.72) 100%);pointer-events:none}
.offer-copy{position:absolute;right:16px;left:16px;bottom:14px;z-index:2;direction:rtl;color:#fff;display:flex;align-items:flex-end;gap:12px}
.offer-text{min-width:0;flex:1;text-shadow:0 2px 8px rgba(0,0,0,.28)}
.offer-tag{display:inline-flex;align-items:center;background:var(--primary);color:var(--on-primary);border-radius:999px;padding:4px 10px;font-size:.68rem;font-weight:900;margin-bottom:5px}
.offer-copy h3{margin:0;font-size:1.05rem;font-weight:900;line-height:1.25}
.offer-copy p{margin:2px 0 0;font-size:.78rem;opacity:.9;line-height:1.45;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.offer-cta{flex:0 0 auto;border:0;border-radius:999px;background:var(--primary);color:var(--on-primary);padding:10px 14px;font-size:.76rem;font-weight:900;box-shadow:0 7px 18px rgba(0,0,0,.18);display:inline-flex;align-items:center;gap:6px}
.offer-dots{display:flex;justify-content:center;gap:6px;padding:9px;background:var(--surface)}
.offer-dot{width:7px;height:7px;border-radius:999px;background:color-mix(in srgb,var(--muted) 28%,transparent);border:0;padding:0;transition:.25s}
.offer-dot.active{width:22px;background:var(--primary)}

.home-section-head{display:flex;align-items:end;justify-content:space-between;gap:12px;margin:18px 0 10px}
.home-section-head h2{margin:0;font-size:1.16rem;font-weight:900;letter-spacing:-.02em}
.home-section-head p{margin:3px 0 0;color:var(--muted);font-size:.76rem}
.home-section-head a{font-size:.78rem;font-weight:900;color:var(--primary);white-space:nowrap;border:1px solid color-mix(in srgb,var(--primary) 18%,var(--line));border-radius:999px;padding:7px 11px;background:color-mix(in srgb,var(--primary) 4%,var(--surface))}

/* Visual category rail from ERP category image/icon settings. */
.visual-categories{display:flex;gap:10px;overflow-x:auto;padding:2px 0 6px;scrollbar-width:none;scroll-snap-type:x proximity}
.visual-categories::-webkit-scrollbar{display:none}
.visual-cat{flex:0 0 82px;min-height:100px;border:1px solid var(--line);border-radius:18px;background:var(--surface);color:var(--text);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;padding:10px 8px;scroll-snap-align:start;box-shadow:0 8px 20px color-mix(in srgb,var(--text) 5%,transparent);transition:.18s}
.visual-cat:active{transform:scale(.97)}
.visual-cat.active{border-color:var(--primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 15%,transparent)}
.visual-cat-media{width:54px;height:54px;border-radius:16px;overflow:hidden;display:grid;place-items:center;background:color-mix(in srgb,var(--accent) 13%,var(--bg));color:var(--primary);font-size:1.45rem}
.visual-cat-media img{width:100%;height:100%;object-fit:cover}
.visual-cat b{font-size:.76rem;line-height:1.15;text-align:center;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.home-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.home-product{background:var(--surface);border:1px solid var(--line);border-radius:18px;overflow:hidden;box-shadow:0 10px 24px color-mix(in srgb,var(--text) 6%,transparent);position:relative}
.home-product-media{height:148px;position:relative;background:color-mix(in srgb,var(--accent) 10%,var(--bg));overflow:hidden}
.home-product-media img{width:100%;height:100%;object-fit:cover}
.home-product-media .product-placeholder{width:100%;height:100%;display:grid;place-items:center;font-size:1.55rem;font-weight:900;color:var(--primary)}
.home-fav{position:absolute;top:9px;left:9px;width:34px;height:34px;border:0;border-radius:50%;background:rgba(255,255,255,.94);color:var(--muted);box-shadow:0 5px 14px rgba(0,0,0,.16);z-index:2}
.home-fav.active{color:var(--primary)}
.home-product-body{padding:11px 12px 12px}
.home-product h3{margin:0 0 7px;font-size:.88rem;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.home-product-foot{display:flex;align-items:center;justify-content:space-between;gap:7px}
.home-price{font-size:.86rem;font-weight:900;color:var(--primary)}
.home-add{border:0;background:var(--primary);color:var(--on-primary);border-radius:10px;padding:7px 10px;font-size:.7rem;font-weight:900;white-space:nowrap}
.home-empty{grid-column:1/-1;text-align:center;padding:38px 18px;color:var(--muted);background:var(--surface);border:1px dashed var(--line);border-radius:18px}

@media(max-width:390px){
 .home-branch{max-width:145px}
 .offer-copy{right:12px;left:12px;bottom:11px}
 .offer-copy h3{font-size:.92rem}
 .offer-copy p{font-size:.7rem}
 .offer-cta{padding:9px 11px;font-size:.7rem}
 .visual-cat{flex-basis:76px}
 .home-product-media{height:132px}
}
</style>
</head>
<body class="crisp-customer-menu">
<div class="app crisp-menu-app">
<main class="menu-area">
 <div class="shell">
  <header class="home-head">
   <div class="home-brandline">
    @if($branding['logo_small'] ?? $branding['logo'] ?? null)
     <img class="home-logo" src="{{ $branding['logo_small'] ?? $branding['logo'] }}" alt="{{ $branding['name'] ?? '' }}">
    @else
     <div class="home-logo-fallback">{{ mb_substr($branding['name'] ?? 'م',0,1) }}</div>
    @endif
    <div class="home-branch">{{ $location->name }}</div>
    <div class="home-actions">
     <a class="home-icon" href="{{ route('customer-menu.favorites', $location->code) }}" aria-label="المفضلة"><i class="fa-regular fa-heart"></i><span class="badge" id="favBadgeTop">0</span></a>
     <a class="home-icon" href="{{ route('customer-menu.cart', $location->code) }}" aria-label="السلة"><i class="fa-solid fa-bag-shopping"></i><span class="badge" id="cartBadgeTop">0</span></a>
    </div>
   </div>
   <div class="home-greeting">
    <h1>أهلًا بك 👋</h1>
    <p>شو بتحب تاكل اليوم؟</p>
   </div>
  </header>

  <div class="home-tools">
   <div class="home-search"><i class="fa-solid fa-magnifying-glass"></i><input id="searchInput" type="search" placeholder="ابحث عن صنفك المفضل..." autocomplete="off"></div>
   <button class="home-filter" type="button" onclick="window.location.href='{{ route('customer-menu.products', $location->code) }}'" aria-label="عرض المنيو"><i class="fa-solid fa-sliders"></i></button>
  </div>

  @if($banners->isNotEmpty())
   <section class="offer-slider" id="offerSlider" aria-label="العروض">
    <div class="offer-track" id="offerTrack">
     @foreach($banners as $banner)
      <article class="offer-slide" data-offer-slide>
       <img src="{{ $banner['image'] }}" alt="{{ $banner['title'] ?: 'عرض' }}">
       <div class="offer-shade"></div>
       <div class="offer-copy">
        <div class="offer-text">
         @if($banner['badge_text'])<span class="offer-tag">{{ $banner['badge_text'] }}</span>@endif
         @if($banner['title'])<h3>{{ $banner['title'] }}</h3>@endif
         @if($banner['subtitle'])<p>{{ $banner['subtitle'] }}</p>@endif
        </div>
        @if($banner['link_url'])
         <a class="offer-cta" href="{{ $banner['link_url'] }}" target="_blank" rel="noopener">اطلب الآن <i class="fa-solid fa-arrow-left"></i></a>
        @else
         <button class="offer-cta" type="button" onclick="document.getElementById('categories').scrollIntoView({behavior:'smooth',block:'center'})">اطلب الآن <i class="fa-solid fa-arrow-left"></i></button>
        @endif
       </div>
      </article>
     @endforeach
    </div>
    @if($banners->count() > 1)
     <div class="offer-dots" id="offerDots" aria-label="التنقل بين العروض">
      @foreach($banners as $i => $banner)<button type="button" class="offer-dot {{ $i === 0 ? 'active' : '' }}" data-offer-dot="{{ $i }}" aria-label="العرض {{ $i + 1 }}"></button>@endforeach
     </div>
    @endif
   </section>
  @endif

  @php
   $categoryIconMap = [
    'burger'=>'fa-burger','pizza'=>'fa-pizza-slice','coffee'=>'fa-mug-hot','cake'=>'fa-cake-candles',
    'croissant'=>'fa-bread-slice','donut'=>'fa-cookie-bite','icecream'=>'fa-ice-cream','juice'=>'fa-glass-water',
    'drink'=>'fa-glass-water','fries'=>'fa-bowl-food','chicken'=>'fa-drumstick-bite','salad'=>'fa-leaf',
    'sandwich'=>'fa-burger','chocolate'=>'fa-cookie','tea'=>'fa-mug-hot','breakfast'=>'fa-bread-slice',
    'gift'=>'fa-gift','dessert'=>'fa-cookie-bite','sparkles'=>'fa-utensils'
   ];
  @endphp

  <div class="home-section-head">
   <div><h2>التصنيفات</h2><p>اختار اللي على بالك</p></div>
   <a href="{{ route('customer-menu.products', $location->code) }}">عرض الكل</a>
  </div>
  <div class="visual-categories" id="categories">
   <button class="visual-cat active" type="button" data-cat="all"><span class="visual-cat-media"><i class="fa-solid fa-utensils"></i></span><b>الكل</b></button>
   @foreach(($categories ?? []) as $category)
    @php $iconClass = $categoryIconMap[$category['icon_key'] ?? 'sparkles'] ?? 'fa-utensils'; @endphp
    <button class="visual-cat" type="button" data-cat="{{ $category['id'] }}">
     <span class="visual-cat-media" @if(!empty($category['icon_color'])) style="color:{{ $category['icon_color'] }}" @endif>
      @if(!empty($category['image']))
       <img src="{{ $category['image'] }}" alt="{{ $category['name'] }}" onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block'">
       <i class="fa-solid {{ $iconClass }}" style="display:none"></i>
      @else
       <i class="fa-solid {{ $iconClass }}"></i>
      @endif
     </span>
     <b>{{ $category['name'] }}</b>
    </button>
   @endforeach
  </div>

  <div class="home-section-head">
   <div><h2>الأكثر طلبًا ⭐</h2><p>مختارات مميزة من المنيو</p></div>
   <a href="{{ route('customer-menu.products', $location->code) }}">عرض الكل</a>
  </div>
  <div class="home-grid" id="productGrid"></div>
 </div>
</main>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'home'])
@include('customer-menu.partials.pwa-install')
@include('customer-menu.partials.cart-engine')

<script>
const CM = window.CustomerMenu;
const PRODUCT_URL_BASE = @json(route('customer-menu.product.show', [$location->code, '__ID__']));
const productUrl = id => PRODUCT_URL_BASE.replace('__ID__', id);
let activeCat = 'all';

function renderCard(p){
 return `<article class="home-product">
  <a href="${productUrl(p.id)}" aria-label="${CM.esc(p.name)}">
   <div class="home-product-media">${CM.image(p)}
    <button class="home-fav ${CM.isFavorite(p.id)?'active':''}" type="button" data-fav="${CM.esc(p.id)}"><i class="${CM.isFavorite(p.id)?'fa-solid':'fa-regular'} fa-heart"></i></button>
   </div>
  </a>
  <div class="home-product-body">
   <h3><a href="${productUrl(p.id)}">${CM.esc(p.name)}</a></h3>
   <div class="home-product-foot"><span class="home-price">${CM.money(p.price)}</span><button class="home-add" type="button" data-add="${CM.esc(p.id)}">${!p.available?'غير متوفر':(p.requiresChoices?'اختر':'أضف')}</button></div>
  </div>
 </article>`;
}

function renderGrid(){
 const q=document.getElementById('searchInput').value.trim().toLowerCase();
 const rows=CM.PRODUCTS.filter(p=>(activeCat==='all'||p.category===String(activeCat))&&(!q||(p.name+' '+p.description+' '+p.category_name).toLowerCase().includes(q))).slice(0,q||activeCat!=='all'?undefined:8);
 document.getElementById('productGrid').innerHTML=rows.length?rows.map(renderCard).join(''):`<div class="home-empty">لا توجد أصناف متاحة حاليًا.</div>`;
}

document.addEventListener('click',e=>{
 const fav=e.target.closest('[data-fav]');
 if(fav){e.preventDefault();e.stopPropagation();CM.toggleFavorite(fav.dataset.fav);renderGrid();return;}
 const add=e.target.closest('[data-add]');
 if(add){e.preventDefault();e.stopPropagation();const p=CM.product(add.dataset.add);if(!p||!p.available)return;if(p.requiresChoices){window.location.href=productUrl(p.id);return;}CM.addToCart(add.dataset.add,1);}
});

document.getElementById('categories').addEventListener('click',e=>{
 const btn=e.target.closest('[data-cat]');if(!btn)return;activeCat=btn.dataset.cat;
 document.querySelectorAll('.visual-cat').forEach(x=>x.classList.toggle('active',x===btn));renderGrid();
 document.getElementById('productGrid').scrollIntoView({behavior:'smooth',block:'start'});
});
document.getElementById('searchInput').addEventListener('input',renderGrid);
renderGrid();

(function rotatingOffers(){
 const track=document.getElementById('offerTrack');if(!track)return;
 const slides=[...track.querySelectorAll('[data-offer-slide]')];
 const dots=[...document.querySelectorAll('[data-offer-dot]')];
 if(slides.length<2)return;
 let index=0,timer;
 const go=i=>{index=(i+slides.length)%slides.length;track.style.transform=`translateX(-${index*100}%)`;dots.forEach((d,n)=>d.classList.toggle('active',n===index));};
 const start=()=>{clearInterval(timer);timer=setInterval(()=>go(index+1),5000);};
 dots.forEach((d,i)=>d.addEventListener('click',()=>{go(i);start();}));
 document.getElementById('offerSlider').addEventListener('mouseenter',()=>clearInterval(timer));
 document.getElementById('offerSlider').addEventListener('mouseleave',start);
 document.addEventListener('visibilitychange',()=>document.hidden?clearInterval(timer):start());
 start();
})();
</script>
</body>
</html>
