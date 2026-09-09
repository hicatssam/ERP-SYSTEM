/**
 * product-modal.js
 * -----------------------------------------------------------------------
 * ملف مستقل: نافذة تفاصيل المنتج + اقتراحات يدوية حسب الفئة.
 * حمّله وضيفه بالمنيو بدون ما تلمس ملف الـ Blade الأساسي، غير سطر واحد.
 *
 * طريقة الاستخدام:
 * 1) ارفع هاد الملف بمجلد public/js/ مثلاً: public/js/product-modal.js
 * 2) بآخر ملف customer-menu.show (قبل وسم </body> مباشرة)، ضيف سطر تحميل الملف:
 *
 *      <script src="{{ asset('js/product-modal.js') }}" defer></script>
 *
 * 3) داخل الـ <script> الأساسي بنفس الصفحة (جوا الـ IIFE الحالية)، بعد ما
 *    تتعرّف المتغيرات items / map / cart / renderCart / showToast / $،
 *    ضيف سطر واحد بس يعرّض هالمتغيرات لهاد الملف:
 *
 *      window.menuCartAPI = { items, map, cart, renderCart, showToast, $ };
 *
 *    مكان مقترح للسطر: مباشرة بعد `const showToast = (...) => {...};`
 *    أو بعد `const add = id => {...};` — المهم يكون بعد تعريف كل المتغيرات
 *    الخمسة المذكورة وقبل نهاية الـ IIFE.
 *
 * 4) بلوحة التحكم، أضف حقل `suggested_category_id` للمنتج (nullable)،
 *    ومرره ضمن مصفوفة $menuItems بالكنترولر:
 *
 *      'suggested_category_id' => $product->suggested_category_id,
 *
 *    إذا الحقل فاضي/null، النافذة ما بتعرض قسم الاقتراحات إطلاقاً.
 * -----------------------------------------------------------------------
 */
