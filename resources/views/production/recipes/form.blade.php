@extends('layouts.app')

@section('title', $recipe ? 'تعديل الوصفة' : 'إضافة وصفة')
@section('page-title', $recipe ? 'تعديل الوصفة' : 'إضافة وصفة')

@section('content')
@php
    $rows = old('items');

    if ($rows === null) {
        $rows = $recipe
            ? $recipe->items->map(fn($item) => [
                'ingredient_product_id' => $item->ingredient_product_id,
                'quantity' => $item->quantity,
                'expected_waste_percent' => $item->expected_waste_percent,
                'stage' => $item->stage,
                'notes' => $item->notes,
            ])->values()->all()
            : [[
                'ingredient_product_id' => '',
                'quantity' => '',
                'expected_waste_percent' => 0,
                'stage' => '',
                'notes' => '',
            ]];
    }
@endphp

<div class="page-header">
    <div>
        <h1 class="page-heading">{{ $recipe ? 'تعديل مسودة الوصفة' : 'إضافة وصفة جديدة' }}</h1>
        <p class="page-subheading">الكميات تُسجل بوحدة مخزون كل مكون. لا يوجد تحويل وحدات ضمني.</p>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <ul style="margin:0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST"
      action="{{ $recipe ? route('production.recipes.update', $recipe) : route('production.recipes.store') }}">
    @csrf
    @if($recipe) @method('PUT') @endif

    <div class="card" style="margin-bottom:1rem">
        <div class="card-header"><span class="card-title">بيانات الوصفة</span></div>
        <div class="card-body">
            <div class="form-grid">
                @if(!$recipe)
                <div class="form-group">
                    <label class="form-label">المنتج الناتج *</label>
                    <select name="product_id" id="recipeProductSelect" class="form-input" required>
                        <option value="">اختر المنتج</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected((string)old('product_id') === (string)$product->id)>
                                {{ $product->name_ar ?: $product->name }}
                                — {{ $product->unitDefinition?->symbol ?: $product->unit }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">المتغير / الحجم</label>
                    <select name="product_variant_id" id="recipeVariantSelect" class="form-input">
                        <option value="">الوصفة الأساسية للمنتج</option>
                        @foreach($products as $product)
                            @foreach($product->activeVariants as $variant)
                                <option
                                    value="{{ $variant->id }}"
                                    data-product-id="{{ $product->id }}"
                                    @selected((string)old('product_variant_id') === (string)$variant->id)
                                >
                                    {{ $product->name_ar ?: $product->name }} — {{ $variant->displayName() }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    <small>اتركه فارغًا للوصفة الأساسية، أو اختر حجمًا لو كانت الكمية تختلف حسب الحجم.</small>
                </div>
                @else
                <div class="form-group">
                    <label class="form-label">المنتج الناتج</label>
                    <input class="form-input" value="{{ $recipe->product?->name_ar ?: $recipe->product?->name }}" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">المتغير / الحجم</label>
                    <input class="form-input" value="{{ $recipe->productVariant?->displayName() ?: 'الوصفة الأساسية للمنتج' }}" disabled>
                </div>
                @endif

                <div class="form-group">
                    <label class="form-label">اسم الوصفة *</label>
                    <input name="name" class="form-input" required value="{{ old('name', $recipe?->name) }}">
                </div>

                <div class="form-group">
                    <label class="form-label">ناتج الوصفة *</label>
                    <input type="number" step="0.001" min="0.001" name="yield_quantity" class="form-input" required
                           value="{{ old('yield_quantity', $recipe?->yield_quantity ?? 1) }}">
                    <small>مثال: إذا الوصفة تنتج 24 قطعة اكتب 24.</small>
                </div>
            </div>

            <div class="form-group" style="margin-top:1rem">
                <label class="form-label">ملاحظات</label>
                <textarea class="form-input" name="notes" rows="3">{{ old('notes', $recipe?->notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span class="card-title">المكونات</span>
            <button type="button" class="btn btn-outline btn-sm" id="addIngredient">+ إضافة مكون</button>
        </div>
        <div class="card-body">
            <div id="ingredientRows">
                @foreach($rows as $i => $row)
                    <div class="recipe-row" data-row>
                        <div>
                            <label class="form-label">المكون *</label>
                            <select class="form-input" name="items[{{ $i }}][ingredient_product_id]" required>
                                <option value="">اختر</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}"
                                            @selected((string)($row['ingredient_product_id'] ?? '') === (string)$product->id)>
                                        {{ $product->name_ar ?: $product->name }}
                                        — {{ $product->unitDefinition?->symbol ?: $product->unit }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">الكمية الصافية *</label>
                            <input class="form-input" type="number" min="0.001" step="0.001"
                                   name="items[{{ $i }}][quantity]" required value="{{ $row['quantity'] ?? '' }}">
                        </div>
                        <div>
                            <label class="form-label">هدر متوقع %</label>
                            <input class="form-input" type="number" min="0" max="100" step="0.001"
                                   name="items[{{ $i }}][expected_waste_percent]"
                                   value="{{ $row['expected_waste_percent'] ?? 0 }}">
                        </div>
                        <div>
                            <label class="form-label">المرحلة</label>
                            <input class="form-input" name="items[{{ $i }}][stage]" value="{{ $row['stage'] ?? '' }}"
                                   placeholder="خلط / خبز / تزيين">
                        </div>
                        <div>
                            <label class="form-label">ملاحظات</label>
                            <input class="form-input" name="items[{{ $i }}][notes]" value="{{ $row['notes'] ?? '' }}">
                        </div>
                        <div style="align-self:end">
                            <button type="button" class="btn btn-ghost btn-sm" data-remove>حذف</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer" style="display:flex;justify-content:flex-end;gap:.75rem">
            <a class="btn btn-ghost" href="{{ route('production.recipes.index') }}">إلغاء</a>
            <button class="btn btn-gold">حفظ المسودة</button>
        </div>
    </div>
</form>

<template id="ingredientTemplate">
    <div class="recipe-row" data-row>
        <div>
            <label class="form-label">المكون *</label>
            <select class="form-input" data-name="ingredient_product_id" required>
                <option value="">اختر</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}">
                        {{ $product->name_ar ?: $product->name }} — {{ $product->unitDefinition?->symbol ?: $product->unit }}
                    </option>
                @endforeach
            </select>
        </div>
        <div><label class="form-label">الكمية الصافية *</label><input class="form-input" type="number" min="0.001" step="0.001" data-name="quantity" required></div>
        <div><label class="form-label">هدر متوقع %</label><input class="form-input" type="number" min="0" max="100" step="0.001" data-name="expected_waste_percent" value="0"></div>
        <div><label class="form-label">المرحلة</label><input class="form-input" data-name="stage"></div>
        <div><label class="form-label">ملاحظات</label><input class="form-input" data-name="notes"></div>
        <div style="align-self:end"><button type="button" class="btn btn-ghost btn-sm" data-remove>حذف</button></div>
    </div>
</template>
@endsection

@push('styles')
<style>
.recipe-row{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1.5fr auto;gap:.65rem;padding:.85rem 0;border-bottom:1px solid var(--border)}
@media(max-width:1000px){.recipe-row{grid-template-columns:1fr 1fr}.recipe-row>div:first-child{grid-column:1/-1}}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('ingredientRows');
    const tpl = document.getElementById('ingredientTemplate');
    const add = document.getElementById('addIngredient');
    const productSelect = document.getElementById('recipeProductSelect');
    const variantSelect = document.getElementById('recipeVariantSelect');

    const syncVariants = () => {
        if (!productSelect || !variantSelect) return;

        const productId = String(productSelect.value || '');
        let selectedStillVisible = !variantSelect.value;

        [...variantSelect.options].forEach(option => {
            if (!option.dataset.productId) {
                option.hidden = false;
                return;
            }

            const visible = option.dataset.productId === productId;
            option.hidden = !visible;
            option.disabled = !visible;

            if (visible && option.value === variantSelect.value) {
                selectedStillVisible = true;
            }
        });

        if (!selectedStillVisible) {
            variantSelect.value = '';
        }
    };

    productSelect?.addEventListener('change', syncVariants);
    syncVariants();

    const reindex = () => {
        [...box.querySelectorAll('[data-row]')].forEach((row, index) => {
            row.querySelectorAll('[name], [data-name]').forEach(el => {
                const key = el.dataset.name || (el.name.match(/\]\[([^\]]+)\]$/)?.[1]);
                if (key) el.name = `items[${index}][${key}]`;
            });
        });
    };

    add.addEventListener('click', () => {
        box.appendChild(tpl.content.cloneNode(true));
        reindex();
    });

    box.addEventListener('click', event => {
        const btn = event.target.closest('[data-remove]');
        if (!btn) return;
        if (box.querySelectorAll('[data-row]').length <= 1) return;
        btn.closest('[data-row]').remove();
        reindex();
    });

    reindex();
});
</script>
@endpush
