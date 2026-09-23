@extends('layouts.app')

@section('title', 'نقطة البيع')
@section('page-title', 'نقطة البيع')

@section('content')
@php
    $selectedTableId = (int) old('restaurant_table_id', request('table_id', 0));

    $resolveProductImage = static function (?string $image): ?string {
        if (blank($image)) {
            return null;
        }

        $image = trim($image);

        if (
            str_starts_with($image, 'http://')
            || str_starts_with($image, 'https://')
            || str_starts_with($image, '//')
        ) {
            return $image;
        }

        $normalized = ltrim($image, '/');

        if (str_starts_with($normalized, 'storage/')) {
            return asset($normalized);
        }

        if (file_exists(public_path($normalized))) {
            return asset($normalized);
        }

        return asset('storage/' . $normalized);
    };

    $productPayload = $products->map(function ($product) use ($resolveProductImage, $location) {
        $menuItem = $product->restaurantMenuItems->first();

        return [
            // Keep product id because RestaurantOrderService / inventory / invoice workflows use product_id.
            'id'       => (int) $product->id,
            'name'     => $menuItem?->display_name_ar
                ?: $menuItem?->display_name
                ?: $product->name_ar
                ?: $product->name,
            'category' => $product->category?->name_ar ?: $product->category?->name ?: 'بدون فئة',
            'price'    => (float) $product->getEffectivePriceForLocation($location->id),
            'image'    => $resolveProductImage($menuItem?->image ?: $product->image),
            'sku'      => $product->sku,
            'brand'    => $product->getAttribute('brand_name') ?: null,
            'requires_variant' => $product->isVariantProduct() && $product->activeVariants->isNotEmpty(),
            'variants' => $product->activeVariants->map(fn ($variant) => [
                'id' => (int) $variant->id,
                'name' => $variant->displayName(),
                'price' => (float) ($variant->selling_price ?? $product->getEffectivePriceForLocation($location->id)),
                'is_default' => (bool) $variant->is_default,
            ])->values(),
            'modifier_groups' => $product->modifierGroupLinks
                ->filter(fn ($link) => $link->group && $link->group->is_active)
                ->map(fn ($link) => [
                    'id' => (int) $link->group->id,
                    'product_variant_id' => $link->product_variant_id ? (int) $link->product_variant_id : null,
                    'name' => $link->group->name_ar ?: $link->group->name,
                    'selection_type' => $link->group->selection_type?->value ?? (string) $link->group->selection_type,
                    'is_required' => $link->is_required_override ?? (bool) $link->group->is_required,
                    'min_selections' => $link->min_selections_override ?? (int) $link->group->min_selections,
                    'max_selections' => $link->max_selections_override ?? $link->group->max_selections,
                    'modifiers' => $link->group->modifiers->map(fn ($modifier) => [
                        'id' => (int) $modifier->id,
                        'name' => $modifier->name_ar ?: $modifier->name,
                        'price_delta' => (float) $modifier->price_delta,
                        'allow_quantity' => (bool) $modifier->allow_quantity,
                        'max_quantity' => (int) $modifier->max_quantity,
                        'is_default' => (bool) $modifier->is_default,
                    ])->values(),
                ])->values(),
        ];
    })->values();

    $cashierName =
        auth()->user()?->employee?->full_name
        ?? auth()->user()?->display_name
        ?? 'المستخدم';

    $oldServiceType = old('service_type', $selectedTableId ? 'dine_in' : 'takeaway');
    $oldPaymentArrangement = old(
        'payment_arrangement',
        $selectedTableId ? 'pay_on_pickup' : 'pay_now'
    );

    $defaultSalesChannelId = old('sales_channel_id', $salesChannels->first()?->id);
@endphp

