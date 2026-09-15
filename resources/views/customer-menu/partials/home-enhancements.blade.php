@php
    $popularProductIds = \Illuminate\Support\Facades\DB::table('order_items')
        ->join('orders', 'orders.id', '=', 'order_items.order_id')
        ->where('orders.location_id', (int) $location->id)
        ->whereIn('orders.status', ['confirmed', 'completed'])
        ->whereNotNull('order_items.product_id')
        ->groupBy('order_items.product_id')
        ->orderByRaw('SUM(order_items.quantity) DESC')
        ->limit(4)
        ->pluck('order_items.product_id')
        ->map(fn ($id) => (int) $id)
        ->values();

    $hasRealBanners = collect($banners ?? [])->isNotEmpty();
    $renderedBannerIds = collect($banners ?? [])->pluck('id')->map(fn ($id) => (int) $id)->values();
    $bannerRows = \App\Models\MenuBanner::query()
        ->whereIn('id', $renderedBannerIds->all())
        ->get(['id', 'product_id'])
        ->keyBy('id');

    $bannerTargets = collect($banners ?? [])->map(function ($banner) use ($bannerRows) {
        $row = $bannerRows->get((int) ($banner['id'] ?? 0));
        return [
            'id' => (int) ($banner['id'] ?? 0),
            'product_id' => $row?->product_id ? (int) $row->product_id : null,
        ];
    })->values();

    $fallbackBannerRows = collect();
    if (! $hasRealBanners) {
        $fallbackBannerRows = \App\Models\MenuBanner::query()
            ->where('is_active', true)
            ->where(function ($query) use ($location) {
                $query->whereNull('location_id')->orWhere('location_id', 0)->orWhere('location_id', (int) $location->id);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'image' => asset('storage/' . ltrim((string) $row->image, '/')),
                'title' => (string) ($row->title ?? ''),
                'subtitle' => (string) ($row->subtitle ?? ''),
                'badge_text' => (string) ($row->badge_text ?? ''),
                'product_id' => $row->product_id ? (int) $row->product_id : null,
                'link_url' => (string) ($row->link_url ?? ''),
            ])
            ->filter(fn ($row) => filled($row['image']))
            ->values();
    }
@endphp

