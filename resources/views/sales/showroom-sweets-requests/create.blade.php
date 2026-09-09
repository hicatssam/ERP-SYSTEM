@extends('layouts.app')
@section('title', 'طلب حلويات جديد للفرع')

@section('content')
<div class="page-header">
    <h1 class="page-heading">طلب حلويات جديد للفرع</h1>
    <p class="page-subheading">
        <a href="{{ route('showroom-sweets-requests.index') }}">طلبات حلويات الفروع</a> &laquo; جديد
    </p>
</div>

@if($errors->any())
    <div class="alert alert-danger ssr-errors" role="alert">
        <strong>يرجى تصحيح الأخطاء التالية:</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('showroom-sweets-requests.store') }}" method="POST" id="ssrForm">
    @csrf

    <div style="display:grid;gap:1.5rem;max-width:1100px">
        <div class="card">
            <div class="card-header">
                <span class="card-title">بيانات الطلب</span>
            </div>
            <div class="card-body ssr-grid ssr-grid-2">
                <div class="form-group">
                    <label class="form-label">الفرع الطالب *</label>

                    @if($canChooseBranch)
                        <select name="requesting_location_id" class="form-select" required>
                            <option value="">اختر الفرع</option>
                            @foreach($branches as $location)
                                <option value="{{ $location->id }}"
                                    {{ (string) old('requesting_location_id') === (string) $location->id ? 'selected' : '' }}>
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <div class="form-input"
                             style="display:flex;align-items:center;min-height:46px;background:#f8fafc;font-weight:700;cursor:not-allowed">
                            {{ $branch?->name ?? 'غير محدد' }}
                        </div>

                        <input type="hidden"
                               name="requesting_location_id"
                               value="{{ $branch?->id }}">

                        <small class="form-help">
                            هذا هو الفرع المرتبط بحسابك، ولا يمكن تغييره من هذه الشاشة.
                        </small>
                    @endif
                </div>

                <div class="form-group">
                    <label class="form-label">المصنع المرسل إليه *</label>
                    <select name="factory_location_id" class="form-select" required>
                        <option value="">اختر المصنع</option>
                        @foreach($factories as $factory)
                            <option value="{{ $factory->id }}"
                                {{ (string) old('factory_location_id', $factories->count() === 1 ? $factory->id : '') === (string) $factory->id ? 'selected' : '' }}>
                                {{ $factory->name }}
                            </option>
                        @endforeach
                    </select>
                    @if($factories->isEmpty())
                        <small class="form-error">لا يوجد مصنع فعال في النظام حاليًا.</small>
                    @endif
                </div>

                <div class="form-group">
                    <label class="form-label">تاريخ الحاجة</label>
                    <input type="date"
                           name="needed_by"
                           class="form-input"
                           value="{{ old('needed_by') }}"
                           min="{{ now()->format('Y-m-d') }}">
                </div>

                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">ملاحظات عامة</label>
                    <textarea name="notes"
                              class="form-textarea"
                              rows="2"
                              placeholder="مثال: الطلب للعرض الصباحي، تجهيز قبل الساعة 9...">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
                <div>
                    <span class="card-title">أصناف الحلويات المطلوبة</span>
                    <div style="font-size:.78rem;color:var(--text-muted);margin-top:.2rem">
                        مثال: نمورة — 2 صدر، بقلاوة — 3 صدر، كنافة — 1 صينية.
                    </div>
                </div>

                <button type="button" class="btn btn-outline btn-sm" id="addItemBtn">
                    + إضافة صنف
                </button>
            </div>

            <div class="card-body">
                <div id="itemsContainer"></div>
                <p id="noItemsMsg" style="color:var(--text-muted);font-size:.9rem;margin:0">
                    اضغط "إضافة صنف" لإضافة أول صنف حلويات.
                </p>
            </div>
        </div>

        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <button class="btn btn-gold" type="submit" {{ $factories->isEmpty() ? 'disabled' : '' }}>
                إرسال الطلب للمصنع
            </button>
            <a href="{{ route('showroom-sweets-requests.index') }}" class="btn btn-ghost">إلغاء</a>
        </div>
    </div>
</form>

<datalist id="requestUnitOptions">
    <option value="صدر"></option>
    <option value="صينية"></option>
    <option value="علبة"></option>
    <option value="كغم"></option>
    <option value="حبة"></option>
    <option value="ربطة"></option>
</datalist>

