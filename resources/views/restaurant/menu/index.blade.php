@extends('layouts.app')

@section('title', 'منيو المطعم')
@section('page-title', 'منيو المطعم')

@section('content')
@php
    $resolveImage = static function (?string $image): ?string {
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
@endphp

<div class="rm-page">
    <div class="rm-header">
        <div>
            <h1>منيو المطعم</h1>
            <p>
                الأصناف التي تظهر في نقطة البيع وقنوات طلب المطعم.
                المنتجات والمخزون يبقون منفصلين عن المنيو.
            </p>
        </div>

        <div class="rm-header-actions">
            @if($locations->count() > 1)
                <form method="GET" action="{{ route('restaurant.menu.index') }}">
                    <select name="location_id" class="rm-control" onchange="this.form.submit()">
                        @foreach($locations as $branch)
                            <option
                                value="{{ $branch->id }}"
                                @selected((int) $branch->id === (int) $location->id)
                            >
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            @can('restaurant_menu.manage')
                <button type="button" class="rm-btn rm-btn-primary" id="rmOpenAdd">
                    + إضافة صنف للمنيو
                </button>
            @endcan

            @can('restaurant_pos.use')
                <a
                    class="rm-btn rm-btn-light"
                    href="{{ route('restaurant.pos.index', ['location_id' => $location->id]) }}"
                >
                    فتح نقطة البيع
                </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="rm-alert rm-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rm-alert rm-alert-error">
            <strong>يرجى مراجعة البيانات:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ route('restaurant.menu.index') }}" class="rm-filters">
        <input type="hidden" name="location_id" value="{{ $location->id }}">

        <div class="rm-search">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m20 20-3.5-3.5"></path>
            </svg>
            <input
                type="search"
                name="q"
                value="{{ request('q') }}"
                placeholder="ابحث باسم صنف المنيو أو SKU..."
            >
        </div>

        <select name="category_id" class="rm-control">
            <option value="">كل الفئات</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>
                    {{ $category->name_ar ?: $category->name }}
                </option>
            @endforeach
        </select>

        <select name="status" class="rm-control">
            <option value="">كل الحالات</option>
            <option value="active" @selected(request('status') === 'active')>فعال في المنيو</option>
            <option value="inactive" @selected(request('status') === 'inactive')>معطل من المنيو</option>
            <option value="available" @selected(request('status') === 'available')>متاح في الفرع</option>
            <option value="unavailable" @selected(request('status') === 'unavailable')>غير متاح في الفرع</option>
        </select>

        <button class="rm-btn rm-btn-primary" type="submit">تصفية</button>

        <a
            class="rm-btn rm-btn-light"
            href="{{ route('restaurant.menu.index', ['location_id' => $location->id]) }}"
        >
            مسح
        </a>
    </form>

    <div class="rm-stats">
        <div>
            <span>الفرع الحالي</span>
            <strong>{{ $location->name }}</strong>
        </div>
        <div>
            <span>أصناف المنيو</span>
            <strong>{{ $menuItems->total() }}</strong>
        </div>
        <div>
            <span>منتجات قابلة للإضافة</span>
            <strong>{{ $availableProducts->count() }}</strong>
        </div>
    </div>

    <div class="rm-grid">
        @forelse($menuItems as $menuItem)
            @php
                $product = $menuItem->product;
                $locationProduct = $product->locationProducts->first();
                $image = $resolveImage($menuItem->effectiveImage());
                $name = $menuItem->displayName();
                $category = $product->category?->name_ar ?: $product->category?->name ?: 'بدون فئة';
                $price = $locationProduct?->local_selling_price ?? $product->base_selling_price;
                $available = (bool) ($locationProduct?->is_available ?? false);
                $inventoryModeLabel = match($menuItem->inventory_mode ?? 'auto') {
                    'recipe' => 'خصم الوصفة',
                    'product' => 'خصم المنتج',
                    default => 'تلقائي',
                };
            @endphp

            <article class="rm-card {{ ! $menuItem->is_active ? 'is-inactive' : '' }}">
                <div class="rm-card-main">
                    <div class="rm-card-image">
                        @if($image)
                            <img src="{{ $image }}" alt="{{ $name }}" loading="lazy">
                        @else
                            <div class="rm-placeholder">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="3" y="3" width="18" height="18" rx="3"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <path d="m21 15-5-5L5 21"></path>
                                </svg>
                            </div>
                        @endif

                        <span class="rm-availability-dot {{ $available ? 'ok' : 'off' }}" title="{{ $available ? 'متاح للبيع' : 'غير متاح للبيع' }}"></span>
                    </div>

                    <div class="rm-card-content">
                        <div class="rm-card-topline">
                            <span class="rm-category-chip">{{ $category }}</span>
                            <strong class="rm-price">₪{{ number_format((float) $price, 2) }}</strong>
                        </div>

                        <h3>{{ $name }}</h3>

                        <div class="rm-state-line">
                            <span class="rm-live-state {{ $available && $menuItem->is_active ? 'on' : 'off' }}">
                                <i></i>
                                {{ $available && $menuItem->is_active ? 'جاهز للبيع' : 'غير متاح للبيع' }}
                            </span>
                            <span class="rm-sku">{{ $product->sku ?: 'بدون SKU' }}</span>
                        </div>

                        <div class="rm-channel-list">
                            <span class="{{ $menuItem->show_in_pos ? 'enabled' : '' }}">
                                <b>POS</b><small>{{ $menuItem->show_in_pos ? 'ظاهر' : 'مخفي' }}</small>
                            </span>
                            <span class="{{ $menuItem->show_in_qr ? 'enabled' : '' }}">
                                <b>QR</b><small>{{ $menuItem->show_in_qr ? 'ظاهر' : 'مخفي' }}</small>
                            </span>
                            <span class="{{ $menuItem->show_in_delivery ? 'enabled' : '' }}">
                                <b>توصيل</b><small>{{ $menuItem->show_in_delivery ? 'ظاهر' : 'مخفي' }}</small>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="rm-card-footer">
                    <div class="rm-card-footer-meta">
                        <span>ترتيب العرض</span>
                        <strong>#{{ $menuItem->sort_order }}</strong>
                    </div>

                    @can('restaurant_menu.manage')
                        <div class="rm-card-actions">
                            <button
                                type="button"
                                class="rm-icon-action rm-edit-button"
                                data-editor="rmEditor{{ $menuItem->id }}"
                            >
                                <svg viewBox="0 0 24 24"><path d="M4 20h4l11-11-4-4L4 16z"></path><path d="m13.5 6.5 4 4"></path></svg>
                                تعديل
                            </button>

                            <form method="POST" action="{{ route('restaurant.menu.toggle-availability', $menuItem) }}">
                                @csrf
                                <input type="hidden" name="location_id" value="{{ $location->id }}">
                                <button type="submit" class="rm-icon-action {{ $available ? 'danger' : 'success' }}">
                                    <svg viewBox="0 0 24 24"><path d="M12 3v9"></path><path d="M7 5.5a8 8 0 1 0 10 0"></path></svg>
                                    {{ $available ? 'إيقاف مؤقت' : 'إتاحة للبيع' }}
                                </button>
                            </form>
                        </div>
                    @endcan
                </div>

                    @can('restaurant_menu.manage')
                        <div class="rm-editor" id="rmEditor{{ $menuItem->id }}" hidden>
                            <form
                                method="POST"
                                action="{{ route('restaurant.menu.update', $menuItem) }}"
                                enctype="multipart/form-data"
                            >
                                @csrf
                                @method('PUT')

                                <input type="hidden" name="location_id" value="{{ $location->id }}">

                                <div class="rm-form-grid">
                                    <div>
                                        <label>اسم العرض بالعربية</label>
                                        <input
                                            class="rm-control"
                                            type="text"
                                            name="display_name_ar"
                                            value="{{ $menuItem->display_name_ar }}"
                                            placeholder="{{ $product->name_ar ?: $product->name }}"
                                        >
                                    </div>

                                    <div>
                                        <label>اسم العرض بالإنجليزية</label>
                                        <input
                                            class="rm-control"
                                            type="text"
                                            name="display_name"
                                            value="{{ $menuItem->display_name }}"
                                            placeholder="{{ $product->name }}"
                                        >
                                    </div>

                                    <div>
                                        <label>سعر البيع في هذا الفرع</label>
                                        <input
                                            class="rm-control"
                                            type="number"
                                            name="selling_price"
                                            step="0.01"
                                            min="0"
                                            required
                                            value="{{ $price }}"
                                        >
                                    </div>

                                    <div>
                                        <label>الترتيب</label>
                                        <input
                                            class="rm-control"
                                            type="number"
                                            name="sort_order"
                                            min="0"
                                            value="{{ $menuItem->sort_order }}"
                                        >
                                    </div>

                                    <div>
                                        <label>آلية خصم المخزون</label>
                                        <select class="rm-control" name="inventory_mode" required>
                                            <option value="auto" @selected(($menuItem->inventory_mode ?? 'auto') === 'auto')>
                                                تلقائي — الوصفة إن وجدت وإلا المنتج
                                            </option>
                                            <option value="recipe" @selected(($menuItem->inventory_mode ?? 'auto') === 'recipe')>
                                                الوصفة — خصم المكونات
                                            </option>
                                            <option value="product" @selected(($menuItem->inventory_mode ?? 'auto') === 'product')>
                                                المنتج — خصم الصنف النهائي
                                            </option>
                                        </select>
                                    </div>

                                    <div class="rm-wide">
                                        <label>صورة المنيو — يفضل PNG/WebP بخلفية شفافة</label>
                                        <input class="rm-control rm-file" type="file" name="image" accept=".png,.webp,.jpg,.jpeg,image/*">
                                    </div>

                                    @if($menuItem->image)
                                        <label class="rm-check rm-wide">
                                            <input type="checkbox" name="remove_image" value="1">
                                            <span>حذف صورة المنيو واستخدام صورة المنتج الأصلية</span>
                                        </label>
                                    @endif

                                    <div class="rm-wide">
                                        <label>الوصف</label>
                                        <textarea class="rm-control rm-textarea" name="description" rows="3">{{ $menuItem->description }}</textarea>
                                    </div>
                                </div>

                                <div class="rm-checks">
                                    <label class="rm-check">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($menuItem->is_active)>
                                        <span>فعال في المنيو</span>
                                    </label>

                                    <label class="rm-check">
                                        <input type="hidden" name="is_available" value="0">
                                        <input type="checkbox" name="is_available" value="1" @checked($available)>
                                        <span>متاح في هذا الفرع</span>
                                    </label>

                                    <label class="rm-check">
                                        <input type="hidden" name="show_in_pos" value="0">
                                        <input type="checkbox" name="show_in_pos" value="1" @checked($menuItem->show_in_pos)>
                                        <span>يظهر في POS</span>
                                    </label>

                                    <label class="rm-check">
                                        <input type="hidden" name="show_in_qr" value="0">
                                        <input type="checkbox" name="show_in_qr" value="1" @checked($menuItem->show_in_qr)>
                                        <span>يظهر في QR</span>
                                    </label>

                                    <label class="rm-check">
                                        <input type="hidden" name="show_in_delivery" value="0">
                                        <input type="checkbox" name="show_in_delivery" value="1" @checked($menuItem->show_in_delivery)>
                                        <span>قنوات التوصيل</span>
                                    </label>
                                </div>

                                <div class="rm-editor-actions">
                                    <button type="submit" class="rm-btn rm-btn-primary">حفظ التعديلات</button>
                                    <button type="button" class="rm-btn rm-btn-light rm-close-editor" data-editor="rmEditor{{ $menuItem->id }}">
                                        إلغاء
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endcan
            </article>
        @empty
            <div class="rm-empty">
                <strong>لا توجد أصناف في منيو المطعم لهذا الفرع.</strong>
                <span>أضف منتجات الكتالوج التي تريد بيعها في المطعم.</span>
            </div>
        @endforelse
    </div>

    @if($menuItems->hasPages())
        <div class="rm-pagination">
            {{ $menuItems->links() }}
        </div>
    @endif