<style>
.home-popular-tag{position:absolute;top:10px;right:10px;z-index:3;background:var(--primary);color:#fff;border-radius:999px;padding:6px 10px;font-size:.68rem;font-weight:900;box-shadow:0 6px 16px rgba(0,0,0,.16)}
.home-offers-section{margin-top:22px}.home-offers-head{display:flex;align-items:end;justify-content:space-between;margin-bottom:11px}.home-offers-head h2{margin:0;font-size:1.3rem;font-weight:900}.home-offers-head p{margin:3px 0 0;color:var(--muted);font-size:.78rem}.home-offers-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.home-offer-product{position:relative;border-radius:18px;overflow:hidden;background:var(--surface);border:1px solid var(--line);box-shadow:0 10px 24px color-mix(in srgb,var(--text) 6%,transparent)}.home-offer-product img{width:100%;height:145px;object-fit:cover}.home-offer-product .product-placeholder{height:145px}.home-offer-product .hop-body{padding:10px 11px}.home-offer-product strong{display:block;font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.home-offer-product .hop-foot{display:flex;align-items:center;justify-content:space-between;margin-top:8px}.home-offer-product .hop-foot b{color:var(--primary)}.home-offer-product .hop-foot a{background:var(--primary);color:#fff;border-radius:10px;padding:7px 10px;font-size:.7rem;font-weight:900}.home-offer-badge{position:absolute;top:9px;right:9px;background:var(--primary);color:#fff;border-radius:999px;padding:5px 9px;font-size:.67rem;font-weight:900;z-index:2}
</style>

<script>
(function(){
 const popular=new Set(@json($popularProductIds));
 const hasRealBanners=@json($hasRealBanners);
 const bannerTargets=@json($bannerTargets);
 const fallbackBanners=@json($fallbackBannerRows);
 const productRouteTemplate=@json(route('customer-menu.product.show', [$location->code, '__PRODUCT__']));
 const productUrl=id=>productRouteTemplate.replace('__PRODUCT__',id);

 function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}

 function replaceFallbackBannerImmediately(){
   if(hasRealBanners||!fallbackBanners.length)return;
   const track=document.getElementById('offerTrack'),slider=document.getElementById('offerSlider');if(!track||!slider)return;
   track.innerHTML=fallbackBanners.map((b,i)=>`<article class="offer-slide ${i===0?'is-active':''}" data-offer-slide><img src="${esc(b.image)}" alt="${esc(b.title||'عرض')}"><div class="offer-shade"></div><div class="offer-copy"><div class="offer-text">${b.badge_text?`<span class="offer-tag">${esc(b.badge_text)}</span>`:''}${b.title?`<h3>${esc(b.title)}</h3>`:''}${b.subtitle?`<p>${esc(b.subtitle)}</p>`:''}</div><a class="offer-cta" href="${b.product_id?productUrl(b.product_id):(b.link_url||'#categories')}">اطلب الآن <i class="fa-solid fa-arrow-left"></i></a></div></article>`).join('');
   let dots=slider.querySelector('.offer-dots');
   if(fallbackBanners.length>1){if(!dots){dots=document.createElement('div');dots.className='offer-dots';dots.id='offerDots';slider.appendChild(dots)}dots.innerHTML=fallbackBanners.map((_,i)=>`<button type="button" class="offer-dot ${i===0?'active':''}" data-offer-dot="${i}"></button>`).join('')}else if(dots)dots.remove();
 }

 function wireBannerTargets(){
   const targets=hasRealBanners?bannerTargets:fallbackBanners;
   [...document.querySelectorAll('[data-offer-slide]')].forEach((slide,index)=>{
     const target=targets[index];if(!target?.product_id)return;
     const cta=slide.querySelector('.offer-cta');if(!cta)return;
     const url=productUrl(target.product_id);
     if(cta.tagName==='A'){cta.href=url;cta.removeAttribute('target');cta.removeAttribute('rel')}
     else cta.addEventListener('click',e=>{e.preventDefault();window.location.href=url},{once:true});
   });
 }

 replaceFallbackBannerImmediately();
 wireBannerTargets();

 function productIdFromCard(card){const href=card.querySelector('a[href*="/product/"]')?.getAttribute('href')||'';const m=href.match(/\/product\/(\d+)/);return m?Number(m[1]):null}
 function decoratePopular(){document.querySelectorAll('.home-product').forEach(card=>{const id=productIdFromCard(card);if(!id||!popular.has(id)||card.querySelector('.home-popular-tag'))return;const media=card.querySelector('.home-product-media');if(!media)return;const tag=document.createElement('span');tag.className='home-popular-tag';tag.textContent='🔥 الأكثر طلبًا';media.appendChild(tag)})}
 function injectOffersSection(){
   const targets=hasRealBanners?bannerTargets:fallbackBanners;const ids=[...new Set(targets.map(x=>x.product_id).filter(Boolean))];if(!ids.length||document.querySelector('.home-offers-section'))return;
   const products=ids.map(id=>window.CustomerMenu?.product(id)).filter(Boolean).slice(0,4);if(!products.length)return;const grid=document.getElementById('productGrid');if(!grid)return;
   const section=document.createElement('section');section.className='home-offers-section';section.innerHTML=`<div class="home-offers-head"><div><h2>عروض خاصة 🎟️</h2><p>لا تفوتها</p></div></div><div class="home-offers-grid">${products.map(p=>`<article class="home-offer-product"><span class="home-offer-badge">عرض خاص</span>${window.CustomerMenu.image(p)}<div class="hop-body"><strong>${window.CustomerMenu.esc(p.name)}</strong><div class="hop-foot"><b>${window.CustomerMenu.money(p.price)}</b><a href="${productUrl(p.id)}">اطلب الآن</a></div></div></article>`).join('')}</div>`;grid.parentNode.appendChild(section)
 }
 function initLater(){decoratePopular();injectOffersSection();const grid=document.getElementById('productGrid');if(grid)new MutationObserver(decoratePopular).observe(grid,{childList:true,subtree:true})}
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initLater);else setTimeout(initLater,0);
})();
</script>