(function () {
    'use strict';

    const STYLE_ID = 'product-modal-styles';
    const MAX_WAIT_MS = 8000;
    const POLL_MS = 50;

    const css = `
        .m-modal-backdrop{position:fixed;z-index:198;inset:0;display:none;background:rgba(13,9,7,.5);backdrop-filter:blur(2px)}
        .m-modal-backdrop.show{display:block}
        .m-modal{position:fixed;z-index:199;left:50%;top:50%;width:min(560px,calc(100% - 24px));max-height:88vh;overflow:auto;display:none;transform:translate(-50%,-46%);opacity:0;border:1px solid var(--m-border,#e5e0da);border-radius:calc(var(--m-radius,14px) + 10px);background:var(--m-surface,#fff);box-shadow:0 40px 110px rgba(15,11,8,.28);transition:.2s ease}
        .m-modal.show{display:block;opacity:1;transform:translate(-50%,-50%)}
        .m-modal-close{position:absolute;z-index:2;top:12px;left:12px;width:32px;height:32px;border:0;border-radius:50%;background:rgba(255,255,255,.85);color:#17110d;font-size:1rem;cursor:pointer}
        .m-modal-media{aspect-ratio:16/10;overflow:hidden;background:color-mix(in srgb,var(--m-bg,#f7f3ee) 70%,var(--m-surface,#fff))}
        .m-modal-media img{width:100%;height:100%;object-fit:cover;display:block}
        .m-modal-body{padding:16px}
        .m-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
        .m-modal-head h3{margin:0;font-size:.92rem;font-weight:900}
        .m-modal-head p{margin:6px 0 0;color:var(--m-muted,#8a8078);font-size:.60rem;line-height:1.8;max-width:360px}
        .m-modal-price{color:var(--m-primary,#c43d48);direction:ltr;font-size:.85rem;font-weight:950;white-space:nowrap}
        .m-modal-qty{display:flex;align-items:center;justify-content:space-between;margin-top:14px;padding:10px 12px;border:1px solid var(--m-border,#e5e0da);border-radius:12px}
        .m-modal-qty>span{font-size:.60rem;font-weight:850;color:var(--m-muted,#8a8078)}
        .m-modal-qty .m-qty{display:inline-flex;align-items:center;gap:8px}
        .m-modal-qty .m-qty button{width:30px;height:30px;border:1px solid var(--m-border,#e5e0da);border-radius:8px;background:var(--m-surface,#fff);cursor:pointer}
        .m-modal-qty .m-qty span{min-width:16px;text-align:center;font-size:.62rem;font-weight:900}
        .m-modal-suggest{margin-top:16px}
        .m-modal-suggest h4{margin:0 0 8px;font-size:.68rem;font-weight:900}
        .m-suggest-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
        .m-suggest-card{border:1px solid var(--m-border,#e5e0da);border-radius:12px;overflow:hidden;background:color-mix(in srgb,var(--m-bg,#f7f3ee) 55%,var(--m-surface,#fff));text-align:center}
        .m-suggest-media{aspect-ratio:1/1;overflow:hidden;background:color-mix(in srgb,var(--m-bg,#f7f3ee) 70%,var(--m-surface,#fff))}
        .m-suggest-media img{width:100%;height:100%;object-fit:cover;display:block}
        .m-suggest-card strong{display:block;margin-top:6px;padding:0 4px;font-size:.52rem;font-weight:850;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
        .m-suggest-card span{display:block;color:var(--m-primary,#c43d48);direction:ltr;font-size:.50rem;font-weight:900}
        .m-suggest-add{width:calc(100% - 12px);margin:6px 6px 8px;min-height:26px;border:1px solid var(--m-border,#e5e0da);border-radius:999px;background:var(--m-surface,#fff);color:var(--m-text,#1c1712);font-size:.46rem;font-weight:900;cursor:pointer}
        .m-suggest-add.active{color:#fff;background:var(--m-primary,#c43d48);border-color:var(--m-primary,#c43d48)}
        .m-submit{width:100%;min-height:45px;margin-top:14px;border:0;border-radius:11px;color:#fff;background:var(--m-primary,#c43d48);font-size:.61rem;font-weight:900;cursor:pointer}
        @media(max-width:620px){.m-suggest-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    `;

    const modalHtml = `
        <div class="m-modal-backdrop" id="mProductBackdrop"></div>
        <div class="m-modal" id="mProductModal" role="dialog" aria-modal="true">
            <button type="button" class="m-modal-close" id="mProductClose">×</button>

            <div class="m-modal-media">
                <img id="mProductImage" src="" alt="">
            </div>

            <div class="m-modal-body">
                <div class="m-modal-head">
                    <div>
                        <h3 id="mProductName"></h3>
                        <p id="mProductDesc"></p>
                    </div>
                    <strong class="m-modal-price" id="mProductPrice"></strong>
                </div>

                <div class="m-modal-qty">
                    <span>الكمية</span>
                    <div class="m-qty">
                        <button type="button" id="mProductQtyPlus">+</button>
                        <span id="mProductQtyValue">1</span>
                        <button type="button" id="mProductQtyMinus">−</button>
                    </div>
                </div>

                <div class="m-modal-suggest" id="mProductSuggest" hidden>
                    <h4>يُقترح معها</h4>
                    <div class="m-suggest-grid" id="mSuggestGrid"></div>
                </div>

                <button type="button" class="m-submit" id="mProductAdd">أضف للطلب</button>
            </div>
        </div>
    `;

    const injectStylesAndMarkup = () => {
        if (!document.getElementById(STYLE_ID)) {
            const style = document.createElement('style');
            style.id = STYLE_ID;
            style.textContent = css;
            document.head.appendChild(style);
        }

        if (!document.getElementById('mProductModal')) {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = modalHtml;
            while (wrapper.firstChild) {
                document.body.appendChild(wrapper.firstChild);
            }
        }
    };

    const waitForCartApi = () => new Promise((resolve, reject) => {
        const start = Date.now();

        const tick = () => {
            if (window.menuCartAPI) return resolve(window.menuCartAPI);
            if (Date.now() - start > MAX_WAIT_MS) {
                return reject(new Error(
                    'product-modal.js: window.menuCartAPI غير موجود. ' +
                    'تأكد أنك ضفت السطر: window.menuCartAPI = { items, map, cart, renderCart, showToast, $ }; ' +
                    'داخل السكربت الأساسي بصفحة المنيو.'
                ));
            }
            window.setTimeout(tick, POLL_MS);
        };

        tick();
    });

    const esc = value => {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    };

    const money = value =>
        `${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ₪`;

    const init = api => {
        const { items, map, cart, renderCart, showToast } = api;
        const $ = api.$ || (id => document.getElementById(id));

        const productBackdrop = $('mProductBackdrop');
        const productModal = $('mProductModal');
        let modalProductId = null;
        let modalQty = 1;
        const modalExtras = new Map();

        const closeProductModal = () => {
            productModal.classList.remove('show');
            productBackdrop.classList.remove('show');
            modalProductId = null;
            modalExtras.clear();
        };

        const renderSuggestGrid = () => {
            const item = map.get(Number(modalProductId));
            const suggestSection = $('mProductSuggest');
            const grid = $('mSuggestGrid');
            const suggestedCategoryId = item?.suggested_category_id;

            const suggestions = suggestedCategoryId
                ? items.filter(i =>
                    Number(i.category_id) === Number(suggestedCategoryId) &&
                    Number(i.product_id) !== Number(modalProductId) &&
                    i.available
                )
                : [];

            if (!suggestions.length) {
                suggestSection.hidden = true;
                grid.innerHTML = '';
                return;
            }

            suggestSection.hidden = false;
            grid.innerHTML = suggestions.map(s => {
                const active = modalExtras.has(Number(s.product_id));
                return `
                    <div class="m-suggest-card">
                        <div class="m-suggest-media">
                            ${s.image ? `<img src="${s.image}" alt="${esc(s.name)}">` : ''}
                        </div>
                        <strong>${esc(s.name)}</strong>
                        <span>${money(s.price)}</span>
                        <button type="button" class="m-suggest-add ${active ? 'active' : ''}" data-suggest-id="${s.product_id}">
                            ${active ? 'أُضيفت ✓' : 'أضف'}
                        </button>
                    </div>`;
            }).join('');
        };

        const openProductModal = id => {
            const item = map.get(Number(id));
            if (!item || !item.available) return;

            modalProductId = Number(id);
            modalQty = 1;
            modalExtras.clear();

            $('mProductImage').src = item.image || '';
            $('mProductImage').alt = item.name || '';
            $('mProductName').textContent = item.name || '';
            $('mProductDesc').textContent = item.description || 'لا يوجد وصف لهذا الصنف.';
            $('mProductPrice').textContent = money(item.price);
            $('mProductQtyValue').textContent = '1';

            renderSuggestGrid();

            productBackdrop.classList.add('show');
            productModal.classList.add('show');
        };

        document.querySelectorAll('.m-product').forEach(card => {
            const openFromCard = () => openProductModal(card.dataset.productId);
            card.querySelector('.m-product-media')?.addEventListener('click', openFromCard);
            card.querySelector('h3')?.addEventListener('click', openFromCard);
        });

        $('mProductClose')?.addEventListener('click', closeProductModal);
        productBackdrop?.addEventListener('click', closeProductModal);

        $('mProductQtyPlus')?.addEventListener('click', () => {
            modalQty = Math.min(50, modalQty + 1);
            $('mProductQtyValue').textContent = String(modalQty);
        });
        $('mProductQtyMinus')?.addEventListener('click', () => {
            modalQty = Math.max(1, modalQty - 1);
            $('mProductQtyValue').textContent = String(modalQty);
        });

        $('mSuggestGrid')?.addEventListener('click', e => {
            const btn = e.target.closest('[data-suggest-id]');
            if (!btn) return;
            const sid = Number(btn.dataset.suggestId);
            modalExtras.has(sid) ? modalExtras.delete(sid) : modalExtras.set(sid, 1);
            renderSuggestGrid();
        });

        $('mProductAdd')?.addEventListener('click', () => {
            if (!modalProductId) return;
            const item = map.get(modalProductId);
            if (!item) return;

            cart.set(modalProductId, Math.min(50, (cart.get(modalProductId) || 0) + modalQty));
            modalExtras.forEach((qty, id) => {
                cart.set(id, Math.min(50, (cart.get(id) || 0) + qty));
            });

            renderCart();
            showToast('تمت الإضافة', `${item.name} صار في طلبك`, '+');
            closeProductModal();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeProductModal();
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        injectStylesAndMarkup();
        waitForCartApi().then(init).catch(err => console.error(err.message));
    });
})();