</div>

@can('restaurant_menu.manage')
<div class="rm-modal" id="rmAddModal" hidden>
    <div class="rm-modal-backdrop" data-rm-close></div>

    <div class="rm-modal-dialog">
        <div class="rm-modal-head">
            <div>
                <h2>إضافة صنف للمنيو</h2>
                <p>اختر Product موجودًا بالفعل ثم حدّد كيف سيظهر للعميل والكاشير.</p>
            </div>
            <button type="button" data-rm-close>×</button>
        </div>

        <form
            method="POST"
            action="{{ route('restaurant.menu.store') }}"
            enctype="multipart/form-data"
        >
            @csrf
            <input type="hidden" name="location_id" value="{{ $location->id }}">

            <div class="rm-modal-body">
                <div class="rm-form-grid">
                    <div class="rm-wide">
                        <label>المنتج *</label>
                        <select class="rm-control" name="product_id" id="rmProductSelect" required>
                            <option value="">اختر منتجًا من الكتالوج</option>
                            @foreach($availableProducts as $product)
                                <option
                                    value="{{ $product->id }}"
                                    data-price="{{ $product->getEffectivePriceForLocation($location->id) }}"
                                    data-name-ar="{{ $product->name_ar }}"
                                    data-name="{{ $product->name }}"
                                >
                                    {{ $product->name_ar ?: $product->name }}
                                    {{ $product->sku ? ' — ' . $product->sku : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label>اسم العرض بالعربية</label>
                        <input class="rm-control" name="display_name_ar" id="rmDisplayNameAr" type="text">
                    </div>

                    <div>
                        <label>اسم العرض بالإنجليزية</label>
                        <input class="rm-control" name="display_name" id="rmDisplayName" type="text">
                    </div>

                    <div>
                        <label>سعر البيع في الفرع *</label>
                        <input
                            class="rm-control"
                            name="selling_price"
                            id="rmSellingPrice"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                        >
                    </div>

                    <div>
                        <label>الترتيب</label>
                        <input class="rm-control" name="sort_order" type="number" min="0" value="0">
                    </div>

                    <div>
                        <label>آلية خصم المخزون *</label>
                        <select class="rm-control" name="inventory_mode" required>
                            <option value="auto" selected>تلقائي — الوصفة إن وجدت وإلا المنتج</option>
                            <option value="recipe">الوصفة — خصم المكونات</option>
                            <option value="product">المنتج — خصم الصنف النهائي</option>
                        </select>
                    </div>

                    <div class="rm-wide">
                        <label>صورة المنيو — PNG/WebP شفافة هي الأفضل</label>
                        <input class="rm-control rm-file" name="image" type="file" accept=".png,.webp,.jpg,.jpeg,image/*">
                    </div>

                    <div class="rm-wide">
                        <label>وصف المنيو</label>
                        <textarea class="rm-control rm-textarea" name="description" rows="3"></textarea>
                    </div>
                </div>

                <div class="rm-checks">
                    <label class="rm-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span>فعال في المنيو</span>
                    </label>

                    <label class="rm-check">
                        <input type="hidden" name="is_available" value="0">
                        <input type="checkbox" name="is_available" value="1" checked>
                        <span>متاح في الفرع</span>
                    </label>

                    <label class="rm-check">
                        <input type="hidden" name="show_in_pos" value="0">
                        <input type="checkbox" name="show_in_pos" value="1" checked>
                        <span>يظهر في POS</span>
                    </label>

                    <label class="rm-check">
                        <input type="hidden" name="show_in_qr" value="0">
                        <input type="checkbox" name="show_in_qr" value="1" checked>
                        <span>يظهر في QR</span>
                    </label>

                    <label class="rm-check">
                        <input type="hidden" name="show_in_delivery" value="0">
                        <input type="checkbox" name="show_in_delivery" value="1" checked>
                        <span>قنوات التوصيل</span>
                    </label>
                </div>
            </div>

            <div class="rm-modal-foot">
                <button type="button" class="rm-btn rm-btn-light" data-rm-close>إلغاء</button>
                <button type="submit" class="rm-btn rm-btn-primary">إضافة إلى المنيو</button>
            </div>
        </form>
    </div>
</div>
@endcan

<style>
.rm-page,
.rm-page * {
    box-sizing: border-box;
}

.rm-page,
.rm-modal {
    --rm-accent: var(--theme-accent, var(--gold, #d7a514));
    --rm-primary: var(--theme-primary, #1f2937);
    --rm-surface: var(--theme-surface, #ffffff);
    --rm-bg: var(--theme-bg, #f5f6f8);
    --rm-text: var(--theme-text, #20242c);
    --rm-muted: var(--theme-muted, #747b86);
    --rm-border: var(--theme-border, #e3e6eb);
    --rm-success: var(--theme-success, #159b62);
    --rm-danger: var(--theme-danger, #d64545);

    color: var(--rm-text);
    direction: rtl;
}

.rm-page {
    color: var(--rm-text);
    direction: rtl;
}

.rm-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 16px;
}

.rm-header h1 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 900;
}

.rm-header p {
    margin: 5px 0 0;
    color: var(--rm-muted);
    font-size: .78rem;
}

.rm-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.rm-btn {
    min-height: 39px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 13px;
    border: 1px solid var(--rm-border);
    border-radius: 9px;
    background: var(--rm-surface);
    color: var(--rm-text);
    font: inherit;
    font-size: .72rem;
    font-weight: 850;
    text-decoration: none;
    cursor: pointer;
}

.rm-btn-primary {
    color: #fff;
    background: var(--rm-accent);
    border-color: var(--rm-accent);
}

.rm-btn-light {
    background: var(--rm-surface);
}

.rm-btn-danger-soft {
    color: var(--rm-danger);
    background: color-mix(in srgb, var(--rm-danger) 7%, var(--rm-surface));
    border-color: color-mix(in srgb, var(--rm-danger) 25%, var(--rm-border));
}

.rm-btn-success-soft {
    color: var(--rm-success);
    background: color-mix(in srgb, var(--rm-success) 7%, var(--rm-surface));
    border-color: color-mix(in srgb, var(--rm-success) 25%, var(--rm-border));
}

.rm-alert {
    margin-bottom: 14px;
    padding: 11px 13px;
    border-radius: 10px;
    font-size: .75rem;
}

.rm-alert-success {
    color: #12643f;
    background: #effbf5;
    border: 1px solid #cceedd;
}

.rm-alert-error {
    color: #952c2c;
    background: #fff5f5;
    border: 1px solid #f3cccc;
}

.rm-alert ul {
    margin: 6px 0 0;
}

.rm-filters {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) 190px 190px auto auto;
    gap: 8px;
    margin-bottom: 14px;
    padding: 12px;
    background: var(--rm-surface);
    border: 1px solid var(--rm-border);
    border-radius: 13px;
}

.rm-control {
    width: 100%;
    min-width: 0;
    min-height: 39px;
    padding: 0 10px;
    color: var(--rm-text);
    background: var(--rm-surface);
    border: 1px solid var(--rm-border);
    border-radius: 8px;
    outline: none;
    font: inherit;
    font-size: .72rem;
}

.rm-control:focus {
    border-color: var(--rm-accent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--rm-accent) 10%, transparent);
}

.rm-search {
    min-height: 39px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 10px;
    background: var(--rm-surface);
    border: 1px solid var(--rm-border);
    border-radius: 8px;
}

.rm-search svg {
    width: 16px;
    height: 16px;
    fill: none;
    stroke: var(--rm-muted);
    stroke-width: 1.7;
}

.rm-search input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--rm-text);
    font: inherit;
    font-size: .72rem;
}

.rm-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}

.rm-stats > div {
    padding: 12px 14px;
    background: var(--rm-surface);
    border: 1px solid var(--rm-border);
    border-radius: 12px;
}

.rm-stats span,
.rm-stats strong {
    display: block;
}

.rm-stats span {
    color: var(--rm-muted);
    font-size: .63rem;
}

.rm-stats strong {
    margin-top: 4px;
    font-size: 1rem;
    font-weight: 900;
}

.rm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 14px;
    align-items: start;
}

/* صنف واحد: عرض متوازن بدون تمدد كامل أو فراغ كبير */
.rm-grid:has(> .rm-card:only-child) {
    grid-template-columns: minmax(520px, 720px);
    justify-content: start;
}

.rm-card {
    overflow: hidden;
    min-width: 0;
    background: var(--rm-surface);
    border: 1px solid var(--rm-border);
    border-radius: 16px;
    box-shadow: 0 5px 22px rgba(15, 23, 42, .045);
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
}

.rm-card:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--rm-accent) 34%, var(--rm-border));
    box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
}

.rm-card.is-inactive {
    opacity: .68;
}

.rm-card-main {
    display: grid;
    grid-template-columns: 126px minmax(0, 1fr);
    gap: 13px;
    padding: 13px;
    direction: rtl;
}

.rm-card-image {
    position: relative;
    width: 126px;
    height: 126px;
    display: grid;
    place-items: center;
    overflow: hidden;
    background: color-mix(in srgb, var(--rm-bg) 72%, var(--rm-surface));
    border: 1px solid color-mix(in srgb, var(--rm-border) 74%, transparent);
    border-radius: 14px;
}

.rm-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
}

