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
:root{--home-max:440px}
html,body{background:var(--bg)}
body.crisp-customer-menu{margin:0;color:var(--text)}
.crisp-menu-app{padding-bottom:92px}
.crisp-menu-app .shell{width:min(var(--home-max),calc(100% - 22px));margin:auto}

/* Header */
.home-head{padding:20px 0 8px}
.home-brandline{display:flex;align-items:center;gap:10px}
.home-logo{width:52px;height:52px;border-radius:16px;object-fit:cover;background:var(--surface);border:1px solid var(--line);padding:3px;box-shadow:0 8px 22px color-mix(in srgb,var(--text) 7%,transparent)}
.home-logo-fallback{width:52px;height:52px;border-radius:16px;display:grid;place-items:center;background:var(--primary);color:#fff;font-weight:900;font-size:1.15rem}
.home-branch-wrap{min-width:0;flex:1}
.home-branch-btn{display:inline-flex;align-items:center;gap:7px;border:0;background:transparent;color:var(--muted);font-size:.9rem;font-weight:800;padding:0;max-width:205px}
.home-branch-btn span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.home-branch-btn i{font-size:.72rem;color:var(--primary)}
.home-actions{margin-inline-start:auto;display:flex;gap:9px}
.home-icon{width:48px;height:48px;border:1px solid var(--line);border-radius:16px;background:var(--surface);display:grid;place-items:center;position:relative;font-size:1.15rem;box-shadow:0 8px 22px color-mix(in srgb,var(--text) 7%,transparent)}
.home-greeting{margin-top:14px;text-align:right}
.home-greeting h1{margin:0;font-size:1.9rem;line-height:1.16;font-weight:900;letter-spacing:-.03em}
.home-greeting p{margin:5px 0 0;color:var(--muted);font-size:1rem;font-weight:500}

/* Search */
.home-tools{display:flex;gap:10px;align-items:center;margin:17px 0 16px}
.home-search{position:relative;flex:1}
.home-search i{position:absolute;right:17px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:1.05rem}
.home-search input{width:100%;height:56px;border:1px solid var(--line);border-radius:18px;background:var(--surface);padding:0 48px 0 15px;color:var(--text);outline:none;font-size:.94rem;box-shadow:0 6px 18px color-mix(in srgb,var(--text) 4%,transparent)}
.home-search input:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent)}
.home-filter{width:56px;height:56px;flex:0 0 56px;border:1px solid var(--line);border-radius:18px;background:var(--surface);color:var(--text);font-size:1.05rem;box-shadow:0 6px 18px color-mix(in srgb,var(--text) 4%,transparent)}

