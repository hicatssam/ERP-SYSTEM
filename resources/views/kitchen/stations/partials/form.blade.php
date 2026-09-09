@php
    $prefix = $formPrefix ?? 'station';
    $stationId = $station?->id;
@endphp

<div class="station-form-grid">
    <div class="form-group">
        <label class="form-label">اسم المحطة *</label>
        <input
            type="text"
            name="name"
            class="form-input"
            value="{{ old('name', $station?->name) }}"
            placeholder="مثال: الشواية"
            required
        >
    </div>

    <div class="form-group">
        <label class="form-label">الكود *</label>
        <input
            type="text"
            name="code"
            class="form-input"
            value="{{ old('code', $station?->code) }}"
            placeholder="GRILL"
            maxlength="60"
            required
        >
        <span class="form-hint">إنجليزي/أرقام و - أو _ فقط.</span>
    </div>

    <div class="form-group">
        <label class="form-label">هدف التحضير بالدقائق *</label>
        <input
            type="number"
            name="target_minutes"
            class="form-input"
            min="1"
            max="240"
            value="{{ old('target_minutes', $station?->target_minutes ?? 15) }}"
            required
        >
    </div>

    <div class="form-group">
        <label class="form-label">الترتيب</label>
        <input
            type="number"
            name="sort_order"
            class="form-input"
            min="0"
            max="9999"
            value="{{ old('sort_order', $station?->sort_order ?? 10) }}"
        >
    </div>

    <div class="form-group span-2">
        <label class="form-label">الوصف</label>
        <textarea name="description" class="form-textarea" rows="2">{{ old('description', $station?->description) }}</textarea>
    </div>

    <div class="form-group span-2">
        <div class="boolean-row">
            <label>
                <input type="hidden" name="is_active" value="0">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked(old('is_active', $station?->is_active ?? true))
                >
                محطة فعالة
            </label>

            <label>
                <input type="hidden" name="is_default" value="0">
                <input
                    type="checkbox"
                    name="is_default"
                    value="1"
                    @checked(old('is_default', $station?->is_default ?? false))
                >
                المحطة الافتراضية
            </label>
        </div>
    </div>

    <div class="form-group span-2 route-picker">
        <div class="route-picker-head">
            <strong>توجيه الفئات لهذه المحطة</strong>
            <span class="badge badge-grey">{{ count($selectedCategoryIds ?? []) }} محددة</span>
        </div>

        <input
            type="search"
            class="form-input route-search"
            placeholder="ابحث عن فئة..."
            data-route-search="{{ $prefix }}-categories"
        >

        <div class="route-options" id="{{ $prefix }}-categories">
            @forelse($categories as $category)
                <label class="route-option">
                    <input
                        type="checkbox"
                        name="category_ids[]"
                        value="{{ $category->id }}"
                        @checked(in_array((int) $category->id, $selectedCategoryIds ?? [], true))
                    >
                    <span>{{ $category->name_ar ?: $category->name }}</span>
                </label>
            @empty
                <span class="form-hint">لا توجد فئات فعالة.</span>
            @endforelse
        </div>
    </div>

    <div class="form-group span-2 route-picker">
        <div class="route-picker-head">
            <strong>توجيه منتجات محددة</strong>
            <span class="badge badge-grey">{{ count($selectedProductIds ?? []) }} محددة</span>
        </div>

        <input
            type="search"
            class="form-input route-search"
            placeholder="ابحث عن منتج..."
            data-route-search="{{ $prefix }}-products"
        >

        <div class="route-options" id="{{ $prefix }}-products">
            @forelse($products as $product)
                <label class="route-option">
                    <input
                        type="checkbox"
                        name="product_ids[]"
                        value="{{ $product->id }}"
                        @checked(in_array((int) $product->id, $selectedProductIds ?? [], true))
                    >
                    <span>
                        {{ $product->name_ar ?: $product->name }}
                        @if($product->category)
                            <small>— {{ $product->category->name_ar ?: $product->category->name }}</small>
                        @endif
                    </span>
                </label>
            @empty
                <span class="form-hint">لا توجد منتجات فعالة متاحة في هذا الفرع.</span>
            @endforelse
        </div>
    </div>
</div>