.rm-placeholder {
    width: 58px;
    height: 58px;
    display: grid;
    place-items: center;
    color: #c7ccd4;
    border: 1px dashed #d5d9df;
    border-radius: 15px;
}

.rm-placeholder svg {
    width: 27px;
    height: 27px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.4;
}

.rm-availability-dot {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 10px;
    height: 10px;
    border: 2px solid var(--rm-surface);
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(15, 23, 42, .16);
}

.rm-availability-dot.ok { background: var(--rm-success); }
.rm-availability-dot.off { background: var(--rm-danger); }

.rm-card-content {
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.rm-card-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.rm-category-chip {
    max-width: 62%;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    padding: 4px 7px;
    color: var(--rm-muted);
    background: var(--rm-bg);
    border-radius: 7px;
    font-size: .56rem;
    font-weight: 800;
}

.rm-price {
    color: var(--rm-accent);
    font-size: .88rem;
    font-weight: 950;
    white-space: nowrap;
    direction: ltr;
}

.rm-card-content h3 {
    margin: 9px 0 0;
    overflow: hidden;
    color: var(--rm-text);
    font-size: .9rem;
    font-weight: 950;
    line-height: 1.45;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.rm-state-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 6px;
}

.rm-live-state {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--rm-muted);
    font-size: .56rem;
    font-weight: 850;
}

