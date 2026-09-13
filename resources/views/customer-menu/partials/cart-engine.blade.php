{{--
    Shared cart + favorites engine.

    There is no customer login on the public menu, so the cart and the
    favorites list both live in the browser's localStorage, keyed by this
    branch's code. Every page (menu, products, product, cart, checkout,
    favorites) includes this partial so they all read/write the *same*
    storage keys and never drift out of sync with each other.

    Cart lines are keyed by product + variant + modifier combination (not
    just product id), so "small cake" and "large cake", or "cake with extra
    chocolate" vs "cake plain", are kept as separate, correctly-priced
    lines instead of being merged together.
--}}
<script>
window.CustomerMenu = (function () {
    const RAW_PRODUCTS = @json($menuItems ?? []);
    const PRODUCTS = (Array.isArray(RAW_PRODUCTS) ? RAW_PRODUCTS : Object.values(RAW_PRODUCTS || {})).map(x => ({
        id: x.product_id ?? x.id ?? null,
        menu_item_id: x.menu_item_id ?? null,
        name: x.name ?? '',
        description: x.description ?? '',
        image: x.image ?? '',
        category: String(x.category_id ?? x.category?.id ?? 'uncategorized'),
        category_name: x.category ?? '',
        price: Number(x.price ?? 0),
        available: x.available !== false && x.available !== 0 && x.available !== '0',
        isVariantProduct: !!x.is_variant_product,
        variants: Array.isArray(x.variants) ? x.variants : [],
        modifierGroups: Array.isArray(x.modifier_groups) ? x.modifier_groups : [],
        requiresChoices: !!x.requires_choices,
    })).filter(x => x.id !== null);

    const LOCATION_CODE = @json($location->code);
    const CART_KEY = 'dahab_cart_' + LOCATION_CODE;
    const FAV_KEY = 'dahab_favorites_' + LOCATION_CODE;
    const ORDERS_KEY = 'customer_menu_orders';

    function safeObject(key) {
        try {
            const value = JSON.parse(localStorage.getItem(key) || '{}');
            return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
        } catch (e) { return {}; }
    }

    function safeArray(key) {
        try {
            const value = JSON.parse(localStorage.getItem(key) || '[]');
            return Array.isArray(value) ? value : [];
        } catch (e) { return []; }
    }

    let cart = safeObject(CART_KEY);
    let favorites = safeArray(FAV_KEY);

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function money(value) { return Number(value || 0).toFixed(2) + ' ₪'; }

    function product(id) { return PRODUCTS.find(p => String(p.id) === String(id)); }

    function image(p, cls = '') {
        const initial = esc((p.name || 'د').slice(0, 1));
        return p.image
            ? `<img class="${cls}" src="${esc(p.image)}" alt="${esc(p.name)}" onerror="this.outerHTML='<div class=&quot;product-placeholder&quot;>${initial}</div>'">`
            : `<div class="product-placeholder">${initial}</div>`;
    }

    let toastTimer;
    function toast(message) {
        const el = document.getElementById('toast');
        if (!el) return;
        el.textContent = message;
        el.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('show'), 1500);
    }

    function cartRows() {
        return Object.entries(cart).map(([key, row]) => ({ ...row, key }));
    }
    function cartCount() { return cartRows().reduce((sum, x) => sum + Number(x.quantity), 0); }
    function cartTotal() { return cartRows().reduce((sum, x) => sum + Number(x.price) * Number(x.quantity), 0); }

    function saveCart() {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
        syncBadges();
        document.dispatchEvent(new CustomEvent('customer-menu:cart-changed'));
    }

    /**
     * Build a stable line key from a product + the exact variant/modifier
     * choice, so different customizations of the same product never merge.
     */
    function lineKey(productId, variantId, modifiers) {
        const modSig = (modifiers || [])
            .slice()
            .sort((a, b) => a.modifier_id - b.modifier_id)
            .map(m => `${m.modifier_id}:${m.quantity || 1}`)
            .join(',');
        return `${productId}:${variantId || 0}:${modSig}`;
    }

    /**
     * options: { variantId: number|null, modifiers: [{modifier_id, quantity}] }
     * Modifier objects are resolved against the product's own modifier_groups
     * so the price/name always comes from trusted catalog data, never from
     * whatever a caller passes in.
     */
    function addToCart(id, qty = 1, options = {}) {
        const p = product(id);
        if (!p || !p.available) return false;

        const variantId = options.variantId ?? null;
        let variantName = null;
        let unitPrice = Number(p.price);

        if (p.isVariantProduct) {
            const variant = p.variants.find(v => String(v.id) === String(variantId));
            if (!variant) { toast('اختر الحجم/المتغير أولاً'); return false; }
            variantName = variant.name;
            unitPrice = Number(variant.price);
        }

        const allModifiers = p.modifierGroups.flatMap(g => g.modifiers.map(m => ({ ...m, group_id: g.id, group_name: g.name })));
        const resolvedModifiers = (options.modifiers || [])
            .map(row => {
                const def = allModifiers.find(m => String(m.id) === String(row.modifier_id));
                if (!def) return null;
                return {
                    modifier_id: def.id,
                    name: def.name,
                    price_delta: Number(def.price_delta || 0),
                    quantity: Math.max(1, Number(row.quantity || 1)),
                };
            })
            .filter(Boolean);

        unitPrice += resolvedModifiers.reduce((sum, m) => sum + m.price_delta * m.quantity, 0);

        const key = lineKey(p.id, variantId, resolvedModifiers);
        const displayName = [p.name, variantName].filter(Boolean).join(' - ');

        if (!cart[key]) {
            cart[key] = {
                product_id: p.id,
                variant_id: variantId,
                variant_name: variantName,
                modifiers: resolvedModifiers,
                name: displayName,
                price: unitPrice,
                quantity: 0,
                image: p.image || '',
            };
        }
        cart[key].quantity = Math.min(50, Number(cart[key].quantity || 0) + Number(qty || 1));
        saveCart();
        toast('تمت الإضافة للسلة');
        return true;
    }

    function changeCart(key, delta) {
        if (!cart[key]) return;
        cart[key].quantity += delta;
        if (cart[key].quantity <= 0) delete cart[key];
        saveCart();
    }

    function removeFromCart(key) {
        delete cart[key];
        saveCart();
    }

    function clearCart() {
        cart = {};
        saveCart();
    }

    function isFavorite(id) { return favorites.map(String).includes(String(id)); }

    function toggleFavorite(id) {
        const key = String(id);
        favorites = isFavorite(key) ? favorites.filter(x => String(x) !== key) : [...favorites, key];
        localStorage.setItem(FAV_KEY, JSON.stringify(favorites));
        syncBadges();
        document.dispatchEvent(new CustomEvent('customer-menu:favorites-changed'));
    }

    function ordersHistory() {
        return safeArray(ORDERS_KEY).filter(x => x && String(x.location || x.location_code || '') === String(LOCATION_CODE));
    }

    function pushOrder(order) {
        const orders = safeArray(ORDERS_KEY);
        orders.push(order);
        localStorage.setItem(ORDERS_KEY, JSON.stringify(orders));
    }

    function syncBadges() {
        const c = cartCount();
        const f = favorites.length;
        document.querySelectorAll('#cartBadgeTop, #cartBadgeNav').forEach(el => el.textContent = c);
        document.querySelectorAll('#favBadgeTop, #favBadgeNav').forEach(el => el.textContent = f);
        const detailCount = document.getElementById('detailCartCount');
        if (detailCount) detailCount.textContent = c;
    }

    document.addEventListener('DOMContentLoaded', syncBadges);

    return {
        PRODUCTS, LOCATION_CODE,
        esc, money, product, image, toast,
        cartRows, cartCount, cartTotal, addToCart, changeCart, removeFromCart, clearCart,
        isFavorite, toggleFavorite, favorites: () => favorites.slice(),
        ordersHistory, pushOrder,
        syncBadges,
    };
})();
</script>
<div class="toast" id="toast"></div>