<div class="rb-pos" id="rbPosRoot">
    <main class="rb-pos-workspace">
    {{-- =====================================================
         PAGE HEADER — same composition as the reference UI
    ====================================================== --}}
    <div class="rb-pos-header">
        <div class="rb-pos-heading">
            <h1>نقطة البيع</h1>
            <div class="rb-pos-breadcrumb">
                <span>واجهة الكاشير</span>
                <span>•</span>
                <span>{{ $location->name }}</span>
                <span>•</span>
                <span>{{ $cashierName }}</span>
            </div>
        </div>

        <div class="rb-pos-header-actions">
            <a
                href="{{ route('restaurant.dashboard', ['location_id' => $location->id]) }}"
                class="rb-btn rb-btn-light rb-back-system"
                title="العودة إلى النظام"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6"></path>
                    <path d="M9 12h10"></path>
                </svg>
                العودة إلى النظام
            </a>
            <button type="button" class="rb-btn rb-btn-primary" id="rbNewOrderBtn">
                <span class="rb-btn-icon">+</span>
                طلب جديد
            </button>

            <button type="button" class="rb-btn rb-btn-light" id="rbQrOrdersBtn">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                    <path d="M14 14h3v3h-3zM18 18h3v3h-3zM14 19h2M19 14h2"></path>
                </svg>
                طلبات QR
            </button>

            <button type="button" class="rb-btn rb-btn-light" id="rbDraftListBtn">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M5 3h11l3 3v15H5z"></path>
                    <path d="M8 8h8M8 12h8M8 16h5"></path>
                </svg>
                المسودات
            </button>

            

            <button
                type="button"
                class="rb-btn rb-btn-light"
                id="rbTableOrderBtn"
                @disabled($tables->isEmpty())
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 9h16M7 9V6h10v3M6 9l-1 11M18 9l1 11"></path>
                </svg>
                طلب طاولة
            </button>
        </div>
    </div>

    @if($errors->any())
        <div class="rb-pos-alert" role="alert">
            <strong>تعذر تنفيذ الطلب.</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('restaurant.pos.orders.store') }}"
        enctype="multipart/form-data"
        id="restaurantPosForm"
        novalidate
    >
        @csrf
        <input type="hidden" name="location_id" value="{{ $location->id }}">
        <input type="hidden" name="pos_action" id="rbPosAction" value="bill_payment">

        <div class="rb-pos-shell">
            {{-- =====================================================
                 PRODUCTS PANEL
            ====================================================== --}}
            <section class="rb-products-panel">
                <div class="rb-menu-panel-head">
                    <div>
                        <span class="rb-menu-kicker">منيو الكاشير</span>
                        <h2>اختر المنتجات</h2>
                        <p>اضغط على أي صنف لإضافته مباشرة إلى الطلب الحالي.</p>
                    </div>

                    <div class="rb-menu-location-pill">
                        <span class="rb-menu-location-dot"></span>
                        {{ $location->name }}
                    </div>
                </div>

                <div class="rb-products-toolbar">
                    <label class="rb-search rb-search-products">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-3.4-3.4"></path>
                        </svg>
                        <input
                            type="search"
                            id="rbProductSearch"
                            placeholder="ابحث في المنتجات"
                            autocomplete="off"
                        >
                    </label>

                    <select class="rb-control rb-toolbar-select" id="rbCategorySelect" aria-label="اختيار الفئة">
                        <option value="__all__">كل الفئات</option>
                    </select>

                    <select class="rb-control rb-toolbar-select" id="rbBrandSelect" aria-label="اختيار العلامة">
                        <option value="__all__">كل العلامات</option>
                    </select>
                </div>

                <div class="rb-category-tabs" id="rbCategoryTabs"></div>

                <div class="rb-product-grid" id="rbProductGrid"></div>

                <div class="rb-products-footer">
                    <span><b id="rbVisibleProductCount">0</b> منتج ظاهر</span>
                    <span>{{ $location->name }}</span>
                </div>
            </section>

            {{-- =====================================================
                 CURRENT ORDER PANEL
            ====================================================== --}}
            <aside class="rb-order-panel">
                <div class="rb-order-top-controls">
                    <label class="rb-search rb-existing-search">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-3.4-3.4"></path>
                        </svg>
                        <input
                            type="search"
                            id="rbExistingOrderSearch"
                            placeholder="ابحث في الطلبات الحالية"
                            autocomplete="off"
                        >
                    </label>

                    <div class="rb-order-selects">
                        <select
                            class="rb-control"
                            name="service_type"
                            id="rbServiceType"
                            required
                        >
                            @foreach($serviceTypes as $type)
                                <option
                                    value="{{ $type->value }}"
                                    @selected($oldServiceType === $type->value)
                                >
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>

                        <select
                            class="rb-control"
                            name="restaurant_table_id"
                            id="rbTableSelect"
                        >
                            <option value="">اختر الطاولة</option>
                            @foreach($tables as $table)
                                <option
                                    value="{{ $table->id }}"
                                    data-occupied="{{ $table->activeSession ? '1' : '0' }}"
                                    @selected($selectedTableId === (int) $table->id)
                                >
                                    {{ $table->area?->name ? $table->area->name . ' — ' : '' }}
                                    {{ $table->displayName() }}
                                    {{ $table->activeSession ? ' — مشغولة' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="rb-order-card">
                    <div class="rb-order-title-row">
                        <div class="rb-order-title">
                            <span class="rb-order-bag">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M5 8h14l-1 13H6z"></path>
                                    <path d="M9 8V6a3 3 0 0 1 6 0v2"></path>
                                </svg>
                            </span>
                            <div class="rb-order-title-copy">
                                <small>السلة الحالية</small>
                                <strong>الطلب الحالي</strong>
                            </div>
                        </div>

                        <div class="rb-order-status-pill">
                            <span id="rbCartCount">0</span> عناصر
                        </div>
                    </div>

                    <div class="rb-cart-scroll" id="rbCartScroll">
                        <div class="rb-cart-empty" id="rbCartEmpty">
                            <div class="rb-cart-empty-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M6 6h15l-1.5 8h-11z"></path>
                                    <path d="M6 6 5 3H2"></path>
                                    <circle cx="9" cy="19" r="1.4"></circle>
                                    <circle cx="18" cy="19" r="1.4"></circle>
                                </svg>
                            </div>
                            <strong>لم تضف منتجات بعد</strong>
                            <span>اضغط على أي منتج لإضافته للطلب.</span>
                        </div>

                        <div id="rbCartItems"></div>
                    </div>

                    <div id="rbHiddenItems"></div>

                    <button type="button" class="rb-more-details" id="rbOpenCheckoutBtn">
                        <span>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                            العميل، الخصم، قناة البيع والدفع
                        </span>
                        <span>›</span>
                    </button>

                    <div class="rb-summary">
                        <div class="rb-summary-row">
                            <span>الإجمالي الفرعي:</span>
                            <strong id="rbSubtotal">₪0.00</strong>
                        </div>
                        <div class="rb-summary-row">
                            <span>خصم المنتجات:</span>
                            <strong>₪0.00</strong>
                        </div>
                        <div class="rb-summary-row">
                            <span>الخصم الإضافي:</span>
                            <strong id="rbDiscountAmount">₪0.00</strong>
                        </div>
                        <div class="rb-summary-row">
                            <span>خصم الكوبون:</span>
                            <strong>₪0.00</strong>
                        </div>
                        <div class="rb-summary-total">
                            <span>الإجمالي:</span>
                            <strong id="rbGrandTotal">₪0.00</strong>
                        </div>
                    </div>

                    <div class="rb-order-actions">
                        <button type="button" class="rb-action rb-action-kot" id="rbKotPrintBtn">
                            إرسال للمطبخ وطباعة
                        </button>

                        <button type="button" class="rb-action rb-action-draft" id="rbSaveDraftBtn">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M5 3h11l3 3v15H5z"></path>
                                <path d="M8 8h8"></path>
                            </svg>
                            مسودة
                        </button>

                        <button type="button" class="rb-action rb-action-payment" id="rbBillPaymentBtn">
                            الفاتورة والدفع
                        </button>

                        <button type="button" class="rb-action rb-action-print" id="rbBillPrintBtn">
                            الفاتورة والطباعة
                        </button>
                    </div>
                </div>
            </aside>
        </div>

        {{-- =====================================================
             CHECKOUT / ORDER DETAILS MODAL
        ====================================================== --}}
        <div class="rb-modal" id="rbCheckoutModal" hidden aria-hidden="true">
            <div class="rb-modal-backdrop" data-close-checkout></div>

            <div class="rb-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="rbCheckoutTitle">
                <div class="rb-modal-head">
                    <div>
                        <h2 id="rbCheckoutTitle">تفاصيل الطلب والدفع</h2>
                        <p>كل الحقول هنا مرتبطة بطلب الكاشير الحالي.</p>
                    </div>
                    <button type="button" class="rb-modal-close" data-close-checkout>×</button>
                </div>

                <div class="rb-modal-body">
                    <div class="rb-modal-grid">
                        <div class="rb-field">
                            <label>العميل</label>
                            <select name="customer_id" class="rb-control">
                                <option value="">عميل نقدي</option>
                                @foreach($customers as $customer)
                                    <option
                                        value="{{ $customer->id }}"
                                        @selected((string) old('customer_id') === (string) $customer->id)
                                    >
                                        {{ $customer->name }}{{ $customer->phone ? ' — ' . $customer->phone : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rb-field" id="rbGuestCountGroup">
                            <label>عدد الضيوف</label>
                            <input
                                type="number"
                                name="guest_count"
                                min="1"
                                max="100"
                                value="{{ old('guest_count', 2) }}"
                                class="rb-control"
                            >
                        </div>

                        <div class="rb-field">
                            <label>قناة البيع *</label>
                            <select name="sales_channel_id" class="rb-control" required>
                                <option value="">اختر القناة</option>
                                @foreach($salesChannels as $channel)
                                    <option
                                        value="{{ $channel->id }}"
                                        @selected((string) $defaultSalesChannelId === (string) $channel->id)
                                    >
                                        {{ $channel->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rb-field">
                            <label>ترتيب الدفع *</label>
                            <select
                                name="payment_arrangement"
                                id="rbPaymentArrangement"
                                class="rb-control"
                                required
                            >
                                @foreach($paymentArrangements as $arrangement)
                                    <option
                                        value="{{ $arrangement->value }}"
                                        @selected($oldPaymentArrangement === $arrangement->value)
                                    >
                                        {{ $arrangement->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rb-field" id="rbPaymentMethodGroup">
                            <label>نوع طريقة الدفع</label>
                            <select
                                name="payment_method_id"
                                id="rbPaymentMethod"
                                class="rb-control"
                            >
                                <option value="">اختر طريقة الدفع</option>
                                @foreach($paymentMethods as $method)
                                    <option
                                        value="{{ $method->id }}"
                                        data-type="{{ $method->type }}"
                                        data-reference="{{ $method->requires_reference ? '1' : '0' }}"
                                        data-verification="{{ $method->requires_verification ? '1' : '0' }}"
                                        @selected((string) old('payment_method_id') === (string) $method->id)
                                    >
                                        {{ $method->name_ar ?: $method->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rb-field" id="rbPaidAmountGroup" hidden>
                            <label>المبلغ المدفوع</label>
                            <input
                                type="number"
                                name="paid_amount"
                                step="0.01"
                                min="0"
                                value="{{ old('paid_amount') }}"
                                class="rb-control"
                            >
                        </div>

                        <div class="rb-field">
                            <label>نوع الخصم</label>
                            <select name="discount_type" id="rbDiscountType" class="rb-control">
                                <option value="none">بدون خصم</option>
                                <option value="percentage" @selected(old('discount_type') === 'percentage')>نسبة %</option>
                                <option value="fixed" @selected(old('discount_type') === 'fixed')>قيمة ثابتة</option>
                            </select>
                        </div>

                        <div class="rb-field">
                            <label>قيمة الخصم</label>
                            <input
                                type="number"
                                name="discount_value"
                                id="rbDiscountValue"
                                step="0.01"
                                min="0"
                                value="{{ old('discount_value', 0) }}"
                                class="rb-control"
                            >
                        </div>

                        <div class="rb-field rb-field-wide" id="rbReferenceGroup" hidden>
                            <label>رقم العملية / المرجع</label>
                            <input
                                type="text"
                                name="reference_number"
                                value="{{ old('reference_number') }}"
                                class="rb-control"
                            >
                        </div>

                        <div class="rb-field" id="rbSenderNameGroup" hidden>
                            <label>اسم المحوّل / صاحب العملية *</label>
                            <input
                                type="text"
                                name="sender_name"
                                value="{{ old('sender_name') }}"
                                maxlength="150"
                                class="rb-control"
                                placeholder="اسم الشخص الذي قام بالدفع"
                            >
                        </div>

                        <div class="rb-field" id="rbSenderPhoneGroup" hidden>
                            <label>رقم جوال المحوّل</label>
                            <input
                                type="text"
                                name="sender_phone"
                                value="{{ old('sender_phone') }}"
                                maxlength="50"
                                class="rb-control"
                                dir="ltr"
                                placeholder="مثال: 059xxxxxxx"
                            >
                        </div>

                        <div class="rb-field rb-field-wide" id="rbSenderAccountGroup" hidden>
                            <label>رقم الحساب / المحفظة</label>
                            <input
                                type="text"
                                name="sender_account_number"
                                value="{{ old('sender_account_number') }}"
                                maxlength="120"
                                class="rb-control"
                                dir="ltr"
                                placeholder="رقم الحساب أو رقم المحفظة — يكفي هذا أو رقم الجوال"
                            >
                            <small class="rb-field-hint">
                                لطريقة الدفع غير النقدية يجب إدخال رقم الجوال أو رقم الحساب/المحفظة على الأقل.
                            </small>
                        </div>

                        <div class="rb-field rb-field-wide" id="rbProofGroup" hidden>
                            <label>إثبات الدفع</label>
                            <input
                                type="file"
                                name="payment_proof"
                                accept="image/*"
                                class="rb-control rb-file-control"
                            >
                        </div>

                        <div class="rb-field rb-field-wide">
                            <label>ملاحظات عامة على الطلب</label>
                            <textarea
                                name="notes"
                                rows="3"
                                class="rb-control rb-textarea"
                                placeholder="مثال: تجهيز سريع، العميل ينتظر..."
                            >{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="rb-modal-foot">
                    <div class="rb-modal-total">
                        <span>الإجمالي</span>
                        <strong id="rbCheckoutTotal">₪0.00</strong>
                    </div>
                    <button type="button" class="rb-btn rb-btn-light" data-close-checkout>إلغاء</button>
                    <button type="button" class="rb-btn rb-btn-primary" id="rbCheckoutContinueBtn">متابعة</button>
                </div>
            </div>
        </div>
    </form>
    </main>
</div>

{{-- =========================================================
     ORDERS / DRAFTS DRAWER
========================================================= --}}
<div class="rb-drawer-backdrop" id="rbDrawerBackdrop" hidden></div>
<aside class="rb-drawer" id="rbOrdersDrawer" aria-hidden="true">
    <div class="rb-drawer-head">
        <div>
            <h3 id="rbDrawerTitle">الطلبات</h3>
            <p id="rbDrawerSubtitle">{{ $location->name }}</p>
        </div>
        <button type="button" id="rbCloseDrawer">×</button>
    </div>

    <div class="rb-drawer-search">
        <label class="rb-search">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m20 20-3.4-3.4"></path>
            </svg>
            <input type="search" id="rbDrawerSearchInput" placeholder="ابحث برقم الطلب أو العميل...">
        </label>
    </div>

    <div class="rb-drawer-section" id="rbLocalDraftSection" hidden>
        <div class="rb-drawer-section-title">المسودة المحلية</div>
        <div id="rbLocalDraftContainer"></div>
    </div>

    <div class="rb-drawer-section">
        <div class="rb-drawer-section-title" id="rbServerOrdersTitle">آخر الطلبات المسجلة</div>
        <div id="rbServerOrdersList">
            @forelse($recentOrders as $order)
                @php
                    $statusValue = $order->status instanceof \BackedEnum
                        ? $order->status->value
                        : (string) $order->status;

                    $statusLabel = method_exists($order->status, 'label')
                        ? $order->status->label()
                        : $statusValue;

                    $channel = $salesChannels->firstWhere('id', $order->sales_channel_id);
                    $channelText = strtolower(trim(
                        ($channel?->code ?? '') . ' ' . ($channel?->name ?? '')
                    ));
                @endphp

                <a
                    href="{{ route('orders.show', $order) }}"
                    class="rb-server-order-card"
                    data-server-order
                    data-status="{{ $statusValue }}"
                    data-channel="{{ $channelText }}"
                    data-search="{{ strtolower($order->order_number . ' ' . ($order->customer?->name ?? '') . ' ' . ($order->restaurantTable?->displayName() ?? '')) }}"
                >
                    <div class="rb-server-order-top">
                        <strong>{{ $order->order_number }}</strong>
                        <span>{{ $statusLabel }}</span>
                    </div>
                    <div class="rb-server-order-meta">
                        <span>{{ $order->restaurant_service_type?->label() ?? '—' }}</span>
                        <span>{{ $order->restaurantTable?->displayName() ?? 'بدون طاولة' }}</span>
                        <span>{{ $order->customer?->name ?? 'عميل نقدي' }}</span>
                    </div>
                </a>
            @empty
                <div class="rb-drawer-empty">لا توجد طلبات حديثة.</div>
            @endforelse
        </div>
    </div>
</aside>

<style>
/* =========================================================
   RestroBit-inspired Restaurant POS
   Scope is fully isolated under .rb-pos to avoid breaking ERP UI.
========================================================= */
.rb-pos,
.rb-pos * {
    box-sizing: border-box;
}

html.rb-pos-mode,
body.rb-pos-mode {
    overflow: hidden !important;
}

.rb-pos {
    /* POS inherits the live system theme */
    --rb-orange: var(--theme-accent, var(--gold, #C98516));
    --rb-orange-hover: color-mix(in srgb, var(--rb-orange) 86%, #000);
    --rb-orange-soft: color-mix(in srgb, var(--rb-orange) 12%, var(--theme-surface, #fff));
    --rb-green: var(--theme-success, var(--success, #197438));
    --rb-navy: var(--theme-primary, var(--navy, #0A2948));
    --rb-text: var(--theme-text, var(--text-main, #172435));
    --rb-muted: var(--theme-text-muted, var(--text-muted, #687482));
    --rb-border: var(--theme-border, var(--border, #DDE2E7));
    --rb-border-2: color-mix(in srgb, var(--rb-border) 80%, var(--rb-text) 20%);
    --rb-bg: var(--theme-bg, var(--bg, #F5F6F8));
    --rb-white: var(--theme-surface, var(--card-bg, #FFFFFF));
    --rb-soft: color-mix(in srgb, var(--rb-white) 88%, var(--rb-bg) 12%);
    --rb-soft-2: color-mix(in srgb, var(--rb-white) 94%, var(--rb-bg) 6%);
    --rb-sidebar-bg: var(--theme-sidebar-bg, var(--rb-white));
    --rb-sidebar-text: var(--theme-sidebar-text, var(--rb-text));
    --rb-sidebar-active: var(--theme-sidebar-active, var(--rb-orange));
    --rb-danger: var(--theme-danger, var(--error, #E22929));
    --rb-radius: var(--theme-radius, 10px);
    --rb-shadow: 0 2px 10px color-mix(in srgb, var(--rb-text) 6%, transparent);

    position: fixed;
    inset: 0;
    z-index: 2147482000;
    width: 100vw;
    height: 100dvh;
    min-width: 0;
    display: grid;
    grid-template-columns: 1fr;
    overflow: hidden;
    direction: ltr;
    color: var(--rb-text);
    background: var(--rb-bg);
    font-family: inherit;
}

.rb-pos-workspace {
    min-width: 0;
    height: 100dvh;
    overflow: auto;
    padding: 25px 22px 20px;
    background: var(--rb-bg);
    scrollbar-width: thin;
}

.rb-pos-sidebar {
    position: relative;
    z-index: 2;
    min-width: 0;
    height: 100dvh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--rb-sidebar-bg);
    color: var(--rb-sidebar-text);
    border-right: 1px solid color-mix(in srgb, var(--rb-sidebar-text) 12%, transparent);
    box-shadow: 4px 0 18px rgba(16, 24, 39, .025);
}

.rb-side-brand {
    height: 72px;
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 0 14px;
    border-bottom: 1px solid color-mix(in srgb, var(--rb-sidebar-text) 10%, transparent);
    color: var(--rb-sidebar-text);
}

.rb-side-brand-mark {
    width: 26px;
    height: 26px;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--rb-sidebar-active);
    border-radius: 7px;
}

.rb-side-brand-mark svg {
    width: 16px;
    height: 16px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.rb-side-brand strong {
    font-size: 12px;
    font-weight: 900;
    letter-spacing: -.2px;
}

.rb-side-user {
    display: grid;
    grid-template-columns: 30px minmax(0, 1fr);
    gap: 7px;
    align-items: center;
    padding: 13px 11px 12px;
}

.rb-side-avatar {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    color: #fff;
    background: color-mix(in srgb, var(--rb-sidebar-text) 18%, var(--rb-sidebar-bg));
    font-size: 10px;
    font-weight: 900;
}

.rb-side-user strong,
.rb-side-user span {
    display: block;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.rb-side-user strong {
    color: var(--rb-sidebar-text);
    font-size: 9px;
    font-weight: 900;
}

.rb-side-user span {
    margin-top: 2px;
    color: color-mix(in srgb, var(--rb-sidebar-text) 58%, transparent);
    font-size: 7.5px;
}

.rb-side-nav {
    min-height: 0;
    flex: 1;
    overflow: auto;
    padding: 4px 9px 10px;
    scrollbar-width: none;
}

.rb-side-nav::-webkit-scrollbar {
    display: none;
}

.rb-side-link {
    min-height: 38px;
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 2px 0;
    padding: 0 9px;
    border-radius: 5px;
    color: color-mix(in srgb, var(--rb-sidebar-text) 78%, transparent);
    font-size: 8.5px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
    transition: .14s ease;
}

.rb-side-link:hover {
    color: var(--rb-sidebar-text);
    background: color-mix(in srgb, var(--rb-sidebar-text) 8%, transparent);
}

.rb-side-link.active {
    color: var(--rb-sidebar-active);
    background: color-mix(in srgb, var(--rb-sidebar-active) 12%, transparent);
    box-shadow: inset 2px 0 0 var(--rb-sidebar-active);
}

.rb-side-link svg {
    width: 15px;
    height: 15px;
    flex: 0 0 15px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.65;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.rb-side-section {
    padding: 12px 9px 4px;
    color: color-mix(in srgb, var(--rb-sidebar-text) 48%, transparent);
    font-size: 7px;
    font-weight: 700;
    text-transform: uppercase;
}

.rb-side-footer {
    padding: 8px 9px 11px;
    border-top: 1px solid color-mix(in srgb, var(--rb-sidebar-text) 10%, transparent);
}

.rb-pos button,
.rb-pos input,
.rb-pos select,
.rb-pos textarea {
    font: inherit;
}

.rb-pos-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 14px;
}

.rb-pos-heading {
    direction: rtl;
    text-align: left;
}

.rb-pos-heading h1 {
    margin: 0;
    color: var(--rb-text);
    font-size: 17px;
    font-weight: 900;
    line-height: 1.35;
}

.rb-pos-breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
    color: var(--rb-muted);
    font-size: 10px;
    font-weight: 600;
}

.rb-pos-header-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
}

.rb-back-system {
    margin-inline-end: 5px;
    color: var(--rb-text);
    background: var(--rb-white);
}

.rb-back-system svg {
    transform: scaleX(-1);
}

.rb-btn {
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 0 13px;
    border: 1px solid var(--rb-border);
    border-radius: 6px;
    background: var(--rb-white);
    color: var(--rb-text);
    font-size: 10px;
    font-weight: 800;
    line-height: 1;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
    transition: .15s ease;
    direction: rtl;
}

.rb-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--rb-orange) 30%, var(--rb-border));
    box-shadow: 0 4px 10px rgba(16, 24, 39, .055);
}

.rb-btn:disabled {
    opacity: .45;
    cursor: not-allowed;
}

.rb-btn svg {
    width: 14px;
    height: 14px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.rb-btn-primary {
    color: #fff;
    background: var(--rb-orange);
    border-color: var(--rb-orange);
}

.rb-btn-primary:hover:not(:disabled) {
    background: var(--rb-orange-hover);
    border-color: var(--rb-orange-hover);
}

.rb-btn-icon {
    font-size: 16px;
    font-weight: 400;
    line-height: 1;
}

.rb-pos-alert {
    margin-bottom: 12px;
    padding: 10px 14px;
    direction: rtl;
    text-align: right;
    color: var(--rb-danger);
    background: color-mix(in srgb, var(--rb-danger) 8%, var(--rb-white));
    border: 1px solid color-mix(in srgb, var(--rb-danger) 28%, var(--rb-border));
    border-radius: 8px;
    font-size: 11px;
}

.rb-pos-alert ul {
    margin: 5px 0 0;
    padding-right: 18px;
}

.rb-pos-shell {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 392px;
    gap: 15px;
    align-items: start;
}

.rb-products-panel,
.rb-order-panel {
    min-width: 0;
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: var(--rb-radius);
    box-shadow: var(--rb-shadow);
}

.rb-products-panel {
    min-height: calc(100dvh - 118px);
    padding: 14px;
}

.rb-products-toolbar {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 150px 150px;
    gap: 9px;
    align-items: center;
    margin-bottom: 11px;
}

.rb-search {
    height: 37px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 10px;
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 6px;
    transition: .15s ease;
}

.rb-search:focus-within {
    border-color: color-mix(in srgb, var(--rb-orange) 55%, var(--rb-border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--rb-orange) 10%, transparent);
}

.rb-search svg {
    width: 15px;
    height: 15px;
    flex: 0 0 15px;
    fill: none;
    stroke: var(--rb-muted);
    stroke-width: 1.7;
    stroke-linecap: round;
}

.rb-search input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--rb-text);
    font-size: 10px;
    direction: rtl;
}

.rb-search input::placeholder {
    color: var(--rb-muted);
}

.rb-control {
    width: 100%;
    height: 37px;
    min-width: 0;
    padding: 0 10px;
    color: var(--rb-text);
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 6px;
    outline: none;
    font-size: 10px;
    font-weight: 650;
    direction: rtl;
    transition: .15s ease;
}

.rb-control:focus {
    border-color: color-mix(in srgb, var(--rb-orange) 55%, var(--rb-border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--rb-orange) 10%, transparent);
}

.rb-category-tabs {
    display: flex;
    align-items: center;
    gap: 4px;
    overflow-x: auto;
    padding: 1px 0 10px;
    scrollbar-width: thin;
}

.rb-category-tab {
    flex: 0 0 auto;
    min-height: 25px;
    padding: 0 9px;
    color: var(--rb-muted);
    background: transparent;
    border: 0;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 750;
    cursor: pointer;
    direction: rtl;
}

.rb-category-tab:hover {
    color: var(--rb-orange);
    background: var(--rb-orange-soft);
}

.rb-category-tab.active {
    color: #fff;
    background: var(--rb-orange);
}

.rb-product-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 9px;
}

.rb-product-card {
    position: relative;
    min-width: 0;
    min-height: 150px;
    overflow: hidden;
    padding: 0;
    color: var(--rb-text);
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 6px;
    box-shadow: 0 1px 4px color-mix(in srgb, var(--rb-text) 4%, transparent);
    text-align: left;
    cursor: pointer;
    transition: .14s ease;
}

.rb-product-card:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--rb-orange) 38%, var(--rb-border));
    box-shadow: 0 7px 16px color-mix(in srgb, var(--rb-text) 8%, transparent);
}

.rb-product-image {
    height: 104px;
    display: grid;
    place-items: center;
    overflow: hidden;
    padding: 9px 10px 4px;
    background: transparent;
}

.rb-product-image img {
    width: 100%;
    height: 100%;
    max-width: 138px;
    margin: auto;
    object-fit: contain;
    object-position: center;
    display: block;
    background: transparent;
    filter: drop-shadow(0 7px 8px rgba(15, 23, 42, .10));
}

.rb-product-placeholder {
    width: 58px;
    height: 58px;
    display: grid;
    place-items: center;
    color: color-mix(in srgb, var(--rb-muted) 45%, transparent);
    border: 1px dashed var(--rb-border);
    border-radius: 50%;
}

.rb-product-placeholder svg {
    width: 27px;
    height: 27px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.4;
}

.rb-product-info {
    padding: 8px 9px 9px;
    direction: rtl;
    text-align: left;
}

.rb-product-name {
    display: block;
    overflow: hidden;
    color: var(--rb-text);
    font-size: 9.5px;
    font-weight: 800;
    line-height: 1.4;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.rb-product-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin-top: 5px;
    direction: ltr;
}

.rb-product-price {
    color: var(--rb-orange);
    font-size: 10px;
    font-weight: 900;
    direction: ltr;
}

.rb-product-plus {
    width: 18px;
    height: 18px;
    display: grid;
    place-items: center;
    flex: 0 0 18px;
    color: #fff;
    background: var(--rb-orange);
    border-radius: 50%;
    font-size: 13px;
    font-weight: 500;
    line-height: 1;
}

.rb-product-qty-badge {
    position: absolute;
    top: 7px;
    right: 7px;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--rb-orange);
    border: 2px solid #fff;
    border-radius: 999px;
    box-shadow: 0 2px 7px rgba(0, 0, 0, .12);
    font-size: 9px;
    font-weight: 900;
}

.rb-product-qty-badge[hidden] {
    display: none;
}

.rb-products-empty {
    grid-column: 1 / -1;
    min-height: 260px;
    display: grid;
    place-items: center;
    color: var(--rb-muted);
    font-size: 11px;
    direction: rtl;
}

.rb-products-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 12px;
    padding-top: 10px;
    color: var(--rb-muted);
    border-top: 1px solid var(--rb-border);
    font-size: 9px;
    direction: rtl;
}

.rb-products-footer b {
    color: var(--rb-text);
}

.rb-order-panel {
    position: sticky;
    top: 76px;
    overflow: hidden;
}

.rb-order-top-controls {
    padding: 12px 12px 10px;
    border-bottom: 1px solid var(--rb-border);
}

.rb-existing-search {
    margin-bottom: 8px;
}

.rb-order-selects {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 7px;
}

.rb-order-card {
    background: var(--rb-white);
}

.rb-order-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 11px 12px 8px;
}

.rb-order-title {
    display: flex;
    align-items: center;
    gap: 7px;
    color: var(--rb-text);
    font-size: 10px;
    direction: rtl;
}

.rb-order-bag {
    width: 22px;
    height: 22px;
    display: grid;
    place-items: center;
    color: var(--rb-text);
}

.rb-order-bag svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.rb-order-status-pill {
    padding: 3px 7px;
    color: var(--rb-muted);
    background: var(--rb-soft);
    border-radius: 999px;
    font-size: 8.5px;
    font-weight: 750;
    direction: rtl;
}

.rb-cart-scroll {
    min-height: 240px;
    max-height: 380px;
    overflow-y: auto;
    padding: 0 12px;
    scrollbar-width: thin;
}

.rb-cart-empty {
    min-height: 230px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--rb-muted);
    text-align: center;
    direction: rtl;
}

/* Important: author CSS can override the browser [hidden] rule. */
.rb-cart-empty[hidden] {
    display: none !important;
}

.rb-cart-empty-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    margin-bottom: 7px;
    color: color-mix(in srgb, var(--rb-muted) 60%, transparent);
    background: var(--rb-soft);
    border-radius: 50%;
}

.rb-cart-empty-icon svg {
    width: 21px;
    height: 21px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.6;
    stroke-linecap: round;
}

.rb-cart-empty strong {
    color: var(--rb-text);
    font-size: 10px;
}

.rb-cart-empty span {
    margin-top: 3px;
    font-size: 8.5px;
}

.rb-cart-item {
    padding: 9px 0;
    border-top: 1px solid var(--rb-border);
}

.rb-cart-item:first-child {
    border-top: 0;
}

.rb-cart-item-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    align-items: start;
}

.rb-cart-item-name {
    min-width: 0;
    direction: rtl;
    text-align: left;
}

.rb-cart-item-name strong {
    display: block;
    overflow: hidden;
    color: var(--rb-text);
    font-size: 9.5px;
    font-weight: 850;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.rb-cart-item-price-line {
    display: flex;
    align-items: center;
    gap: 3px;
    margin-top: 5px;
    color: var(--rb-orange);
    font-size: 8.5px;
    font-weight: 800;
    direction: ltr;
}

.rb-cart-remove {
    width: 23px;
    height: 23px;
    display: grid;
    place-items: center;
    padding: 0;
    color: var(--rb-danger);
    background: var(--rb-white);
    border: 1px solid color-mix(in srgb, var(--rb-danger) 25%, var(--rb-border));
    border-radius: 50%;
    cursor: pointer;
}

.rb-cart-remove svg {
    width: 11px;
    height: 11px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
}

.rb-cart-item-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 7px;
}

.rb-qty-control {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    direction: ltr;
}

.rb-qty-btn {
    width: 19px;
    height: 19px;
    display: grid;
    place-items: center;
    padding: 0;
    color: var(--rb-muted);
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 50%;
    font-size: 11px;
    font-weight: 800;
    line-height: 1;
    cursor: pointer;
}

.rb-qty-btn:hover {
    color: var(--rb-orange);
    border-color: color-mix(in srgb, var(--rb-orange) 38%, var(--rb-border));
}

.rb-qty-value {
    min-width: 19px;
    text-align: center;
    color: var(--rb-text);
    font-size: 9px;
    font-weight: 850;
}

.rb-add-note {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 6px;
    color: var(--rb-muted);
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 4px;
    font-size: 7.8px;
    font-weight: 750;
    cursor: pointer;
    direction: rtl;
}

.rb-add-note svg {
    width: 10px;
    height: 10px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
}

.rb-item-note-wrap {
    margin-top: 7px;
}

.rb-item-note-wrap[hidden] {
    display: none;
}

.rb-item-note {
    width: 100%;
    height: 32px;
    padding: 0 8px;
    color: var(--rb-text);
    background: var(--rb-soft-2);
    border: 1px solid var(--rb-border);
    border-radius: 5px;
    outline: none;
    font-size: 8.5px;
    direction: rtl;
}

.rb-more-details {
    width: calc(100% - 24px);
    min-height: 34px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 4px 12px 0;
    padding: 0 9px;
    color: var(--rb-muted);
    background: var(--rb-soft-2);
    border: 1px solid var(--rb-border);
    border-radius: 5px;
    font-size: 8.7px;
    font-weight: 750;
    cursor: pointer;
    direction: rtl;
}

.rb-more-details > span:first-child {
    display: flex;
    align-items: center;
    gap: 6px;
}

.rb-more-details svg {
    width: 12px;
    height: 12px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
}

.rb-summary {
    padding: 11px 12px 9px;
    border-top: 1px solid var(--rb-border);
    margin-top: 10px;
}

.rb-summary-row,
.rb-summary-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    direction: rtl;
}

.rb-summary-row {
    margin-bottom: 6px;
    color: var(--rb-muted);
    font-size: 8.7px;
}

.rb-summary-row strong {
    color: var(--rb-text);
    font-weight: 800;
    direction: ltr;
}

.rb-summary-total {
    padding-top: 7px;
    color: var(--rb-text);
    border-top: 1px dashed var(--rb-border);
    font-size: 10px;
    font-weight: 900;
}

.rb-summary-total strong {
    font-size: 12px;
    direction: ltr;
}

.rb-order-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 7px;
    padding: 10px 12px 12px;
    border-top: 1px solid var(--rb-border);
}

.rb-action {
    min-height: 37px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 0 8px;
    border: 1px solid transparent;
    border-radius: 5px;
    font-size: 9px;
    font-weight: 850;
    cursor: pointer;
    transition: .14s ease;
}

.rb-action:hover {
    filter: brightness(.97);
    transform: translateY(-1px);
}

.rb-action svg {
    width: 12px;
    height: 12px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
}

.rb-action-kot {
    color: #fff;
    background: var(--rb-navy);
    border-color: var(--rb-navy);
}

.rb-action-draft {
    color: var(--rb-muted);
    background: var(--rb-white);
    border-color: var(--rb-border);
}

.rb-action-payment {
    color: #fff;
    background: var(--rb-orange);
    border-color: var(--rb-orange);
}

.rb-action-print {
    color: #fff;
    background: var(--rb-green);
    border-color: var(--rb-green);
}

/* Modal */
.rb-modal {
    position: fixed;
    inset: 0;
    z-index: 10050;
    display: grid;
    place-items: center;
    padding: 20px;
    direction: rtl;
}

.rb-modal[hidden] {
    display: none;
}

.rb-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, .38);
    backdrop-filter: blur(2px);
}

.rb-modal-dialog {
    position: relative;
    z-index: 1;
    width: min(720px, 96vw);
    max-height: 90vh;
    overflow: auto;
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 10px;
    box-shadow: 0 24px 70px rgba(16, 24, 39, .2);
}

.rb-modal-head,
.rb-modal-foot {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
}

.rb-modal-head {
    justify-content: space-between;
    border-bottom: 1px solid var(--rb-border);
}

.rb-modal-head h2 {
    margin: 0;
    color: var(--rb-text);
    font-size: 14px;
    font-weight: 900;
}

.rb-modal-head p {
    margin: 3px 0 0;
    color: var(--rb-muted);
    font-size: 9px;
}

.rb-modal-close {
    width: 31px;
    height: 31px;
    display: grid;
    place-items: center;
    padding: 0;
    color: var(--rb-muted);
    background: var(--rb-soft);
    border: 0;
    border-radius: 6px;
    font-size: 18px;
    cursor: pointer;
}

.rb-modal-body {
    padding: 15px 16px;
}

.rb-modal-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 11px;
}

.rb-field-wide {
    grid-column: 1 / -1;
}

.rb-field label {
    display: block;
    margin-bottom: 5px;
    color: var(--rb-muted);
    font-size: 9px;
    font-weight: 800;
}

.rb-file-control {
    padding-top: 7px;
}

.rb-textarea {
    height: auto;
    min-height: 75px;
    padding: 9px 10px;
    resize: vertical;
}

.rb-modal-foot {
    justify-content: flex-end;
    border-top: 1px solid var(--rb-border);
}

.rb-modal-total {
    margin-left: auto;
    text-align: right;
}

.rb-modal-total span,
.rb-modal-total strong {
    display: block;
}

.rb-modal-total span {
    color: var(--rb-muted);
    font-size: 8.5px;
}

.rb-modal-total strong {
    margin-top: 2px;
    color: var(--rb-orange);
    font-size: 14px;
    direction: ltr;
}

/* Drawer */
.rb-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 10020;
    background: rgba(15, 23, 42, .3);
    backdrop-filter: blur(2px);
}

.rb-drawer {
    position: fixed;
    top: 0;
    right: 0;
    z-index: 10030;
    width: min(390px, 94vw);
    height: 100dvh;
    display: flex;
    flex-direction: column;
    color: var(--rb-text);
    background: var(--rb-white);
    border-left: 1px solid var(--rb-border);
    box-shadow: -22px 0 55px rgba(15, 23, 42, .16);
    transform: translateX(102%);
    transition: transform .2s ease;
    direction: rtl;
}

.rb-drawer.is-open {
    transform: translateX(0);
}

.rb-drawer-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    padding: 15px;
    border-bottom: 1px solid var(--rb-border);
}

.rb-drawer-head h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 900;
}

.rb-drawer-head p {
    margin: 3px 0 0;
    color: var(--rb-muted);
    font-size: 9px;
}

.rb-drawer-head button {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    padding: 0;
    color: var(--rb-muted);
    background: var(--rb-soft);
    border: 0;
    border-radius: 6px;
    font-size: 18px;
    cursor: pointer;
}

.rb-drawer-search {
    padding: 10px 12px;
    border-bottom: 1px solid var(--rb-border);
}

.rb-drawer-section {
    padding: 11px 12px;
    overflow: visible;
}

.rb-drawer-section-title {
    margin-bottom: 8px;
    color: var(--rb-muted);
    font-size: 9px;
    font-weight: 850;
}

.rb-server-order-card,
.rb-local-draft-card {
    display: block;
    margin-bottom: 7px;
    padding: 10px;
    color: var(--rb-text);
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 7px;
    text-decoration: none;
}

.rb-server-order-card:hover,
.rb-local-draft-card:hover {
    border-color: color-mix(in srgb, var(--rb-orange) 38%, var(--rb-border));
}

.rb-server-order-card[hidden] {
    display: none;
}

.rb-server-order-top,
.rb-local-draft-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.rb-server-order-top strong,
.rb-local-draft-top strong {
    font-size: 10px;
    font-weight: 900;
}

.rb-server-order-top span,
.rb-local-draft-top span {
    padding: 2px 6px;
    color: var(--rb-muted);
    background: var(--rb-soft);
    border-radius: 999px;
    font-size: 8px;
    font-weight: 800;
}

.rb-server-order-meta,
.rb-local-draft-meta {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 7px;
    color: var(--rb-muted);
    font-size: 8px;
}

.rb-local-draft-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
}

.rb-local-draft-actions button {
    min-height: 29px;
    padding: 0 9px;
    border-radius: 5px;
    font-size: 8px;
    font-weight: 800;
    cursor: pointer;
}

.rb-draft-restore {
    color: #fff;
    background: var(--rb-orange);
    border: 1px solid var(--rb-orange);
}

.rb-draft-delete {
    color: var(--rb-danger);
    background: color-mix(in srgb, var(--rb-danger) 8%, var(--rb-white));
    border: 1px solid color-mix(in srgb, var(--rb-danger) 25%, var(--rb-border));
}

.rb-drawer-empty {
    padding: 24px 10px;
    color: var(--rb-muted);
    text-align: center;
    font-size: 9.5px;
}

/* Responsive */
@media (max-width: 1320px) {
    .rb-pos {
        grid-template-columns: 1fr;
    }

    .rb-pos-workspace {
        padding: 18px 16px 16px;
    }

    .rb-pos-shell {
        grid-template-columns: minmax(0, 1fr) 350px;
        gap: 12px;
    }

    .rb-product-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .rb-side-user {
        grid-template-columns: 28px minmax(0, 1fr);
    }
}

@media (max-width: 1080px) {
    .rb-pos {
        grid-template-columns: 1fr;
    }

    .rb-side-brand {
        justify-content: center;
        padding: 0;
    }

    .rb-side-brand > strong,
    .rb-side-user > div:last-child,
    .rb-side-link span,
    .rb-side-section {
        display: none;
    }

    .rb-side-user {
        display: flex;
        justify-content: center;
        padding-inline: 0;
    }

    .rb-side-link {
        justify-content: center;
        padding: 0;
    }

    .rb-pos-shell {
        grid-template-columns: minmax(0, 1fr) 330px;
    }

    .rb-product-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 820px) {
    .rb-pos {
        grid-template-columns: 1fr;
        overflow: auto;
    }

    .rb-pos-sidebar {
        display: none;
    }

    .rb-pos-workspace {
        height: auto;
        min-height: 100dvh;
        overflow: visible;
        padding: 12px;
    }

    .rb-pos-shell {
        grid-template-columns: 1fr;
    }

    .rb-order-panel {
        position: static;
        max-height: none;
    }

    .rb-pos-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .rb-pos-heading {
        text-align: right;
    }

    .rb-pos-header-actions {
        width: 100%;
        justify-content: flex-start;
    }

    .rb-products-toolbar {
        grid-template-columns: 1fr 1fr;
    }

    .rb-search-products {
        grid-column: 1 / -1;
    }

    .rb-product-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .rb-pos-header-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .rb-btn {
        width: 100%;
    }

    .rb-products-toolbar {
        grid-template-columns: 1fr;
    }

    .rb-search-products {
        grid-column: auto;
    }

    .rb-product-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .rb-order-selects,
    .rb-modal-grid {
        grid-template-columns: 1fr;
    }

    .rb-field-wide {
        grid-column: auto;
    }

    .rb-order-actions {
        grid-template-columns: 1fr;
    }

    .rb-modal-foot {
        align-items: stretch;
        flex-direction: column;
    }

    .rb-modal-total {
        margin-left: 0;
        margin-bottom: 4px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rbRoot = document.getElementById('rbPosRoot');
    const rbDrawerBackdropNode = document.getElementById('rbDrawerBackdrop');
    const rbDrawerNode = document.getElementById('rbOrdersDrawer');

    document.documentElement.classList.add('rb-pos-mode');
    document.body.classList.add('rb-pos-mode');

    // Move the POS to <body> so the normal ERP sidebar/header cannot squeeze or overlay it.
    if (rbRoot && rbRoot.parentElement !== document.body) {
        document.body.appendChild(rbRoot);
    }
    if (rbDrawerBackdropNode && rbDrawerBackdropNode.parentElement !== document.body) {
        document.body.appendChild(rbDrawerBackdropNode);
    }
    if (rbDrawerNode && rbDrawerNode.parentElement !== document.body) {
        document.body.appendChild(rbDrawerNode);
    }
    const products = @json($productPayload);
    const oldItems = @json(old('items', []));
    const locationId = @json((int) $location->id);
    const cashierName = @json($cashierName);
    const locationName = @json($location->name);

    const form = document.getElementById('restaurantPosForm');
    const actionInput = document.getElementById('rbPosAction');

    const productGrid = document.getElementById('rbProductGrid');
    const productSearch = document.getElementById('rbProductSearch');
    const categoryTabs = document.getElementById('rbCategoryTabs');
    const categorySelect = document.getElementById('rbCategorySelect');
    const brandSelect = document.getElementById('rbBrandSelect');
    const visibleProductCount = document.getElementById('rbVisibleProductCount');

    const cartItems = document.getElementById('rbCartItems');
    const cartEmpty = document.getElementById('rbCartEmpty');
    const hiddenItems = document.getElementById('rbHiddenItems');
    const cartCount = document.getElementById('rbCartCount');
    const subtotalEl = document.getElementById('rbSubtotal');
    const discountAmountEl = document.getElementById('rbDiscountAmount');
    const grandTotalEl = document.getElementById('rbGrandTotal');
    const checkoutTotalEl = document.getElementById('rbCheckoutTotal');

    const serviceType = document.getElementById('rbServiceType');
    const tableSelect = document.getElementById('rbTableSelect');
    const guestCountGroup = document.getElementById('rbGuestCountGroup');

    const discountType = document.getElementById('rbDiscountType');
    const discountValue = document.getElementById('rbDiscountValue');

    const paymentArrangement = document.getElementById('rbPaymentArrangement');
    const paymentMethod = document.getElementById('rbPaymentMethod');
    const paymentMethodGroup = document.getElementById('rbPaymentMethodGroup');
    const paidAmountGroup = document.getElementById('rbPaidAmountGroup');
    const referenceGroup = document.getElementById('rbReferenceGroup');
    const senderNameGroup = document.getElementById('rbSenderNameGroup');
    const senderPhoneGroup = document.getElementById('rbSenderPhoneGroup');
    const senderAccountGroup = document.getElementById('rbSenderAccountGroup');
    const proofGroup = document.getElementById('rbProofGroup');

    const referenceInput = form.querySelector('[name="reference_number"]');
    const senderNameInput = form.querySelector('[name="sender_name"]');
    const senderPhoneInput = form.querySelector('[name="sender_phone"]');
    const senderAccountInput = form.querySelector('[name="sender_account_number"]');
    const proofInput = form.querySelector('[name="payment_proof"]');

    const checkoutModal = document.getElementById('rbCheckoutModal');
    const checkoutContinueBtn = document.getElementById('rbCheckoutContinueBtn');

    const newOrderBtn = document.getElementById('rbNewOrderBtn');
    const tableOrderBtn = document.getElementById('rbTableOrderBtn');
    const openCheckoutBtn = document.getElementById('rbOpenCheckoutBtn');
    const saveDraftBtn = document.getElementById('rbSaveDraftBtn');
    const billPaymentBtn = document.getElementById('rbBillPaymentBtn');
    const billPrintBtn = document.getElementById('rbBillPrintBtn');
    const kotPrintBtn = document.getElementById('rbKotPrintBtn');

    const qrOrdersBtn = document.getElementById('rbQrOrdersBtn');
    const draftListBtn = document.getElementById('rbDraftListBtn');
    const existingOrderSearch = document.getElementById('rbExistingOrderSearch');

    const drawer = document.getElementById('rbOrdersDrawer');
    const drawerBackdrop = document.getElementById('rbDrawerBackdrop');
    const drawerTitle = document.getElementById('rbDrawerTitle');
    const drawerSubtitle = document.getElementById('rbDrawerSubtitle');
    const drawerSearchInput = document.getElementById('rbDrawerSearchInput');
    const closeDrawerBtn = document.getElementById('rbCloseDrawer');
    const localDraftSection = document.getElementById('rbLocalDraftSection');
    const localDraftContainer = document.getElementById('rbLocalDraftContainer');
    const serverOrdersTitle = document.getElementById('rbServerOrdersTitle');
    const serverOrderCards = [...document.querySelectorAll('[data-server-order]')];

    const cart = new Map();
    let activeCategory = '__all__';
    let activeBrand = '__all__';
    let pendingAction = 'bill_payment';
    let drawerMode = 'all';

    const draftKey = `restaurant_pos_draft_${locationId}`;

    oldItems.forEach(item => {
        const product = products.find(p => Number(p.id) === Number(item.product_id));
        if (!product) return;

        cart.set(Number(product.id), {
            ...product,
            quantity: Math.max(1, Number(item.quantity || 1)),
            kitchen_notes: item.kitchen_notes || '',
        });
    });

    const categories = [...new Set(products.map(p => p.category || 'بدون فئة'))];
    const brands = [...new Set(products.map(p => p.brand).filter(Boolean))];

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function money(value) {
        return `₪${Number(value || 0).toFixed(2)}`;
    }

    function statusMessage(message, type = 'info') {
        const old = document.getElementById('rbPosToast');
        old?.remove();

        const toast = document.createElement('div');
        toast.id = 'rbPosToast';

        const background =
            type === 'error'
                ? 'var(--rb-danger, #D92D20)'
                : (
                    type === 'success'
                        ? 'var(--rb-green, #197438)'
                        : 'var(--rb-navy, #0A2948)'
                );

        toast.textContent = message;

        toast.style.cssText = `
            position:fixed;
            left:50%;
            bottom:28px;
            z-index:2147483646;
            transform:translateX(-50%);
            max-width:min(720px,92vw);
            padding:12px 18px;
            color:#fff;
            background:${background};
            border-radius:9px;
            font-size:12px;
            font-weight:850;
            line-height:1.7;
            direction:rtl;
            text-align:center;
            box-shadow:0 14px 38px rgba(0,0,0,.28);
        `;

        /*
         * مهم:
         * .rb-pos نفسه z-index = 2147482000.
         * لذلك إلحاق الـ Toast بـ body مع z-index صغير يجعله خلف شاشة POS.
         * نضعه داخل rbRoot نفسه ليبقى فوق الواجهة.
         */
        (rbRoot || document.body).appendChild(toast);

        window.setTimeout(
            () => toast.remove(),
            type === 'error' ? 6000 : 3000
        );
    }

    function clearCheckoutError() {
        document.getElementById('rbCheckoutServerError')?.remove();

        [
            referenceInput,
            proofInput,
            paymentMethod,
        ].forEach(field => {
            field?.style.removeProperty('border-color');
            field?.style.removeProperty('box-shadow');
        });
    }

    function showCheckoutError(message, fieldName = null) {
        clearCheckoutError();

        let box = document.getElementById('rbCheckoutServerError');

        if (!box) {
            box = document.createElement('div');
            box.id = 'rbCheckoutServerError';
            box.style.cssText = `
                margin:0 0 12px;
                padding:10px 12px;
                color:#B42318;
                background:#FEF3F2;
                border:1px solid #FECDCA;
                border-radius:7px;
                font-size:11px;
                font-weight:800;
                line-height:1.7;
                direction:rtl;
            `;

            checkoutModal
                .querySelector('.rb-modal-body')
                ?.prepend(box);
        }

        box.textContent = message;

        const field =
            fieldName === 'reference_number'
                ? referenceInput
                : (
                    fieldName === 'payment_proof'
                        ? proofInput
                        : (
                            fieldName === 'payment_method_id'
                                ? paymentMethod
                                : null
                        )
                );

        if (field) {
            field.style.borderColor = 'var(--rb-danger, #D92D20)';
            field.style.boxShadow = '0 0 0 3px rgba(217,45,32,.10)';

            window.setTimeout(
                () => field.focus(),
                80
            );
        }
    }

    function currentDiscount(subtotal) {
        const type = discountType?.value || 'none';
        const raw = Math.max(0, Number(discountValue?.value || 0));

        if (type === 'percentage') {
            return Math.min(subtotal, subtotal * Math.min(raw, 100) / 100);
        }

        if (type === 'fixed') {
            return Math.min(subtotal, raw);
        }

        return 0;
    }

    function getTotals() {
        const rows = [...cart.values()];
        const subtotal = rows.reduce(
            (sum, item) => sum + Number(item.price) * Number(item.quantity),
            0
        );
        const discount = currentDiscount(subtotal);
        return {
            subtotal,
            discount,
            total: Math.max(0, subtotal - discount),
        };
    }

    function productImage(product) {
        if (product.image) {
            return `
                <img
                    src="${escapeHtml(product.image)}"
                    alt="${escapeHtml(product.name)}"
                    loading="lazy"
                    onerror="this.style.display='none';this.nextElementSibling.hidden=false;"
                >
                <div class="rb-product-placeholder" hidden>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="3"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <path d="m21 15-5-5L5 21"></path>
                    </svg>
                </div>
            `;
        }

        return `
            <div class="rb-product-placeholder">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="3"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <path d="m21 15-5-5L5 21"></path>
                </svg>
            </div>
        `;
    }

    function renderFilters() {
        categorySelect.innerHTML = `
            <option value="__all__">كل الفئات</option>
            ${categories.map(category => `
                <option value="${escapeHtml(category)}">${escapeHtml(category)}</option>
            `).join('')}
        `;
        categorySelect.value = activeCategory;

        brandSelect.innerHTML = `
            <option value="__all__">كل العلامات</option>
            ${brands.map(brand => `
                <option value="${escapeHtml(brand)}">${escapeHtml(brand)}</option>
            `).join('')}
        `;
        brandSelect.value = activeBrand;

        const tabCategories = categories.slice(0, 8);
        categoryTabs.innerHTML = `
            <button type="button" class="rb-category-tab ${activeCategory === '__all__' ? 'active' : ''}" data-category="__all__">
                عرض الكل
            </button>
            ${tabCategories.map(category => `
                <button
                    type="button"
                    class="rb-category-tab ${activeCategory === category ? 'active' : ''}"
                    data-category="${escapeHtml(category)}"
                >
                    ${escapeHtml(category)}
                </button>
            `).join('')}
        `;
    }

    function renderProducts() {
        const query = (productSearch.value || '').trim().toLowerCase();

        const filtered = products.filter(product => {
            const categoryOk = activeCategory === '__all__' || product.category === activeCategory;
            const brandOk = activeBrand === '__all__' || product.brand === activeBrand;
            const searchText = `${product.name} ${product.category} ${product.sku || ''}`.toLowerCase();
            const searchOk = !query || searchText.includes(query);
            return categoryOk && brandOk && searchOk;
        });

        visibleProductCount.textContent = filtered.length;

        if (!filtered.length) {
            productGrid.innerHTML = `
                <div class="rb-products-empty">
                    لا توجد منتجات مطابقة للبحث أو الفلتر الحالي.
                </div>
            `;
            return;
        }

        productGrid.innerHTML = filtered.map(product => {
            const item = cart.get(Number(product.id));
            const qty = item ? Number(item.quantity) : 0;

            return `
                <button
                    type="button"
                    class="rb-product-card"
                    data-product-id="${product.id}"
                    title="إضافة ${escapeHtml(product.name)}"
                >
                    <div class="rb-product-image">
                        ${productImage(product)}
                    </div>

                    <span class="rb-product-qty-badge" ${qty > 0 ? '' : 'hidden'}>
                        ${qty > 0 ? qty : ''}
                    </span>

                    <div class="rb-product-info">
                        <div class="rb-product-meta-line">
                            <span class="rb-product-category">${escapeHtml(product.category || 'بدون فئة')}</span>
                            <span class="rb-product-sku">${escapeHtml(product.sku || '')}</span>
                        </div>

                        <span class="rb-product-name">${escapeHtml(product.name)}</span>

                        <div class="rb-product-bottom">
                            <span class="rb-product-price">${money(product.price)}</span>
                            <span class="rb-product-plus">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14"></path>
                                </svg>
                            </span>
                        </div>
                    </div>
                </button>
            `;
        }).join('');
    }

    function syncHiddenItems() {
        hiddenItems.innerHTML = [...cart.values()].map((item, index) => `
            <input type="hidden" name="items[${index}][product_id]" value="${item.id}">
            <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">
            <input type="hidden" name="items[${index}][kitchen_notes]" value="${escapeHtml(item.kitchen_notes || '')}">
        `).join('');
    }

    function renderSummary() {
        const totals = getTotals();
        subtotalEl.textContent = money(totals.subtotal);
        discountAmountEl.textContent = money(totals.discount);
        grandTotalEl.textContent = money(totals.total);
        checkoutTotalEl.textContent = money(totals.total);
        cartCount.textContent = cart.size;
    }

    function renderCart() {
        const rows = [...cart.values()];
        const hasCartItems = rows.length > 0;

        cartEmpty.hidden = hasCartItems;
        cartEmpty.style.display = hasCartItems ? 'none' : 'flex';

        cartItems.innerHTML = rows.map(item => {
            const lineTotal = Number(item.price) * Number(item.quantity);
            const hasNote = Boolean(item.kitchen_notes);

            return `
                <div class="rb-cart-item" data-cart-row="${item.id}">
                    <div class="rb-cart-item-head">
                        <div class="rb-cart-item-name">
                            <strong>${escapeHtml(item.name)}</strong>
                            <div class="rb-cart-item-price-line">
                                <span>${money(item.price)}</span>
                                <span>×</span>
                                <span>${Number(item.quantity)}</span>
                                <span>=</span>
                                <span>${money(lineTotal)}</span>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="rb-cart-remove"
                            data-cart-action="remove"
                            data-id="${item.id}"
                            title="حذف"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 7h16M9 7V4h6v3M8 10v7M12 10v7M16 10v7M6 7l1 14h10l1-14"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="rb-cart-item-foot">
                        <div class="rb-qty-control">
                            <button type="button" class="rb-qty-btn" data-cart-action="minus" data-id="${item.id}">−</button>
                            <span class="rb-qty-value">${Number(item.quantity)}</span>
                            <button type="button" class="rb-qty-btn" data-cart-action="plus" data-id="${item.id}">+</button>
                        </div>

                        <button type="button" class="rb-add-note" data-note-toggle="${item.id}">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 5h16v11H8l-4 4z"></path>
                            </svg>
                            ${hasNote ? 'تعديل الملاحظة' : 'إضافة ملاحظة'}
                        </button>
                    </div>

                    <div class="rb-item-note-wrap" data-note-wrap="${item.id}" ${hasNote ? '' : 'hidden'}>
                        <input
                            type="text"
                            class="rb-item-note"
                            data-kitchen-note="${item.id}"
                            maxlength="500"
                            value="${escapeHtml(item.kitchen_notes || '')}"
                            placeholder="مثال: بدون بصل، زيادة صوص..."
                        >
                    </div>
                </div>
            `;
        }).join('');

        syncHiddenItems();
        renderSummary();
        renderProducts();
    }

    function addProduct(productId) {
        const id = Number(productId);
        const product = products.find(p => Number(p.id) === id);
        if (!product) return;

        if (cart.has(id)) {
            cart.get(id).quantity += 1;
        } else {
            cart.set(id, {
                ...product,
                quantity: 1,
                kitchen_notes: '',
            });
        }

        renderCart();
    }

    function clearOrder(ask = true) {
        if (!cart.size && !ask) return;

        if (ask && cart.size && !window.confirm('هل تريد بدء طلب جديد ومسح الطلب الحالي؟')) {
            return;
        }

        cart.clear();
        renderCart();
        productSearch.value = '';
        activeCategory = '__all__';
        activeBrand = '__all__';
        renderFilters();
        renderProducts();
        statusMessage('تم فتح طلب جديد.');
    }

    function syncServiceType() {
        const dineIn = serviceType.value === 'dine_in';
        tableSelect.required = dineIn;
        guestCountGroup.hidden = !dineIn;

        if (!dineIn) {
            tableSelect.value = '';
        }

        if (dineIn && paymentArrangement.value === 'pay_now' && !paymentMethod.value) {
            paymentArrangement.value = 'pay_on_pickup';
        }

        if (!dineIn && paymentArrangement.value === 'pay_on_pickup') {
            paymentArrangement.value = 'pay_now';
        }

        syncPayment();
    }

    function syncPayment() {
        const arrangement = paymentArrangement.value;

        const noImmediate = [
            'pay_on_pickup',
            'on_account'
        ].includes(arrangement);

        const partial = [
            'deposit',
            'partial_payment'
        ].includes(arrangement);

        paymentMethodGroup.hidden = noImmediate;
        paymentMethod.required = !noImmediate;
        paidAmountGroup.hidden = !partial;

        const selected =
            paymentMethod.options[
                paymentMethod.selectedIndex
            ];

        const methodType =
            !noImmediate
                ? (selected?.dataset.type || '')
                : '';

        const isNonCash =
            !noImmediate
            && paymentMethod.value !== ''
            && methodType !== 'cash';

        const methodRequiresReference =
            !noImmediate
            && selected?.dataset.reference === '1';

        const methodRequiresVerification =
            !noImmediate
            && selected?.dataset.verification === '1';

        /*
         * في الكاشير: أي دفع غير نقدي يجب أن يحمل بيانات العملية
         * حتى يمكن مراجعته لاحقاً من الطلب وسجل المدفوعات.
         */
        const requiresReference =
            !noImmediate
            && (
                isNonCash
                || methodRequiresReference
                || methodRequiresVerification
                || arrangement === 'pending_verification'
            );

        const requiresProof =
            !noImmediate
            && (
                methodRequiresVerification
                || arrangement === 'pending_verification'
            );

        referenceGroup.hidden = !requiresReference;
        senderNameGroup.hidden = !isNonCash;
        senderPhoneGroup.hidden = !isNonCash;
        senderAccountGroup.hidden = !isNonCash;
        proofGroup.hidden = !requiresProof;

        if (referenceInput) {
            referenceInput.required = requiresReference;
        }

        if (senderNameInput) {
            senderNameInput.required = isNonCash;
        }

        if (proofInput) {
            proofInput.required = requiresProof;
        }

        clearCheckoutError();
    }

    function openCheckout(action = 'bill_payment') {
        pendingAction = action;
        actionInput.value = action;
        syncPayment();
        renderSummary();
        checkoutModal.hidden = false;
        checkoutModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeCheckout() {
        checkoutModal.hidden = true;
        checkoutModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function validateOrderBasics() {
        if (!cart.size) {
            window.alert('أضف منتجًا واحدًا على الأقل للطلب.');
            return false;
        }

        if (serviceType.value === 'dine_in' && !tableSelect.value) {
            window.alert('اختر الطاولة لطلب الصالة.');
            tableSelect.focus();
            return false;
        }

        return true;
    }

    function validatePaymentFields() {
        syncPayment();

        const salesChannel = form.querySelector(
            '[name="sales_channel_id"]'
        );

        if (!salesChannel?.value) {
            showCheckoutError(
                'اختر قناة البيع.',
                'sales_channel_id'
            );
            salesChannel?.focus();
            return false;
        }

        if (
            paymentMethod.required
            && !paymentMethod.value
        ) {
            showCheckoutError(
                'اختر طريقة الدفع.',
                'payment_method_id'
            );
            return false;
        }

        if (
            referenceInput?.required
            && !referenceInput.value.trim()
        ) {
            showCheckoutError(
                'رقم العملية / المرجع مطلوب لطريقة الدفع المحددة.',
                'reference_number'
            );
            return false;
        }

        const selectedPaymentMethod =
            paymentMethod.options[
                paymentMethod.selectedIndex
            ];

        const nonCashPayment =
            paymentMethod.required
            && paymentMethod.value !== ''
            && (selectedPaymentMethod?.dataset.type || '') !== 'cash';

        if (
            nonCashPayment
            && !senderNameInput?.value.trim()
        ) {
            showCheckoutError(
                'اسم المحوّل أو صاحب عملية الدفع مطلوب.',
                'sender_name'
            );
            senderNameInput?.focus();
            return false;
        }

        if (
            nonCashPayment
            && !senderPhoneInput?.value.trim()
            && !senderAccountInput?.value.trim()
        ) {
            showCheckoutError(
                'أدخل رقم جوال المحوّل أو رقم حسابه/محفظته.',
                'sender_phone'
            );
            senderPhoneInput?.focus();
            return false;
        }

        if (
            proofInput?.required
            && !proofInput.files.length
        ) {
            showCheckoutError(
                'هذه طريقة دفع تحتاج إثبات دفع.',
                'payment_proof'
            );
            return false;
        }

        clearCheckoutError();

        return true;
    }

    function serializeDraft() {
        const simpleField = name => form.querySelector(`[name="${name}"]`)?.value ?? '';

        return {
            version: 1,
            location_id: locationId,
            saved_at: new Date().toISOString(),
            items: [...cart.values()].map(item => ({
                id: item.id,
                quantity: item.quantity,
                kitchen_notes: item.kitchen_notes || '',
            })),
            fields: {
                service_type: serviceType.value,
                restaurant_table_id: tableSelect.value,
                guest_count: simpleField('guest_count'),
                customer_id: simpleField('customer_id'),
                sales_channel_id: simpleField('sales_channel_id'),
                payment_arrangement: paymentArrangement.value,
                payment_method_id: paymentMethod.value,
                paid_amount: simpleField('paid_amount'),
                discount_type: discountType.value,
                discount_value: discountValue.value,
                reference_number: simpleField('reference_number'),
                sender_name: simpleField('sender_name'),
                sender_phone: simpleField('sender_phone'),
                sender_account_number: simpleField('sender_account_number'),
                notes: simpleField('notes'),
            },
        };
    }

    function saveLocalDraft() {
        if (!cart.size) {
            window.alert('لا يوجد طلب لحفظه كمسودة.');
            return;
        }

        localStorage.setItem(draftKey, JSON.stringify(serializeDraft()));
        statusMessage('تم حفظ المسودة على هذا الجهاز.');
        renderLocalDraftCard();
    }

    function loadLocalDraft() {
        const raw = localStorage.getItem(draftKey);
        if (!raw) return null;

        try {
            return JSON.parse(raw);
        } catch (_) {
            return null;
        }
    }

    function restoreLocalDraft() {
        const draft = loadLocalDraft();
        if (!draft || !Array.isArray(draft.items)) {
            window.alert('المسودة غير متاحة أو تالفة.');
            return;
        }

        cart.clear();
        draft.items.forEach(row => {
            const product = products.find(p => Number(p.id) === Number(row.id));
            if (!product) return;

            cart.set(Number(product.id), {
                ...product,
                quantity: Math.max(1, Number(row.quantity || 1)),
                kitchen_notes: row.kitchen_notes || '',
            });
        });

        const fields = draft.fields || {};
        Object.entries(fields).forEach(([name, value]) => {
            const field = form.querySelector(`[name="${name}"]`);
            if (field && field.type !== 'file') {
                field.value = value ?? '';
            }
        });

        syncServiceType();
        syncPayment();
        renderCart();
        closeDrawer();
        statusMessage('تم استرجاع المسودة.');
    }

    function deleteLocalDraft() {
        localStorage.removeItem(draftKey);
        renderLocalDraftCard();
        statusMessage('تم حذف المسودة المحلية.');
    }

    function renderLocalDraftCard() {
        const draft = loadLocalDraft();

        if (!draft || !Array.isArray(draft.items) || !draft.items.length) {
            localDraftSection.hidden = true;
            localDraftContainer.innerHTML = '';
            return;
        }

        const itemCount = draft.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
        const savedAt = draft.saved_at ? new Date(draft.saved_at) : null;
        const savedLabel = savedAt && !Number.isNaN(savedAt.getTime())
            ? savedAt.toLocaleString('ar')
            : '—';

        localDraftSection.hidden = false;
        localDraftContainer.innerHTML = `
            <div class="rb-local-draft-card">
                <div class="rb-local-draft-top">
                    <strong>مسودة محلية</strong>
                    <span>${itemCount} قطعة</span>
                </div>
                <div class="rb-local-draft-meta">
                    <span>${escapeHtml(locationName)}</span>
                    <span>${escapeHtml(savedLabel)}</span>
                </div>
                <div class="rb-local-draft-actions">
                    <button type="button" class="rb-draft-restore" id="rbRestoreDraftBtn">استرجاع</button>
                    <button type="button" class="rb-draft-delete" id="rbDeleteDraftBtn">حذف</button>
                </div>
            </div>
        `;

        document.getElementById('rbRestoreDraftBtn')?.addEventListener('click', restoreLocalDraft);
        document.getElementById('rbDeleteDraftBtn')?.addEventListener('click', deleteLocalDraft);
    }

    function filterServerOrders() {
        const query = (drawerSearchInput.value || '').trim().toLowerCase();

        serverOrderCards.forEach(card => {
            const matchesQuery = !query || (card.dataset.search || '').includes(query);

            let matchesMode = true;
            if (drawerMode === 'draft') {
                matchesMode = (card.dataset.status || '') === 'draft';
            } else if (drawerMode === 'qr') {
                const channel = card.dataset.channel || '';
                matchesMode = channel.includes('qr') || channel.includes('كيو') || channel.includes('باركود');
            }

            card.hidden = !(matchesQuery && matchesMode);
        });
    }

    function openDrawer(mode = 'all', query = '') {
        drawerMode = mode;
        drawerSearchInput.value = query || '';

        if (mode === 'qr') {
            drawerTitle.textContent = 'طلبات QR';
            drawerSubtitle.textContent = 'آخر طلبات قناة QR المتاحة في البيانات الحالية';
            serverOrdersTitle.textContent = 'طلبات QR الأخيرة';
            localDraftSection.hidden = true;
        } else if (mode === 'draft') {
            drawerTitle.textContent = 'قائمة المسودات';
            drawerSubtitle.textContent = 'مسودات الجهاز + الطلبات المسجلة بحالة Draft';
            serverOrdersTitle.textContent = 'مسودات الخادم الحديثة';
            renderLocalDraftCard();
        } else {
            drawerTitle.textContent = 'البحث في الطلبات الحالية';
            drawerSubtitle.textContent = locationName;
            serverOrdersTitle.textContent = 'آخر الطلبات المسجلة';
            localDraftSection.hidden = true;
        }

        filterServerOrders();
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        drawerBackdrop.hidden = false;
        document.body.style.overflow = 'hidden';

        window.setTimeout(() => drawerSearchInput.focus(), 80);
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        drawerBackdrop.hidden = true;
        document.body.style.overflow = '';
    }

    function buildPrintableHtml(type = 'bill') {
        const totals = getTotals();
        const rows = [...cart.values()];
        const tableText = tableSelect.options[tableSelect.selectedIndex]?.text?.trim() || '—';
        const serviceText = serviceType.options[serviceType.selectedIndex]?.text?.trim() || serviceType.value;
        const title = type === 'kot' ? 'تذكرة المطبخ' : 'فاتورة نقطة البيع';

        return `
            <!doctype html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="utf-8">
                <title>${title}</title>
                <style>
                    *{box-sizing:border-box}
                    body{font-family:Arial,Tahoma,sans-serif;margin:0;padding:18px;color:#111}
                    .receipt{width:80mm;max-width:100%;margin:auto}
                    h1{font-size:18px;margin:0 0 4px;text-align:center}
                    .muted{text-align:center;color:#666;font-size:11px;margin-bottom:14px}
                    .meta{font-size:11px;line-height:1.8;border-top:1px dashed #999;border-bottom:1px dashed #999;padding:7px 0;margin-bottom:8px}
                    table{width:100%;border-collapse:collapse;font-size:11px}
                    td{padding:5px 0;border-bottom:1px dotted #ccc;vertical-align:top}
                    td:last-child{text-align:left;white-space:nowrap}
                    .note{font-size:10px;color:#555;padding-top:2px}
                    .totals{margin-top:10px;font-size:11px;line-height:1.9}
                    .total{font-size:15px;font-weight:700;border-top:1px dashed #777;padding-top:5px;margin-top:4px}
                    .footer{text-align:center;font-size:10px;color:#666;margin-top:14px}
                    @media print{body{padding:0}.receipt{width:80mm}}
                </style>
            </head>
            <body>
                <div class="receipt">
                    <h1>${escapeHtml(locationName)}</h1>
                    <div class="muted">${escapeHtml(title)}</div>
                    <div class="meta">
                        <div>الكاشير: ${escapeHtml(cashierName)}</div>
                        <div>الخدمة: ${escapeHtml(serviceText)}</div>
                        ${serviceType.value === 'dine_in' ? `<div>الطاولة: ${escapeHtml(tableText)}</div>` : ''}
                        <div>الوقت: ${escapeHtml(new Date().toLocaleString('ar'))}</div>
                    </div>
                    <table>
                        <tbody>
                            ${rows.map(item => `
                                <tr>
                                    <td>
                                        <strong>${escapeHtml(item.name)}</strong>
                                        ${item.kitchen_notes ? `<div class="note">ملاحظة: ${escapeHtml(item.kitchen_notes)}</div>` : ''}
                                    </td>
                                    <td>${Number(item.quantity)} × ${money(item.price)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                    ${type === 'bill' ? `
                        <div class="totals">
                            <div>الإجمالي الفرعي: ${money(totals.subtotal)}</div>
                            <div>الخصم: ${money(totals.discount)}</div>
                            <div class="total">الإجمالي: ${money(totals.total)}</div>
                        </div>
                    ` : ''}
                    <div class="footer">طباعة مباشرة من شاشة الكاشير</div>
                </div>
            </body>
            </html>
        `;
    }

    function printCurrent(type = 'bill') {
        const printWindow = window.open('', '_blank', 'width=420,height=720');
        if (!printWindow) {
            window.alert('المتصفح منع نافذة الطباعة. اسمح بالنوافذ المنبثقة ثم حاول مرة أخرى.');
            return false;
        }

        printWindow.document.open();
        printWindow.document.write(buildPrintableHtml(type));
        printWindow.document.close();
        printWindow.focus();

        window.setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 180);

        return true;
    }

   let rbSubmitting = false;

async function submitOrder(action) {
    if (rbSubmitting) {
        return;
    }

    if (!validateOrderBasics()) {
        return;
    }

    actionInput.value = action;

    if (action === 'kot_print') {
        if (
            ['pay_now', 'deposit', 'partial_payment']
                .includes(paymentArrangement.value)
        ) {
            paymentArrangement.value = 'pay_on_pickup';
        }

        syncPayment();

        const salesChannel = form.querySelector(
            '[name="sales_channel_id"]'
        );

        if (!salesChannel?.value) {
            openCheckout(action);
            return;
        }
    } else {
        if (!validatePaymentFields()) {
            openCheckout(action);
            return;
        }
    }

    syncHiddenItems();

    rbSubmitting = true;

    const buttons = [
        billPaymentBtn,
        billPrintBtn,
        kotPrintBtn,
        checkoutContinueBtn,
        newOrderBtn,
    ];

    buttons.forEach(button => {
        if (button) {
            button.disabled = true;
        }
    });

    let loader = document.getElementById(
        'rbPosSavingOverlay'
    );

    if (!loader) {
        loader = document.createElement('div');

        loader.id = 'rbPosSavingOverlay';

        loader.innerHTML = `
            <div style="
                background:#111827;
                color:#fff;
                padding:22px 28px;
                border-radius:14px;
                min-width:280px;
                text-align:center;
                box-shadow:0 20px 60px rgba(0,0,0,.30)
            ">
                <div style="
                    width:34px;
                    height:34px;
                    margin:0 auto 12px;
                    border:3px solid rgba(255,255,255,.25);
                    border-top-color:#fff;
                    border-radius:50%;
                    animation:rbSavingSpin .7s linear infinite
                "></div>

                <strong>
                    جاري تنفيذ الطلب...
                </strong>

                <div style="
                    margin-top:6px;
                    font-size:11px;
                    opacity:.7
                ">
                    يرجى عدم الضغط مرة أخرى
                </div>
            </div>
        `;

        loader.style.cssText = `
            position:fixed;
            inset:0;
            z-index:2147483645;
            display:grid;
            place-items:center;
            background:rgba(15,23,42,.30);
            backdrop-filter:blur(2px);
        `;

        const style = document.createElement('style');

        style.textContent = `
            @keyframes rbSavingSpin {
                to {
                    transform:rotate(360deg);
                }
            }
        `;

        document.head.appendChild(style);
        (rbRoot || document.body).appendChild(loader);
    }

    let printWindow = null;

    if (
        action === 'bill_print'
        || action === 'kot_print'
    ) {
        printWindow = window.open(
            'about:blank',
            '_blank',
            'width=460,height=760'
        );

        if (printWindow) {
            printWindow.document.write(`
                <html lang="ar" dir="rtl">
                    <head>
                        <meta charset="utf-8">
                        <title>جاري تجهيز الطباعة</title>
                    </head>

                    <body style="
                        font-family:Arial;
                        text-align:center;
                        padding:40px
                    ">
                        جاري حفظ الطلب وتجهيز الطباعة...
                    </body>
                </html>
            `);

            printWindow.document.close();
        }
    }

    try {
        const response = await fetch(
            form.action,
            {
                method: 'POST',

                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },

                credentials: 'same-origin',

                body: new FormData(form),
            }
        );

        let data = null;

        try {
            data = await response.json();
        } catch (error) {
            data = null;
        }

        if (!response.ok) {
            printWindow?.close();

            const errorBag = data?.errors || {};
            const errors =
                Object.values(errorBag)
                    .flat()
                    .filter(Boolean);

            const message =
                errors.length
                    ? errors.join(' — ')
                    : (
                        data?.message
                        || 'تعذر تنفيذ الطلب.'
                    );

            const firstField =
                Object.keys(errorBag)[0]
                || null;

            /*
             * في الفاتورة/الدفع نعيد إظهار شاشة الدفع نفسها
             * ونضع الخطأ بداخلها حتى لا يضيع على المستخدم.
             */
            if (action !== 'kot_print') {
                openCheckout(action);
                showCheckoutError(
                    message,
                    firstField
                );
            }

            statusMessage(
                message,
                'error'
            );

            return;
        }

        localStorage.removeItem(draftKey);

        if (
            action === 'bill_print'
            && printWindow
        ) {
            if (data?.invoice_print_url) {
                printWindow.location.href =
                    data.invoice_print_url;
            } else {
                printWindow.close();
            }
        }

        if (
            action === 'kot_print'
            && printWindow
        ) {
            printWindow.document.open();

            printWindow.document.write(
                buildPrintableHtml('kot')
            );

            printWindow.document.close();

            window.setTimeout(() => {
                printWindow.focus();
                printWindow.print();
            }, 250);
        }

        statusMessage(
            data?.message
            || 'تم تنفيذ الطلب بنجاح.',
            'success'
        );

        window.setTimeout(() => {
            window.location.href =
                data?.redirect_url
                || '{{ route('orders.index') }}';
        }, action === 'bill_payment' ? 150 : 700);

    } catch (error) {
        printWindow?.close();

        console.error(
            'POS submit error:',
            error
        );

        statusMessage(
            'تعذر الاتصال بالسيرفر. افحص الطلبات قبل إعادة المحاولة حتى لا يتم تسجيل الطلب مرتين.'
        );

    } finally {
        rbSubmitting = false;

        buttons.forEach(button => {
            if (button) {
                button.disabled = false;
            }
        });

        document
            .getElementById(
                'rbPosSavingOverlay'
            )
            ?.remove();
    }
}

    // -----------------------------
    // Product listeners
    // -----------------------------
    productGrid.addEventListener('click', event => {
        const card = event.target.closest('[data-product-id]');
        if (!card) return;
        addProduct(card.dataset.productId);
    });

    productSearch.addEventListener('input', renderProducts);

    categoryTabs.addEventListener('click', event => {
        const button = event.target.closest('[data-category]');
        if (!button) return;

        activeCategory = button.dataset.category;
        categorySelect.value = activeCategory;
        renderFilters();
        renderProducts();
    });

    categorySelect.addEventListener('change', () => {
        activeCategory = categorySelect.value;
        renderFilters();
        renderProducts();
    });

    brandSelect.addEventListener('change', () => {
        activeBrand = brandSelect.value;
        renderProducts();
    });

    // -----------------------------
    // Cart listeners
    // -----------------------------
    cartItems.addEventListener('click', event => {
        const actionButton = event.target.closest('[data-cart-action]');
        if (actionButton) {
            const id = Number(actionButton.dataset.id);
            const item = cart.get(id);
            if (!item) return;

            if (actionButton.dataset.cartAction === 'plus') {
                item.quantity += 1;
            } else if (actionButton.dataset.cartAction === 'minus') {
                item.quantity -= 1;
                if (item.quantity <= 0) cart.delete(id);
            } else if (actionButton.dataset.cartAction === 'remove') {
                cart.delete(id);
            }

            renderCart();
            return;
        }

        const noteButton = event.target.closest('[data-note-toggle]');
        if (noteButton) {
            const id = Number(noteButton.dataset.noteToggle);
            const wrap = cartItems.querySelector(`[data-note-wrap="${id}"]`);
            if (!wrap) return;
            wrap.hidden = !wrap.hidden;
            if (!wrap.hidden) {
                wrap.querySelector('[data-kitchen-note]')?.focus();
            }
        }
    });

    cartItems.addEventListener('input', event => {
        const noteInput = event.target.closest('[data-kitchen-note]');
        if (!noteInput) return;

        const id = Number(noteInput.dataset.kitchenNote);
        const item = cart.get(id);
        if (!item) return;

        item.kitchen_notes = noteInput.value.slice(0, 500);
        syncHiddenItems();
    });

    // -----------------------------
    // Order / payment listeners
    // -----------------------------
    serviceType.addEventListener('change', syncServiceType);
    paymentArrangement.addEventListener('change', syncPayment);
    paymentMethod.addEventListener('change', syncPayment);

    [discountType, discountValue].forEach(element => {
        element?.addEventListener('input', renderSummary);
        element?.addEventListener('change', renderSummary);
    });

    newOrderBtn.addEventListener('click', () => clearOrder(true));
    tableOrderBtn?.addEventListener('click', () => {
        serviceType.value = 'dine_in';
        syncServiceType();
        tableSelect.focus();
        statusMessage('اختر الطاولة لبدء طلب صالة.');
    });
    openCheckoutBtn.addEventListener('click', () => openCheckout('bill_payment'));
    saveDraftBtn.addEventListener('click', saveLocalDraft);
    billPaymentBtn.addEventListener('click', () => openCheckout('bill_payment'));
    billPrintBtn.addEventListener('click', () => openCheckout('bill_print'));
    kotPrintBtn.addEventListener('click', () => submitOrder('kot_print'));

    checkoutContinueBtn.addEventListener(
        'click',
        async () => {
            if (rbSubmitting) {
                return;
            }

            if (
                !validateOrderBasics()
                || !validatePaymentFields()
            ) {
                return;
            }

            /*
             * لا نغلق Modal قبل رد السيرفر.
             * إذا رجع 422 سيشاهد المستخدم الخطأ داخل نفس نافذة الدفع.
             */
            await submitOrder(
                pendingAction
            );
        }
    );

    checkoutModal.querySelectorAll('[data-close-checkout]').forEach(element => {
        element.addEventListener('click', closeCheckout);
    });

    // -----------------------------
    // Drawer / existing orders
    // -----------------------------
    qrOrdersBtn.addEventListener('click', () => openDrawer('qr'));
    draftListBtn.addEventListener('click', () => openDrawer('draft'));

    existingOrderSearch.addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        openDrawer('all', existingOrderSearch.value);
    });

    existingOrderSearch.addEventListener('focus', () => {
        if (!existingOrderSearch.value) return;
    });

    drawerSearchInput.addEventListener('input', filterServerOrders);
    closeDrawerBtn.addEventListener('click', closeDrawer);
    drawerBackdrop.addEventListener('click', closeDrawer);

    // -----------------------------
    // Keyboard shortcuts useful for cashier operation
    // -----------------------------
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            if (!checkoutModal.hidden) {
                closeCheckout();
                return;
            }
            closeDrawer();
        }

        if (event.key === 'F2') {
            event.preventDefault();
            productSearch.focus();
            productSearch.select();
        }

        if (event.key === 'F4') {
            event.preventDefault();
            saveLocalDraft();
        }

        if (event.key === 'F8') {
            event.preventDefault();
            openCheckout('bill_payment');
        }
    });

    // Keep every POS submission inside the single AJAX flow.
    form.addEventListener('submit', event => {
        event.preventDefault();

        if (rbSubmitting) {
            return;
        }

        if (!validateOrderBasics()) {
            return;
        }

        submitOrder(
            actionInput.value
            || 'bill_payment'
        );
    });

    // Initial render
    renderFilters();
    renderCart();
    syncServiceType();
    syncPayment();
    renderLocalDraftCard();
});
</script>
@endsection