.rm-live-state i {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.rm-live-state.on { color: var(--rm-success); }
.rm-live-state.off { color: var(--rm-danger); }

.rm-sku {
    max-width: 48%;
    overflow: hidden;
    color: var(--rm-muted);
    font-size: .52rem;
    white-space: nowrap;
    text-overflow: ellipsis;
    direction: ltr;
}

.rm-channel-list {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 5px;
    margin-top: 12px;
}

.rm-channel-list span {
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1px;
    min-height: 37px;
    color: var(--rm-muted);
    background: var(--rm-bg);
    border: 1px solid transparent;
    border-radius: 9px;
}

.rm-channel-list span.enabled {
    color: var(--rm-accent);
    background: color-mix(in srgb, var(--rm-accent) 8%, var(--rm-surface));
    border-color: color-mix(in srgb, var(--rm-accent) 18%, var(--rm-border));
}

.rm-channel-list b {
    font-size: .58rem;
    font-weight: 950;
}

.rm-channel-list small {
    font-size: .47rem;
    font-weight: 700;
    opacity: .78;
}

.rm-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-height: 50px;
    padding: 8px 12px;
    background: color-mix(in srgb, var(--rm-bg) 50%, var(--rm-surface));
    border-top: 1px solid var(--rm-border);
}

.rm-card-footer-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--rm-muted);
    font-size: .53rem;
}

