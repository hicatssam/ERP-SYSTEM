@php
    $editing = isset($product);
    $productTypeValue = old('product_type', $editing ? ($product->product_type?->value ?? 'standard') : 'standard');
    $selectedUnitId = old('unit_id', $editing ? ($product->unit_id ?: optional($units->firstWhere('code', $product->unit))->id) : optional($units->firstWhere('code','piece'))->id);
@endphp

@if($errors->any())
    <div style="margin-bottom:1rem;padding:1rem;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;color:#991b1b">
        <strong>تعذر حفظ المنتج:</strong>
        <ul style="margin:.5rem 0 0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div style="display:grid;gap:1rem">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <div class="form-group"><label class="form-label">الاسم (إنجليزي) *</label><input name="name" class="form-input" value="{{ old('name', $product->name ?? '') }}" required></div>
        <div class="form-group"><label class="form-label">الاسم (عربي)</label><input name="name_ar" class="form-input" value="{{ old('name_ar', $product->name_ar ?? '') }}"></div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem">
        <div class="form-group"><label class="form-label">الفئة *</label><select name="category_id" class="form-select" required><option value="">اختر فئة</option>@foreach($categories as $cat)<option value="{{ $cat->id }}" @selected((string)old('category_id',$product->category_id ?? '') === (string)$cat->id)>{{ $cat->name_ar ?? $cat->name }}</option>@endforeach</select></div>
        <div class="form-group"><label class="form-label">وحدة القياس *</label><select name="unit_id" class="form-select" required>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string)$selectedUnitId === (string)$unit->id)>{{ $unit->displayName() }} @if($unit->symbol)({{ $unit->symbol }})@endif</option>@endforeach</select></div>
        <div class="form-group"><label class="form-label">نوع المنتج *</label><select name="product_type" class="form-select" required><option value="standard" @selected($productTypeValue==='standard')>منتج عادي</option>@if($variantsEnabled)<option value="variant" @selected($productTypeValue==='variant')>منتج بمتغيرات</option>@endif</select>@if(!$variantsEnabled)<small style="color:var(--text-muted)">فعّل «متغيرات المنتجات» لإضافة المقاسات والألوان.</small>@endif</div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        @if($brands->isNotEmpty())
            <div class="form-group"><label class="form-label">العلامة التجارية</label><select name="brand_id" class="form-select"><option value="">بدون علامة</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string)old('brand_id',$product->brand_id ?? '') === (string)$brand->id)>{{ $brand->displayName() }}</option>@endforeach</select></div>
        @else
            <div class="form-group"><label class="form-label">العلامة التجارية</label><input class="form-input" value="وحدة العلامات التجارية غير مفعلة" disabled></div>
        @endif
        <div class="form-group"><label class="form-label">السعر الأساسي (₪) *</label><input name="base_selling_price" type="number" min="0" step="0.01" class="form-input" value="{{ old('base_selling_price',$product->base_selling_price ?? '') }}" required></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <div class="form-group"><label class="form-label">SKU</label><input class="form-input" value="{{ $editing ? $product->sku : 'سيُنشأ تلقائيًا عند الحفظ' }}" disabled dir="ltr"></div>
        <div class="form-group"><label class="form-label">الباركود EAN-13</label><input class="form-input" value="{{ $editing ? $product->barcode : 'سيُنشأ تلقائيًا عند الحفظ' }}" disabled dir="ltr"></div>
    </div>

  @php
    $selectedLocationIds = collect(
        old(
            'location_ids',
            $editing
                ? $product->locationProducts
                    ->where('is_available', true)
                    ->pluck('location_id')
                    ->all()
                : []
        )
    )
    ->map(fn ($id) => (string) $id)
    ->unique()
    ->values()
    ->all();
@endphp

<div class="form-group">
    <label class="form-label">الفروع / المصنع *</label>

    @if($canManageLocations)

        <div style="
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:.75rem;
        ">
           @foreach($locations as $location)
    <label style="
        display:flex;
        align-items:center;
        gap:.7rem;
        padding:1rem;
        background:var(--surface);
        border:1px solid var(--border);
        border-radius:10px;
        cursor:pointer;
    ">
        <input
            type="checkbox"
            name="location_ids[]"
            value="{{ $location->id }}"
            @checked(
                in_array(
                    (string) $location->id,
                    $selectedLocationIds,
                    true
                )
            )
        >

        <span>
            {{ $location->name_ar ?: $location->name }}

            <small style="color:var(--text-muted)">
                {{ $location->type === 'factory' ? ' — مصنع' : ' — فرع' }}
            </small>
        </span>
    </label>
@endforeach
        </div>

    @else

        <input
            class="form-input"
            value="{{ $managedLocation?->name_ar ?: ($managedLocation?->name ?? '—') }}"
            disabled
        >

        @if($managedLocation)
            <input
                type="hidden"
                name="location_ids[]"
                value="{{ $managedLocation->id }}"
            >
        @endif

    @endif
</div>

    <div style="display:flex;gap:1.25rem;flex-wrap:wrap;padding:.8rem 1rem;background:var(--surface);border:1px solid var(--border);border-radius:10px">
        <label style="display:flex;gap:.5rem;align-items:center"><input type="checkbox" name="tracks_batch" value="1" @checked(old('tracks_batch',$product->tracks_batch ?? false))> تتبع الدُفعات</label>
        <label style="display:flex;gap:.5rem;align-items:center"><input type="checkbox" name="tracks_expiry" value="1" @checked(old('tracks_expiry',$product->tracks_expiry ?? false))> تتبع تاريخ الصلاحية</label>
        <small style="color:var(--text-muted)">تتبع الصلاحية يفعّل تتبع الدفعات تلقائيًا.</small>
    </div>

    <div class="form-group"><label class="form-label">الوصف</label><textarea name="description" class="form-textarea">{{ old('description',$product->description ?? '') }}</textarea></div>

    <div class="form-group"><label class="form-label">صورة المنتج</label><input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/webp">@if($editing && $product->image)<label style="display:flex;gap:.5rem;align-items:center;margin-top:.5rem"><input type="checkbox" name="remove_image" value="1"> حذف الصورة الحالية</label>@endif</div>
</div>