<template id="itemTemplate">
    <div class="ssr-item-row" data-index="__IDX__">
        <div class="form-group ssr-product-col">
            <label class="form-label">الصنف *</label>
            <select name="items[__IDX__][product_id]" class="form-select product-select" required>
                <option value="">اختر الصنف</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" data-unit="{{ $product->unit }}">
                        {{ $product->name_ar ?? $product->name }}
                        @if($product->category)
                            — {{ $product->category->name_ar ?? $product->category->name }}
                        @endif
                        @if($product->sku)
                            ({{ $product->sku }})
                        @endif
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">الكمية *</label>
            <input type="number"
                   name="items[__IDX__][quantity]"
                   class="form-input quantity-input"
                   min="0.001"
                   step="0.001"
                   value="1"
                   required>
        </div>

        <div class="form-group">
            <label class="form-label">الوحدة *</label>
            <input type="text"
                   name="items[__IDX__][requested_unit]"
                   class="form-input unit-input"
                   list="requestUnitOptions"
                   value="صدر"
                   placeholder="صدر / صينية / كغم..."
                   required>
        </div>

        <div class="form-group ssr-notes-col">
            <label class="form-label">ملاحظة للصنف</label>
            <input type="text"
                   name="items[__IDX__][notes]"
                   class="form-input notes-input"
                   placeholder="مثال: صدر كبير، بدون فستق...">
        </div>

        <div class="form-group ssr-remove-col">
            <label class="form-label" style="opacity:0">حذف</label>
            <button type="button" class="btn btn-danger btn-sm remove-item-btn" title="حذف الصنف">×</button>
        </div>
    </div>
</template>

<style>
.ssr-grid{display:grid;gap:1rem}
.ssr-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.ssr-item-row{
    display:grid;
    grid-template-columns:minmax(260px,2fr) minmax(110px,.7fr) minmax(130px,.8fr) minmax(220px,1.5fr) auto;
    gap:.85rem;
    align-items:start;
    padding:1rem;
    margin-bottom:.85rem;
    background:var(--off-white);
    border:1px solid var(--border);
    border-radius:var(--radius);
}
.ssr-remove-col{display:flex;flex-direction:column}
.ssr-errors{max-width:1100px;margin-bottom:1.25rem;padding:1rem 1.25rem;border:1px solid #dc3545;border-radius:12px;background:rgba(220,53,69,.12);color:#dc3545}
.ssr-errors ul{margin:.65rem 0 0;padding-inline-start:1.25rem}
@media(max-width:900px){
    .ssr-grid-2,.ssr-item-row{grid-template-columns:1fr}
    .ssr-remove-col .form-label{display:none}
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let index = 0;
    const container = document.getElementById('itemsContainer');
    const noItemsMsg = document.getElementById('noItemsMsg');
    const template = document.getElementById('itemTemplate');
    const oldItems = @json(old('items', [['quantity' => 1, 'requested_unit' => 'صدر']]));

    function refreshEmptyState() {
        noItemsMsg.style.display = container.querySelector('.ssr-item-row') ? 'none' : '';
    }

    function addItem(data = {}) {
        const html = template.innerHTML.replaceAll('__IDX__', index++);
        const holder = document.createElement('div');
        holder.innerHTML = html.trim();
        const row = holder.firstElementChild;

        const product = row.querySelector('.product-select');
        const quantity = row.querySelector('.quantity-input');
        const unit = row.querySelector('.unit-input');
        const notes = row.querySelector('.notes-input');

        if (data.product_id) product.value = String(data.product_id);
        quantity.value = data.quantity ?? 1;
        unit.value = data.requested_unit ?? 'صدر';
        notes.value = data.notes ?? '';

        row.querySelector('.remove-item-btn').addEventListener('click', () => {
            row.remove();
            refreshEmptyState();
        });

        product.addEventListener('change', () => {
            const selected = product.options[product.selectedIndex];
            const productUnit = selected?.dataset?.unit;

            if (productUnit && !unit.value.trim()) {
                unit.value = productUnit;
            }
        });

        container.appendChild(row);
        refreshEmptyState();
    }

    document.getElementById('addItemBtn').addEventListener('click', () => addItem());

    document.getElementById('ssrForm').addEventListener('submit', (event) => {
        if (!container.querySelector('.ssr-item-row')) {
            event.preventDefault();
            alert('يجب إضافة صنف حلويات واحد على الأقل.');
        }
    });

    (Array.isArray(oldItems) && oldItems.length ? oldItems : [{}]).forEach(addItem);
});
</script>
@endsection