.rm-card-footer-meta strong {
    color: var(--rm-text);
    font-size: .6rem;
}

.rm-card-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    margin: 0;
}

.rm-card-actions form { margin: 0; }

.rm-icon-action {
    min-height: 31px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 0 9px;
    border: 1px solid var(--rm-border);
    border-radius: 8px;
    background: var(--rm-surface);
    color: var(--rm-text);
    font: inherit;
    font-size: .56rem;
    font-weight: 850;
    cursor: pointer;
    transition: .14s ease;
}

.rm-icon-action:hover {
    border-color: color-mix(in srgb, var(--rm-accent) 38%, var(--rm-border));
    color: var(--rm-accent);
}

.rm-icon-action svg {
    width: 12px;
    height: 12px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.rm-icon-action.danger {
    color: var(--rm-danger);
    background: color-mix(in srgb, var(--rm-danger) 5%, var(--rm-surface));
    border-color: color-mix(in srgb, var(--rm-danger) 18%, var(--rm-border));
}

.rm-icon-action.success {
    color: var(--rm-success);
    background: color-mix(in srgb, var(--rm-success) 5%, var(--rm-surface));
    border-color: color-mix(in srgb, var(--rm-success) 18%, var(--rm-border));
}

.rm-editor {
    margin-top: 10px;
    padding-top: 11px;
    border-top: 1px dashed var(--rm-border);
}

.rm-editor[hidden] {
    display: none;
}

.rm-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px;
}

