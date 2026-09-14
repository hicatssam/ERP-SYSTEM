{{--
    Shared stylesheet for every public customer-menu page — light, card-based
    layout (pill category filters, rounded product cards, flat bottom nav).

    Every color comes from $theme (admin → Customer menu branding settings),
    never hard-coded, so switching the brand's primary/accent color updates
    every one of these pages instantly.
--}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Tajawal:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
 --primary:{{ $theme['primary'] ?? '#C40035' }};
 --accent:{{ $theme['accent'] ?? '#FFCF25' }};
 --bg:{{ $theme['background'] ?? '#F7F2E8' }};
 --surface:{{ $theme['surface'] ?? '#FFFFFF' }};
 --text:{{ $theme['text'] ?? '#17130F' }};
 --muted:{{ $theme['muted'] ?? '#8A8177' }};
 --radius:{{ $theme['radius'] ?? 22 }}px;
 --line:{{ $theme['border'] ?? 'color-mix(in srgb, var(--text) 9%, transparent)' }};
 --shadow:0 14px 34px color-mix(in srgb, var(--text) 8%, transparent);
 --safe-bottom:env(safe-area-inset-bottom,0px);
 --on-primary:#ffffff;
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;font-family:{{ $theme['font_family'] ?? 'Tajawal' }},Tajawal,sans-serif;background:var(--bg);color:var(--text)}
body{overflow-x:hidden}
button,input,select,textarea{font:inherit}
button{cursor:pointer}
img{display:block;max-width:100%}
a{color:inherit;text-decoration:none}
[hidden]{display:none!important}

.app{width:100%;min-height:100vh}
.shell{width:min(560px,calc(100% - 28px));margin:auto}

/* ---------- headers ---------- */
.greet-head{padding:22px 0 6px}
.greet-head .hello{margin:0;font-size:1.5rem;font-weight:800}
.greet-head p{margin:4px 0 0;color:var(--muted);font-size:.9rem}
.greet-head .brandline{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.greet-head .brandline img{width:34px;height:34px;border-radius:10px;object-fit:cover}
.greet-head .brandline .fallback{width:34px;height:34px;border-radius:10px;background:var(--primary);color:var(--on-primary);display:grid;place-items:center;font-weight:900}
.greet-head .brandline b{font-size:.86rem;color:var(--muted);font-weight:700}
.head-actions{margin-inline-start:auto;display:flex;gap:8px}
.icon-btn{width:42px;height:42px;border:1px solid var(--line);border-radius:14px;background:var(--surface);color:var(--text);position:relative;flex-shrink:0}
.badge{position:absolute;top:-5px;left:-5px;min-width:18px;height:18px;padding:0 4px;border-radius:20px;background:var(--primary);color:var(--on-primary);font-size:10px;font-weight:900;display:grid;place-items:center}

.page-head{display:flex;align-items:center;gap:14px;padding:20px 0 6px}
.page-head h1{margin:0;font-size:1.3rem}
.page-head p{margin:2px 0 0;color:var(--muted);font-size:.82rem}
.page-back{width:40px;height:40px;flex-shrink:0;display:grid;place-items:center;border-radius:13px;background:var(--surface);border:1px solid var(--line);color:var(--text);font-size:16px}

/* ---------- promo card ---------- */
.promo-card{
 margin:14px 0 6px;border-radius:var(--radius);padding:20px;position:relative;overflow:hidden;
 background:linear-gradient(120deg, color-mix(in srgb, var(--accent) 85%, white), var(--accent));
 color:color-mix(in srgb, var(--text) 92%, black);
}
.promo-card .tag{display:inline-block;background:color-mix(in srgb, var(--text) 88%, transparent);color:#fff;font-weight:900;font-size:1.3rem;padding:4px 10px;border-radius:10px;margin-bottom:8px}
.promo-card h3{margin:0 0 4px;font-size:1.05rem}
.promo-card p{margin:0;font-size:.82rem;opacity:.85}
.promo-card i.fa-bowl-food{position:absolute;left:14px;bottom:10px;font-size:3rem;opacity:.18}

/* ---------- pill categories ---------- */
.categories{display:flex;gap:10px;overflow:auto;padding:14px 0 6px;scrollbar-width:none}
.categories::-webkit-scrollbar{display:none}
.cat{flex:0 0 auto;border:1px solid var(--line);background:var(--surface);border-radius:16px;padding:9px 14px;color:var(--muted);font-weight:700;font-size:.82rem;display:flex;flex-direction:column;align-items:center;gap:6px;min-width:64px}
.cat img{width:34px;height:34px;border-radius:50%;object-fit:cover}
.cat.active{background:var(--primary);color:var(--on-primary);border-color:var(--primary)}

/* ---------- search + filter ---------- */
.tools{display:flex;gap:10px;align-items:center;margin:6px 0 4px}
.search{position:relative;flex:1}
.search i{position:absolute;right:15px;top:50%;transform:translateY(-50%);color:var(--muted)}
.search input{width:100%;height:48px;border:1px solid var(--line);border-radius:15px;background:var(--surface);padding:0 42px 0 14px;outline:none}
.search input:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb, var(--primary) 14%, transparent)}
.filter-square{width:48px;height:48px;flex-shrink:0;border:1px solid var(--line);border-radius:15px;background:var(--surface);color:var(--text)}
.filter-square.has-filters{background:var(--primary);border-color:var(--primary);color:var(--on-primary)}