/* Hero offers */
.offer-slider{position:relative;margin:0 0 18px;overflow:hidden;border-radius:22px;background:var(--surface);border:1px solid var(--line);box-shadow:0 14px 32px color-mix(in srgb,var(--text) 8%,transparent)}
.offer-track{display:flex;direction:ltr;transition:transform .62s cubic-bezier(.22,.61,.36,1);will-change:transform}
.offer-slide{position:relative;flex:0 0 100%;aspect-ratio:16/9;overflow:hidden;background:color-mix(in srgb,var(--primary) 10%,var(--surface))}
.offer-slide>img{width:100%;height:100%;object-fit:cover;transition:transform 5.2s ease}
.offer-slide.is-active>img{transform:scale(1.045)}
.offer-shade{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 22%,rgba(0,0,0,.74) 100%);pointer-events:none}
.offer-copy{position:absolute;inset:auto 16px 14px;z-index:2;direction:rtl;color:#fff;display:flex;align-items:flex-end;gap:12px}
.offer-text{min-width:0;flex:1;text-shadow:0 2px 8px rgba(0,0,0,.36)}
.offer-tag{display:inline-flex;align-items:center;background:var(--primary);color:#fff;border-radius:999px;padding:5px 11px;font-size:.72rem;font-weight:900;margin-bottom:6px}
.offer-copy h3{margin:0;font-size:1.15rem;font-weight:900;line-height:1.2}
.offer-copy p{margin:4px 0 0;font-size:.8rem;opacity:.94;line-height:1.45;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.offer-cta{flex:0 0 auto;border:0;border-radius:999px;background:var(--primary);color:#fff;padding:11px 16px;font-size:.78rem;font-weight:900;box-shadow:0 8px 22px rgba(0,0,0,.2);display:inline-flex;align-items:center;gap:7px;transition:.2s}
.offer-cta:active{transform:scale(.97)}
.offer-dots{display:flex;justify-content:center;gap:7px;padding:11px;background:var(--surface)}
.offer-dot{width:8px;height:8px;border-radius:999px;background:color-mix(in srgb,var(--muted) 28%,transparent);border:0;padding:0;transition:.25s}
.offer-dot.active{width:24px;background:var(--primary)}

/* Sections */
.home-section-head{display:flex;align-items:end;justify-content:space-between;gap:12px;margin:20px 0 11px}
.home-section-head h2{margin:0;font-size:1.35rem;font-weight:900;letter-spacing:-.03em}
.home-section-head p{margin:3px 0 0;color:var(--muted);font-size:.8rem}
.home-section-head a{font-size:.8rem;font-weight:900;color:var(--primary);white-space:nowrap;border:1px solid color-mix(in srgb,var(--primary) 18%,var(--line));border-radius:999px;padding:8px 13px;background:color-mix(in srgb,var(--primary) 4%,var(--surface))}

/* Categories */
.visual-categories{display:flex;gap:10px;overflow-x:auto;padding:2px 0 8px;scrollbar-width:none;scroll-snap-type:x proximity}
.visual-categories::-webkit-scrollbar{display:none}
.visual-cat{flex:0 0 84px;min-height:108px;border:1px solid var(--line);border-radius:19px;background:var(--surface);color:var(--text);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:10px 7px;scroll-snap-align:start;box-shadow:0 9px 22px color-mix(in srgb,var(--text) 5%,transparent);transition:.18s}
.visual-cat:active{transform:scale(.97)}
.visual-cat.active{border-color:var(--primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 13%,transparent)}
.visual-cat-media{width:60px;height:60px;border-radius:17px;overflow:hidden;display:grid;place-items:center;background:color-mix(in srgb,var(--accent) 13%,var(--bg));color:var(--primary);font-size:1.55rem}
.visual-cat-media img{width:100%;height:100%;object-fit:cover}
.visual-cat b{font-size:.78rem;line-height:1.15;text-align:center;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* Product cards */
.home-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.home-product{background:var(--surface);border:1px solid var(--line);border-radius:20px;overflow:hidden;box-shadow:0 12px 26px color-mix(in srgb,var(--text) 7%,transparent);position:relative}
.home-product-media{height:172px;position:relative;background:color-mix(in srgb,var(--accent) 9%,var(--bg));overflow:hidden}
.home-product-media img{width:100%;height:100%;object-fit:cover;transition:transform .25s ease}
.home-product:active .home-product-media img{transform:scale(1.02)}
.home-product-media .product-placeholder{width:100%;height:100%;display:grid;place-items:center;font-size:1.65rem;font-weight:900;color:var(--primary)}
.home-fav{position:absolute;top:10px;left:10px;width:38px;height:38px;border:0;border-radius:50%;background:rgba(255,255,255,.95);color:var(--muted);box-shadow:0 5px 14px rgba(0,0,0,.16);z-index:2;font-size:.95rem}
.home-fav.active{color:var(--primary)}
.home-product-body{padding:12px 13px 13px}
.home-product h3{margin:0 0 4px;font-size:.97rem;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.home-product-desc{margin:0 0 10px;color:var(--muted);font-size:.72rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.home-product-foot{display:flex;align-items:center;justify-content:space-between;gap:7px}
.home-price{font-size:.94rem;font-weight:900;color:var(--primary)}
.home-add{border:0;background:var(--primary);color:#fff;border-radius:11px;padding:8px 11px;font-size:.74rem;font-weight:900;white-space:nowrap}
.home-empty{grid-column:1/-1;text-align:center;padding:38px 18px;color:var(--muted);background:var(--surface);border:1px dashed var(--line);border-radius:18px}

/* Branch picker */
.branch-sheet{position:fixed;inset:0;z-index:260;background:rgba(12,10,9,.52);display:none;align-items:flex-end;justify-content:center;padding:14px}
.branch-sheet.open{display:flex}
.branch-panel{width:min(440px,100%);max-height:78dvh;overflow:auto;background:var(--surface);border-radius:26px 26px 18px 18px;padding:18px;box-shadow:0 28px 70px rgba(0,0,0,.24)}
.branch-panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.branch-panel-head h3{margin:0;font-size:1.08rem}
.branch-close{width:36px;height:36px;border:0;border-radius:50%;background:var(--bg);color:var(--text)}
.branch-list{display:grid;gap:9px}
.branch-option{display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:16px;padding:13px;background:var(--surface)}
.branch-option.active{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 5%,var(--surface))}
.branch-radio{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:var(--bg);color:var(--primary)}
.branch-option strong{display:block;font-size:.9rem}.branch-option small{display:block;margin-top:3px;color:var(--muted);font-size:.72rem}.branch-current{margin-inline-start:auto;color:var(--primary);font-size:.72rem;font-weight:900}

@media(max-width:390px){
 .crisp-menu-app .shell{width:calc(100% - 18px)}
 .home-logo,.home-logo-fallback{width:46px;height:46px}.home-icon{width:43px;height:43px}.home-branch-btn{max-width:135px}
 .home-greeting h1{font-size:1.65rem}.home-tools{margin-top:13px}.home-search input,.home-filter{height:50px}.home-filter{width:50px;flex-basis:50px}
 .offer-copy{right:12px;left:12px;bottom:11px}.offer-copy h3{font-size:1rem}.offer-copy p{font-size:.72rem}.offer-cta{padding:9px 11px;font-size:.7rem}
 .visual-cat{flex-basis:78px;min-height:101px}.visual-cat-media{width:54px;height:54px}.home-product-media{height:145px}
}
</style>
</head>
<body class="crisp-customer-menu">
@php
    $categoryIconMap=[
        'burger'=>'fa-burger','pizza'=>'fa-pizza-slice','coffee'=>'fa-mug-hot','cake'=>'fa-cake-candles',
        'croissant'=>'fa-bread-slice','donut'=>'fa-cookie-bite','icecream'=>'fa-ice-cream','juice'=>'fa-glass-water',
        'drink'=>'fa-glass-water','fries'=>'fa-bowl-food','chicken'=>'fa-drumstick-bite','salad'=>'fa-leaf',
        'sandwich'=>'fa-burger','chocolate'=>'fa-cookie','tea'=>'fa-mug-hot','breakfast'=>'fa-bread-slice',
        'gift'=>'fa-gift','dessert'=>'fa-cookie-bite','sparkles'=>'fa-utensils'
    ];

    $fallbackHero = collect($menuItems ?? [])->first(fn($row) => !empty($row['image']));
    $fallbackHeroImage = $theme['cover'] ?? ($fallbackHero['image'] ?? null);
@endphp

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
  <div class="home-branch-wrap">
   <button type="button" class="home-branch-btn" id="branchPickerBtn">
    <span>{{ $location->name }}</span><i class="fa-solid fa-chevron-down"></i>
   </button>
  </div>
  <div class="home-actions">
   <a class="home-icon" href="{{ route('customer-menu.favorites', $location->code) }}" aria-label="المفضلة"><i class="fa-regular fa-heart"></i><span class="badge" id="favBadgeTop">0</span></a>
   <a class="home-icon" href="{{ route('customer-menu.cart', $location->code) }}" aria-label="السلة"><i class="fa-solid fa-bag-shopping"></i><span class="badge" id="cartBadgeTop">0</span></a>
  </div>
 </div>
 <div class="home-greeting"><h1>أهلًا بك 👋</h1><p>شو بتحب تاكل اليوم؟</p></div>
</header>

<div class="home-tools">
 <div class="home-search"><i class="fa-solid fa-magnifying-glass"></i><input id="searchInput" type="search" placeholder="ابحث عن صنفك المفضل..." autocomplete="off"></div>
 <button class="home-filter" type="button" onclick="window.location.href='{{ route('customer-menu.products', $location->code) }}'" aria-label="عرض المنيو"><i class="fa-solid fa-sliders"></i></button>
</div>

<section class="offer-slider" id="offerSlider" aria-label="العروض">
 <div class="offer-track" id="offerTrack">
  @forelse($banners as $banner)
   <article class="offer-slide {{ $loop->first ? 'is-active' : '' }}" data-offer-slide>
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
  @empty
   <article class="offer-slide is-active" data-offer-slide>
    @if($fallbackHeroImage)<img src="{{ $fallbackHeroImage }}" alt="عرض اليوم">@endif
    <div class="offer-shade"></div>
    <div class="offer-copy">
     <div class="offer-text"><span class="offer-tag">عرض اليوم</span><h3>{{ $fallbackHero['name'] ?? 'اكتشف عروضنا اليوم' }}</h3><p>اختار صنفك المفضل واطلبه مباشرة من المنيو.</p></div>
     <button class="offer-cta" type="button" onclick="document.getElementById('categories').scrollIntoView({behavior:'smooth',block:'center'})">اطلب الآن <i class="fa-solid fa-arrow-left"></i></button>
    </div>
   </article>
  @endforelse
 </div>
 @if($banners->count() > 1)
  <div class="offer-dots" id="offerDots">
   @foreach($banners as $i=>$banner)<button type="button" class="offer-dot {{ $i===0?'active':'' }}" data-offer-dot="{{ $i }}" aria-label="العرض {{ $i+1 }}"></button>@endforeach
  </div>
 @elseif($banners->isEmpty())
  <div class="offer-dots"><button type="button" class="offer-dot active" aria-label="العرض الحالي"></button></div>
 @endif
</section>

<div class="home-section-head">
 <div><h2>التصنيفات</h2><p>اختر اللي على بالك</p></div>
 <a href="{{ route('customer-menu.products',$location->code) }}">عرض الكل</a>
</div>
<div class="visual-categories" id="categories">
 <button class="visual-cat active" type="button" data-cat="all"><span class="visual-cat-media"><i class="fa-solid fa-utensils"></i></span><b>الكل</b></button>
 @foreach(($categories??[]) as $category)
  @php $iconClass=$categoryIconMap[$category['icon_key']??'sparkles']??'fa-utensils'; @endphp
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
 <a href="{{ route('customer-menu.products',$location->code) }}">عرض الكل</a>
</div>
<div class="home-grid" id="productGrid"></div>
</div>
</main>
</div>

<div class="branch-sheet" id="branchSheet" aria-hidden="true">
 <div class="branch-panel">
  <div class="branch-panel-head"><h3>اختر الفرع</h3><button class="branch-close" type="button" id="branchClose"><i class="fa-solid fa-xmark"></i></button></div>
  <div class="branch-list">
   @foreach(($branches??[]) as $branch)
    <a class="branch-option {{ (int)$branch['id']===(int)$location->id?'active':'' }}" href="{{ $branch['url'] }}">
     <span class="branch-radio"><i class="fa-solid fa-location-dot"></i></span>
     <span><strong>{{ $branch['name'] }}</strong>@if($branch['address'])<small>{{ $branch['address'] }}</small>@endif</span>
     @if((int)$branch['id']===(int)$location->id)<span class="branch-current">الفرع الحالي</span>@endif
    </a>
   @endforeach
  </div>
 </div>
</div>

@include('customer-menu.partials.bottom-nav',['activeNav'=>'home'])
@include('customer-menu.partials.pwa-install')
@include('customer-menu.partials.cart-engine')

<script>
const CM=window.CustomerMenu;
const PRODUCT_URL_BASE=@json(route('customer-menu.product.show',[$location->code,'__ID__']));
const productUrl=id=>PRODUCT_URL_BASE.replace('__ID__',id);
let activeCat='all';

function renderCard(p){
 const desc=(p.description||p.category_name||'').trim();
 return `<article class="home-product">
  <a href="${productUrl(p.id)}" aria-label="${CM.esc(p.name)}">
   <div class="home-product-media">${CM.image(p)}<button class="home-fav ${CM.isFavorite(p.id)?'active':''}" type="button" data-fav="${CM.esc(p.id)}"><i class="${CM.isFavorite(p.id)?'fa-solid':'fa-regular'} fa-heart"></i></button></div>
  </a>
  <div class="home-product-body">
   <h3><a href="${productUrl(p.id)}">${CM.esc(p.name)}</a></h3>
   <p class="home-product-desc">${CM.esc(desc)}</p>
   <div class="home-product-foot"><span class="home-price">${CM.money(p.price)}</span><button class="home-add" type="button" data-add="${CM.esc(p.id)}">${!p.available?'غير متوفر':(p.requiresChoices?'اختر':'إضافة +')}</button></div>
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
 const btn=e.target.closest('[data-cat]');if(!btn)return;
 activeCat=btn.dataset.cat;
 document.querySelectorAll('.visual-cat').forEach(x=>x.classList.toggle('active',x===btn));
 renderGrid();
 document.getElementById('productGrid').scrollIntoView({behavior:'smooth',block:'start'});
});
document.getElementById('searchInput').addEventListener('input',renderGrid);
renderGrid();

(function rotatingOffers(){
 const track=document.getElementById('offerTrack');if(!track)return;
 const slides=[...track.querySelectorAll('[data-offer-slide]')];
 const dots=[...document.querySelectorAll('[data-offer-dot]')];
 if(slides.length<2)return;
 let index=0,timer,startX=0;
 const go=i=>{index=(i+slides.length)%slides.length;track.style.transform=`translateX(-${index*100}%)`;dots.forEach((d,n)=>d.classList.toggle('active',n===index));slides.forEach((s,n)=>s.classList.toggle('is-active',n===index));};
 const start=()=>{clearInterval(timer);timer=setInterval(()=>go(index+1),5000);};
 dots.forEach((d,i)=>d.addEventListener('click',()=>{go(i);start();}));
 const slider=document.getElementById('offerSlider');
 slider.addEventListener('touchstart',e=>{startX=e.touches[0].clientX;clearInterval(timer)},{passive:true});
 slider.addEventListener('touchend',e=>{const dx=e.changedTouches[0].clientX-startX;if(Math.abs(dx)>42)go(index+(dx<0?1:-1));start();},{passive:true});
 document.addEventListener('visibilitychange',()=>document.hidden?clearInterval(timer):start());
 start();
})();

(function branchPicker(){
 const sheet=document.getElementById('branchSheet'),open=document.getElementById('branchPickerBtn'),close=document.getElementById('branchClose');
 if(!sheet||!open)return;
 const hide=()=>{sheet.classList.remove('open');sheet.setAttribute('aria-hidden','true');document.body.style.overflow='';};
 open.addEventListener('click',()=>{sheet.classList.add('open');sheet.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';});
 close?.addEventListener('click',hide);
 sheet.addEventListener('click',e=>{if(e.target===sheet)hide();});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')hide();});
})();
</script>
</body>
</html>