.rm-form-grid label {
    display: block;
    margin-bottom: 4px;
    color: var(--rm-muted);
    font-size: .6rem;
    font-weight: 800;
}

.rm-wide {
    grid-column: 1 / -1;
}

.rm-textarea {
    height: auto;
    padding: 9px 10px;
    resize: vertical;
}

.rm-file {
    padding-top: 7px;
}

.rm-checks {
    display: flex;
    gap: 9px 14px;
    flex-wrap: wrap;
    margin-top: 11px;
}

.rm-check {
    display: inline-flex !important;
    align-items: center;
    gap: 6px;
    color: var(--rm-text) !important;
    font-size: .62rem !important;
    cursor: pointer;
}

.rm-check input[type="checkbox"] {
    accent-color: var(--rm-accent);
}

.rm-editor-actions {
    display: flex;
    justify-content: flex-end;
    gap: 7px;
    margin-top: 11px;
}

.rm-empty {
    grid-column: 1 / -1;
    min-height: 260px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--rm-muted);
    background: var(--rm-surface);
    border: 1px dashed var(--rm-border);
    border-radius: 14px;
    text-align: center;
}

.rm-empty strong {
    color: var(--rm-text);
    font-size: .85rem;
}

.rm-empty span {
    margin-top: 5px;
    font-size: .65rem;
}