/* ---------- section header ---------- */
.section-title{display:flex;align-items:end;justify-content:space-between;margin:16px 0 12px}
.section-title h2{margin:0;font-size:1.1rem;font-weight:800}
.section-title button,.section-title a{border:0;background:transparent;color:var(--primary);font-weight:800;font-size:.85rem}

/* ---------- dine-in table picker: small colored availability cards ---------- */
.table-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(78px,1fr));gap:9px;margin-top:6px}
.table-card{
 border:1.5px solid var(--line);background:var(--surface);border-radius:14px;padding:10px 6px;text-align:center;
 display:flex;flex-direction:column;align-items:center;gap:5px;font-weight:800;color:var(--text);
}
.table-card .dot{width:9px;height:9px;border-radius:50%}
.table-card .dot.available{background:#22a35c}
.table-card .dot.occupied{background:#d64545}
.table-card small{font-size:.68rem;font-weight:700;color:var(--muted)}
.table-card.occupied{opacity:.55;cursor:not-allowed;border-style:dashed}
.table-card.occupied small{color:#b3453f}
.table-card.available small{color:#1f8a4f}
.table-card.selected{border-color:var(--primary);background:color-mix(in srgb, var(--primary) 8%, var(--surface));box-shadow:0 0 0 2px color-mix(in srgb, var(--primary) 18%, transparent)}
.table-card:disabled{background:var(--bg)}
.table-legend{display:flex;gap:16px;margin:8px 0 2px;font-size:.76rem;color:var(--muted)}
.table-legend span{display:inline-flex;align-items:center;gap:6px}
.table-legend .dot{width:9px;height:9px;border-radius:50%}

/* ---------- payment method logos + upload field ---------- */
.payment-card .p-icon img{width:100%;height:100%;object-fit:contain;border-radius:10px}
.upload-field{border:1.5px dashed var(--line);border-radius:14px;padding:14px;text-align:center;background:var(--surface);cursor:pointer;display:block}
.upload-field i{font-size:1.3rem;color:var(--primary);display:block;margin-bottom:6px}
.upload-field span{font-size:.82rem;color:var(--muted);display:block}
.upload-field input[type=file]{display:none}
.upload-preview{margin-top:10px;display:flex;align-items:center;gap:10px;font-size:.82rem;color:var(--text)}
.upload-preview img{width:44px;height:44px;object-fit:cover;border-radius:10px;border:1px solid var(--line)}
.required-star{color:#d64545}

/* ---------- product grid ---------- */
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.product{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);position:relative;display:block;transition:.2s}
.product:active{transform:scale(.98)}
.product-media{display:block;height:118px;background:color-mix(in srgb, var(--accent) 22%, var(--bg));position:relative;overflow:hidden}
.product-media img{width:100%;height:100%;object-fit:contain;padding:8px;background:color-mix(in srgb, var(--accent) 12%, var(--bg))}
.product-placeholder{width:100%;height:100%;display:grid;place-items:center;font-size:1.6rem;font-weight:900;color:color-mix(in srgb, var(--primary) 60%, var(--muted))}
.fav{position:absolute;top:9px;left:9px;width:32px;height:32px;border:0;border-radius:50%;background:rgba(255,255,255,.92);color:var(--muted);box-shadow:0 4px 12px rgba(0,0,0,.12);z-index:2;font-size:.85rem}
.fav.active{color:var(--primary)}
.product-body{padding:11px 12px 13px}
.sold-out{display:inline-block;margin:0 0 4px;padding:2px 7px;border-radius:999px;background:color-mix(in srgb, var(--primary) 12%, transparent);color:var(--primary);font-size:.68rem;font-weight:800}
.product h3{font-size:.88rem;margin:0 0 3px;line-height:1.3;min-height:2.3em;overflow:hidden}
.product-desc{display:none}
.product-rating{font-size:.72rem;color:var(--muted);margin:2px 0 6px}
.product-rating i{color:var(--accent-dark,#e0a800);color:#f0b429}
.product-foot{display:flex;align-items:center;justify-content:space-between;gap:6px}
.price{font-weight:900;color:var(--text);font-size:.86rem}
.details{border:0;background:var(--primary);color:var(--on-primary);border-radius:10px;padding:7px 10px;font-weight:800;font-size:.72rem;white-space:nowrap}
.empty{grid-column:1/-1;text-align:center;padding:50px 20px;color:var(--muted);background:var(--surface);border-radius:var(--radius);border:1px dashed var(--line)}

/* ---------- filter sheet ---------- */
.filter-sheet-overlay{position:fixed;inset:0;background:rgba(10,8,6,.5);z-index:150;display:none;align-items:flex-end;justify-content:center}
.filter-sheet-overlay.open{display:flex}
.filter-sheet{width:min(560px,100%);max-height:85dvh;overflow:auto;background:var(--surface);border-radius:24px 24px 0 0;padding:20px 20px calc(20px + var(--safe-bottom))}
.filter-sheet-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.filter-sheet-head h2{margin:0;font-size:1.1rem}
.filter-sheet-head button{border:0;background:transparent;color:var(--primary);font-weight:800;font-size:.85rem}
.filter-sheet .close-btn{width:36px;height:36px;border-radius:50%;background:var(--bg);border:0;color:var(--text)}
.filter-group{margin-bottom:18px}
.filter-group h4{margin:0 0 10px;font-size:.86rem;color:var(--muted)}
.chip-row{display:flex;flex-wrap:wrap;gap:8px}
.chip{border:1px solid var(--line);background:var(--surface);border-radius:999px;padding:9px 15px;font-weight:700;font-size:.82rem;color:var(--text)}
.chip.active{background:var(--primary);color:var(--on-primary);border-color:var(--primary)}
.filter-apply{width:100%;border:0;border-radius:15px;background:var(--primary);color:var(--on-primary);padding:14px;font-weight:900}

/* ---------- product detail ---------- */
.detail-media{height:280px;border-radius:var(--radius);background:color-mix(in srgb, var(--accent) 22%, var(--bg));position:relative;overflow:hidden;margin-top:8px}
.detail-media img{width:100%;height:100%;object-fit:contain;padding:14px}
.detail-media .fav{top:14px;left:14px;width:40px;height:40px;font-size:1rem}
.detail-body{padding:18px 0}
.detail-body h1,.detail-body h2{margin:0 0 6px;font-size:1.3rem}
.detail-description{color:var(--muted);line-height:1.75;white-space:pre-line;font-size:.9rem}
.detail-meta{display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid var(--line)}
.detail-price{font-size:1.25rem;font-weight:900;color:var(--primary)}
.qty{display:flex;align-items:center;gap:12px}
.qty button{width:36px;height:36px;border-radius:11px;border:1px solid var(--line);background:var(--surface);font-size:1.1rem}
.qty strong{min-width:22px;text-align:center}
.add-detail{width:100%;border:0;border-radius:15px;background:var(--primary);color:var(--on-primary);padding:14px;font-weight:900;font-size:.95rem;margin-top:16px}
.add-detail:disabled{opacity:.5;cursor:not-allowed}
.detail-cart-link{width:100%;border:0;background:transparent;color:var(--muted);padding:10px;font-weight:800;font-size:.85rem}
.detail-cart-link span{display:inline-grid;place-items:center;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:var(--primary);color:var(--on-primary);margin-inline-start:5px;font-size:.72rem}
.related-row{display:flex;gap:12px;overflow:auto;padding:4px 0 10px;scrollbar-width:none}
.related-row::-webkit-scrollbar{display:none}
.related-row .product{flex:0 0 130px}

/* ---------- variant + modifier picker (product page) ---------- */
.picker-group{margin:18px 0}
.picker-group-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.picker-group-head h4{margin:0;font-size:.92rem;font-weight:800}
.picker-group-head small{color:var(--muted);font-size:.74rem}
.picker-required{color:#d64545;font-size:.7rem;font-weight:800;background:color-mix(in srgb, #d64545 10%, transparent);padding:2px 8px;border-radius:8px}
.option-row{
 display:flex;align-items:center;gap:12px;border:1.5px solid var(--line);border-radius:14px;padding:12px 14px;margin-bottom:8px;background:var(--surface);cursor:pointer;
}
.option-row.selected{border-color:var(--primary);background:color-mix(in srgb, var(--primary) 6%, var(--surface))}
.option-row .mark{width:20px;height:20px;border:2px solid var(--line);flex-shrink:0;display:flex;align-items:center;justify-content:center}
.option-row .mark.round{border-radius:50%}
.option-row .mark.square{border-radius:6px}
.option-row.selected .mark{border-color:var(--primary)}
.option-row.selected .mark.round::after{content:"";width:10px;height:10px;border-radius:50%;background:var(--primary)}
.option-row.selected .mark.square::after{content:"\f00c";font-family:"Font Awesome 6 Free";font-weight:900;font-size:.6rem;color:var(--primary)}
.option-row .opt-copy{flex:1;font-weight:700;font-size:.88rem}
.option-row .opt-price{font-size:.8rem;color:var(--muted);font-weight:700}
.option-row .opt-price.has-cost{color:var(--primary)}
.modifier-qty{display:flex;align-items:center;gap:8px;margin-inline-start:10px}
.modifier-qty button{width:26px;height:26px;border-radius:8px;border:1px solid var(--line);background:var(--bg);font-size:.9rem}
.modifier-qty span{min-width:16px;text-align:center;font-size:.82rem;font-weight:800}
.picker-error{font-size:.76rem;color:#d64545;margin-top:4px}
.cart-row,.fav-row{display:grid;grid-template-columns:58px 1fr auto;gap:12px;align-items:center;padding:13px 0;border-bottom:1px solid var(--line)}
.cart-thumb,.fav-thumb{width:58px;height:58px;border-radius:15px;background:color-mix(in srgb, var(--accent) 22%, var(--bg));overflow:hidden}
.cart-thumb img,.fav-thumb img{width:100%;height:100%;object-fit:contain;padding:4px}
.row-copy strong{display:block;font-size:.9rem}
.row-copy small{color:var(--primary);font-weight:800;font-size:.82rem}
.row-actions{display:flex;align-items:center;gap:6px}
.row-actions button,.row-actions a{border:1px solid var(--line);background:var(--surface);border-radius:9px;min-width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;color:var(--text);font-size:.8rem}

/* ---------- cart summary ---------- */
.order-summary{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:16px;margin:16px 0}
.eta-banner{display:flex;align-items:center;gap:10px;background:color-mix(in srgb, var(--accent) 16%, var(--surface));color:var(--primary);border-radius:14px;padding:12px 14px;margin:14px 0 0;font-weight:700;font-size:.86rem}
.eta-banner i{font-size:1rem}
.summary-row{display:flex;justify-content:space-between;font-size:.88rem;color:var(--muted);padding:5px 0}
.cart-total{display:flex;justify-content:space-between;font-size:1.1rem;font-weight:900;padding:14px 0 4px;border-top:1px solid var(--line);margin-top:6px}
.cart-note{font-size:.76rem;color:var(--muted);margin:2px 0 14px}
.checkout-start{width:100%;border:0;border-radius:15px;background:var(--primary);color:var(--on-primary);padding:14px;font-weight:900}
.checkout-start:disabled{opacity:.5}

/* ---------- checkout form ---------- */
.checkout-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.checkout-grid label{font-size:.82rem;font-weight:700;color:var(--muted)}
.checkout-grid input,.checkout-grid select,.checkout-grid textarea{display:block;width:100%;margin-top:6px;border:1px solid var(--line);border-radius:13px;background:var(--surface);padding:11px;outline:none;color:var(--text)}
.checkout-grid textarea{min-height:80px;resize:vertical}
.full{grid-column:1/-1}
.checkout-grid h3{margin:20px 0 4px;font-size:.95rem}

/* payment method cards, radio style */
.payment-methods{display:grid;gap:10px;margin:10px 0}
.payment-card{border:1px solid var(--line);background:var(--surface);border-radius:16px;padding:13px 14px;display:flex;align-items:center;gap:12px;text-align:right;width:100%}
.payment-card .radio{width:20px;height:20px;border-radius:50%;border:2px solid var(--line);flex-shrink:0;position:relative}
.payment-card.active .radio{border-color:var(--primary)}
.payment-card.active .radio::after{content:"";position:absolute;inset:3px;border-radius:50%;background:var(--primary)}
.payment-card .p-icon{width:38px;height:38px;border-radius:10px;background:color-mix(in srgb, var(--primary) 10%, transparent);color:var(--primary);display:grid;place-items:center;flex-shrink:0}
.payment-card .p-copy{flex:1;min-width:0}
.payment-card .p-copy strong{display:block;font-size:.88rem}
.payment-card .p-copy small{color:var(--muted);font-size:.75rem}
.payment-card.active{border-color:var(--primary);box-shadow:0 0 0 2px color-mix(in srgb, var(--primary) 14%, transparent)}

.error{color:#b3261e;font-size:.85rem;margin:8px 0}
.submit-order{width:100%;border:0;border-radius:15px;background:var(--primary);color:var(--on-primary);padding:14px;font-weight:900;margin-top:14px}
.submit-order:disabled{opacity:.6}

.orders-list{display:grid;gap:10px}
.order-card{border:1px solid var(--line);border-radius:16px;padding:14px;background:var(--surface)}
.order-card a{color:var(--primary);font-weight:800}

/* ---------- bottom nav (flat) ---------- */
.bottom-nav{display:none}
.toast{position:fixed;z-index:1500;bottom:96px;left:50%;transform:translate(-50%,20px);background:var(--text);color:#fff;padding:10px 16px;border-radius:999px;opacity:0;pointer-events:none;transition:.25s;font-size:.85rem}
.toast.show{opacity:1;transform:translate(-50%,0)}

/* ---------- PWA install button + iOS instructions sheet ---------- */
.pwa-install-btn{
 position:fixed;left:16px;bottom:calc(80px + var(--safe-bottom));z-index:90;
 display:flex;align-items:center;gap:8px;border:0;border-radius:999px;
 background:var(--primary);color:var(--on-primary);padding:11px 16px;
 font-weight:800;font-size:.8rem;box-shadow:0 10px 24px color-mix(in srgb, var(--primary) 40%, transparent);
}
.pwa-install-btn i{font-size:.9rem}
@media(min-width:560px){ .pwa-install-btn{ bottom:20px; } }

.pwa-ios-sheet{position:fixed;inset:0;background:rgba(10,8,6,.5);z-index:200;display:none;align-items:flex-end;justify-content:center}
.pwa-ios-sheet.open{display:flex}
.pwa-ios-card{width:min(420px,100%);background:var(--surface);border-radius:20px 20px 0 0;padding:22px;position:relative}
.pwa-ios-close{position:absolute;top:14px;left:14px;width:32px;height:32px;border-radius:50%;background:var(--bg);border:0;color:var(--text)}
.pwa-ios-card h3{margin:0 0 12px;font-size:1rem}
.pwa-ios-card ol{margin:0;padding-inline-start:20px;display:flex;flex-direction:column;gap:8px;font-size:.86rem;color:var(--muted)}
@media(min-width:560px){ .pwa-ios-card{border-radius:20px;margin-bottom:20px} }

.menu-area{padding-bottom:100px}

@media(min-width:560px){ .grid{grid-template-columns:repeat(3,minmax(0,1fr))} }

@media(max-width:560px){
 .bottom-nav{
  position:fixed;z-index:100;bottom:0;right:0;left:0;height:calc(76px + var(--safe-bottom));
  padding:7px 6px var(--safe-bottom);background:color-mix(in srgb,var(--surface) 96%,transparent);
  border-top:1px solid var(--line);box-shadow:0 -9px 30px rgba(25,18,20,.07);
  -webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);
  display:grid;grid-template-columns:repeat(5,1fr);align-items:center;
 }
 .nav-btn{height:61px;border:0;background:transparent;color:var(--muted);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;font-size:11px;font-weight:800;position:relative}
 .nav-btn i{font-size:20px;transition:transform .2s ease}
 .nav-btn.active{color:var(--primary)}
 .nav-btn.active i{transform:translateY(-2px);font-size:22px}
 .nav-badge{position:absolute;top:0;left:50%;margin-left:9px;min-width:19px;height:19px;border:2px solid var(--surface);border-radius:12px;background:var(--primary);color:#fff;font-size:9px;font-weight:900;display:grid;place-items:center;padding:0 3px}
}
</style>