.rm-pagination {
    margin-top: 16px;
}

/* Modal */
.rm-modal {
    position: fixed;
    inset: 0;
    z-index: 2147482500;
    display: grid;
    place-items: center;
    padding: 24px;
    direction: rtl;
    color: var(--rm-text, #20242c);
    isolation: isolate;
}

.rm-modal[hidden] {
    display: none !important;
}

.rm-modal-backdrop {
    position: absolute;
    inset: 0;
    z-index: 0;
    background: rgba(15, 23, 42, .48);
    backdrop-filter: blur(1.5px);
    -webkit-backdrop-filter: blur(1.5px);
}

.rm-modal-dialog {
    position: relative;
    z-index: 2;
    width: min(780px, 96vw);
    max-height: min(88vh, 780px);
    overflow: auto;

    /* Important: this must be fully opaque. */
    background: var(--rm-surface, #ffffff);
    color: var(--rm-text, #20242c);
    opacity: 1;
    border: 1px solid var(--rm-border, #e3e6eb);
    border-radius: 16px;
    box-shadow: 0 28px 90px rgba(15, 23, 42, .30);
}

.rm-modal-head,
.rm-modal-foot {
    position: sticky;
    z-index: 3;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    background: var(--rm-surface, #ffffff);
}

.rm-modal-head {
    top: 0;
    justify-content: space-between;
    border-bottom: 1px solid var(--rm-border, #e3e6eb);
}

.rm-modal-head h2 {
    margin: 0;
    color: var(--rm-text, #20242c);
    font-size: 1.05rem;
    font-weight: 900;
}

.rm-modal-head p {
    margin: 5px 0 0;
    color: var(--rm-muted, #747b86);
    font-size: .75rem;
    line-height: 1.65;
}

.rm-modal-head > button {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    flex: 0 0 36px;
    border: 1px solid var(--rm-border, #e3e6eb);
    border-radius: 9px;
    background: var(--rm-bg, #f5f6f8);
    color: var(--rm-text, #20242c);
    cursor: pointer;
    font-size: 1.2rem;
    font-weight: 700;
}

.rm-modal-body {
    padding: 18px;
    background: var(--rm-surface, #ffffff);
}

.rm-modal .rm-form-grid {
    gap: 14px 12px;
}

.rm-modal .rm-form-grid label {
    margin-bottom: 6px;
    color: var(--rm-text, #20242c);
    font-size: .74rem;
    font-weight: 850;
}

.rm-modal .rm-control {
    min-height: 43px;
    padding-inline: 12px;
    background: var(--rm-surface, #ffffff);
    color: var(--rm-text, #20242c);
    border-color: var(--rm-border, #e3e6eb);
    font-size: .78rem;
}

.rm-modal select.rm-control {
    cursor: pointer;
}

.rm-modal .rm-textarea {
    min-height: 92px;
    padding: 10px 12px;
}

.rm-modal .rm-file {
    padding-top: 8px;
    padding-bottom: 8px;
}

.rm-modal .rm-checks {
    margin-top: 16px;
    padding: 12px;
    gap: 10px 16px;
    background: var(--rm-bg, #f5f6f8);
    border: 1px solid var(--rm-border, #e3e6eb);
    border-radius: 10px;
}

.rm-modal .rm-check {
    color: var(--rm-text, #20242c) !important;
    font-size: .72rem !important;
    font-weight: 750;
}

.rm-modal-foot {
    bottom: 0;
    justify-content: flex-end;
    border-top: 1px solid var(--rm-border, #e3e6eb);
}

.rm-modal-foot .rm-btn {
    min-width: 110px;
}

@media (max-width: 1250px) {
    .rm-grid {
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    }

    .rm-grid:has(> .rm-card:only-child) {
        grid-template-columns: minmax(480px, 680px);
    }
}

@media (max-width: 900px) {
    .rm-header {
        flex-direction: column;
    }

    .rm-filters {
        grid-template-columns: 1fr 1fr;
    }

    .rm-search {
        grid-column: 1 / -1;
    }

    .rm-grid {
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    }

    .rm-grid:has(> .rm-card:only-child) {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 600px) {
    .rm-stats,
    .rm-grid,
    .rm-form-grid,
    .rm-filters {
        grid-template-columns: 1fr;
    }

    .rm-card-main {
        grid-template-columns: 96px minmax(0, 1fr);
        gap: 10px;
        padding: 10px;
    }

    .rm-card-image {
        width: 96px;
        height: 96px;
    }

    .rm-card-footer {
        align-items: stretch;
        flex-direction: column;
    }

    .rm-card-actions,
    .rm-card-actions form,
    .rm-icon-action {
        width: 100%;
    }

    .rm-search,
    .rm-wide {
        grid-column: auto;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const addModal = document.getElementById('rmAddModal');
    const openAdd = document.getElementById('rmOpenAdd');

    /*
     * The modal lives outside .rm-page, and some ERP layouts create their own
     * stacking contexts. Moving it to <body> keeps it above the main layout
     * while .rm-modal now carries the theme variables itself.
     */
    if (addModal && addModal.parentElement !== document.body) {
        document.body.appendChild(addModal);
    }

    function openModal() {
        if (!addModal) return;

        addModal.hidden = false;
        addModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        window.setTimeout(() => {
            addModal.querySelector('select, input, textarea, button')?.focus();
        }, 50);
    }

    function closeModal() {
        if (!addModal) return;

        addModal.hidden = true;
        addModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    openAdd?.addEventListener('click', openModal);

    addModal?.querySelectorAll('[data-rm-close]').forEach(element => {
        element.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && addModal && !addModal.hidden) {
            closeModal();
        }
    });

    document.querySelectorAll('.rm-edit-button').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.dataset.editor;
            const editor = document.getElementById(id);
            if (!editor) return;
            editor.hidden = !editor.hidden;
        });
    });

    document.querySelectorAll('.rm-close-editor').forEach(button => {
        button.addEventListener('click', () => {
            const editor = document.getElementById(button.dataset.editor);
            if (editor) editor.hidden = true;
        });
    });

    const productSelect = document.getElementById('rmProductSelect');
    const displayNameAr = document.getElementById('rmDisplayNameAr');
    const displayName = document.getElementById('rmDisplayName');
    const sellingPrice = document.getElementById('rmSellingPrice');

    productSelect?.addEventListener('change', () => {
        const option = productSelect.options[productSelect.selectedIndex];

        if (!option?.value) {
            if (sellingPrice) sellingPrice.value = '';
            return;
        }

        if (displayNameAr && !displayNameAr.value) {
            displayNameAr.value = option.dataset.nameAr || '';
        }

        if (displayName && !displayName.value) {
            displayName.value = option.dataset.name || '';
        }

        if (sellingPrice) {
            sellingPrice.value = option.dataset.price || '';
        }
    });

    @if($errors->any() && old('product_id'))
        openModal();
    @endif
});
</script>
@endsection
