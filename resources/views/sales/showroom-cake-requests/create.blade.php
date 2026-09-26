@extends('layouts.app')
@section('title', 'طلب كيك معرض جديد')

@section('content')
<div class="page-header">
    <h1 class="page-heading">طلب كيك معرض جديد</h1>
    <p class="page-subheading">
        <a href="{{ route('showroom-cake-requests.index') }}">طلبات المعرض</a> &laquo; جديد
    </p>
</div>

@if($errors->any())
<div class="alert alert-danger scr-errors" role="alert">
    <strong>يرجى تصحيح الأخطاء التالية:</strong>
    <ul>
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('showroom-cake-requests.store') }}" method="POST" id="scrForm">
    @csrf
    <div style="display:grid;gap:1.5rem;max-width:1000px">

        {{-- Header info --}}
        <div class="card">
            <div class="card-header"><span class="card-title">بيانات الطلب</span></div>
            <div class="card-body scr-grid scr-grid-2">
                <div class="form-group">
                    <label class="form-label">المعرض / الفرع *</label>

                    @if($canChooseBranch)
                        <select
                            name="requesting_location_id"
                            class="form-select @error('requesting_location_id') is-invalid @enderror"
                            required
                        >
                            <option value="">اختر الفرع الطالب</option>
                            @foreach($branches as $branchOption)
                                <option
                                    value="{{ $branchOption->id }}"
                                    @selected(old('requesting_location_id') == $branchOption->id)
                                >
                                    {{ $branchOption->name }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input
                            type="text"
                            class="form-input"
                            value="{{ $branch?->name ?? 'غير محدد' }}"
                            disabled
                        >
                        <small class="form-help">سيتم إنشاء الطلب باسم فرعك الحالي.</small>
                    @endif

                    @error('requesting_location_id')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">المصنع *</label>
                    <select
                        name="factory_location_id"
                        class="form-select @error('factory_location_id') is-invalid @enderror"
                        required
                    >
                        <option value="">اختر المصنع</option>
                        @foreach($factories as $factory)
                            <option
                                value="{{ $factory->id }}"
                                @selected(old('factory_location_id') == $factory->id)
                            >
                                {{ $factory->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('factory_location_id')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">تاريخ الحاجة (اختياري)</label>
                    <input type="date"
                           name="needed_by"
                           class="form-input @error('needed_by') is-invalid @enderror"
                           value="{{ old('needed_by') }}">
                    @error('needed_by')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">ملاحظات عامة</label>
                    <textarea name="notes"
                              class="form-textarea @error('notes') is-invalid @enderror"
                              rows="2"
                              placeholder="أي ملاحظة إضافية للمصنع...">{{ old('notes') }}</textarea>
                    @error('notes')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>

        {{-- Items --}}
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <span class="card-title">أصناف الكيك المطلوبة</span>
                <button type="button" class="btn btn-outline btn-sm" id="addItemBtn">+ إضافة صنف</button>
            </div>
            <div class="card-body">
                <div id="itemsContainer">
                    {{-- Dynamic items appended here --}}
                </div>
                @error('items')<span class="form-error">{{ $message }}</span>@enderror
                <p id="noItemsMsg" style="color:var(--text-muted);font-size:.9rem">
                    اضغط "إضافة صنف" لإضافة أول صنف كيك.
                </p>
            </div>
        </div>

        <div style="display:flex;gap:.75rem">
            <button class="btn btn-gold" type="submit">إرسال الطلب</button>
            <a href="{{ route('showroom-cake-requests.index') }}" class="btn btn-ghost">إلغاء</a>
        </div>
    </div>
</form>

{{-- Item template (hidden) --}}
<template id="itemTemplate">
    <div class="scr-item-row scr-grid scr-grid-item" data-index="__IDX__">
        <div class="form-group">
            <label class="form-label">نوع الكيك *</label>
            <select name="items[__IDX__][cake_type]" class="form-select" required>
                <option value="">اختر</option>
                <option value="chocolate">شوكولاتة</option>
                <option value="vanilla">فانيلا</option>
                <option value="red_velvet">ريد فيلفيت</option>
                <option value="caramel">كراميل</option>
                <option value="fruit">فاكهة</option>
                <option value="other">أخرى</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">الحجم</label>
            <select name="items[__IDX__][cake_size]" class="form-select">
                <option value="">—</option>
                <option value="small">صغير</option>
                <option value="medium">وسط</option>
                <option value="large">كبير</option>
                <option value="30x30">30 × 30</option>
                <option value="40x30">40 × 30</option>
                <option value="60x40">60 × 40</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">النكهة</label>
            <input type="text" name="items[__IDX__][flavor]" class="form-input" placeholder="مثال: نوتيلا، فراولة...">
        </div>
        <div class="form-group">
            <label class="form-label">الشكل</label>
            <select name="items[__IDX__][shape]" class="form-select">
                <option value="">—</option>
                <option value="round">دائري</option>
                <option value="slab">بلاطة</option>
                <option value="wedding_tiers">طوابق أفراح</option>
                <option value="standard_tiers">طوابق ستاندر</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">الكمية *</label>
            <input type="number" name="items[__IDX__][quantity]" class="form-input" min="1" value="1" required>
        </div>
        <div class="form-group">
            <label class="form-label">ملاحظة</label>
            <input type="text" name="items[__IDX__][notes]" class="form-input" placeholder="ملاحظة إضافية...">
        </div>
        <div class="form-group scr-remove-col">
            <label class="form-label" style="opacity:0">حذف</label>
            <button type="button" class="btn btn-danger btn-sm remove-item-btn" title="حذف هذا الصنف">×</button>
        </div>
    </div>
</template>

<style>
.scr-grid{display:grid;gap:1rem}
.scr-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.scr-grid-item{grid-template-columns:repeat(3,minmax(0,1fr)) repeat(3,minmax(0,1fr)) auto;align-items:start}
.scr-remove-col{display:flex;flex-direction:column}
.scr-item-row{padding:.75rem;background:var(--off-white);border-radius:var(--radius);margin-bottom:.75rem}
.scr-errors{max-width:1000px;margin-bottom:1.25rem;padding:1rem 1.25rem;border:1px solid #dc3545;border-radius:12px;background:rgba(220,53,69,.12);color:#dc3545}
.scr-errors ul{margin:.65rem 0 0;padding-inline-start:1.25rem}
.form-error{display:block;color:#dc3545;margin-top:.35rem;font-size:.82rem}
@media(max-width:800px){.scr-grid-2,.scr-grid-item{grid-template-columns:1fr}}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let idx = 0;
    const container = document.getElementById('itemsContainer');
    const noMsg     = document.getElementById('noItemsMsg');
    const tpl       = document.getElementById('itemTemplate');

    function addItem() {
        const html = tpl.innerHTML.replaceAll('__IDX__', idx++);
        const div  = document.createElement('div');
        div.innerHTML = html;
        container.appendChild(div.firstElementChild);
        noMsg.style.display = 'none';
        div.firstElementChild.querySelector('.remove-item-btn').addEventListener('click', removeItem);
    }

    function removeItem(e) {
        e.target.closest('.scr-item-row').remove();
        if (!container.querySelector('.scr-item-row')) noMsg.style.display = '';
    }

    document.getElementById('addItemBtn').addEventListener('click', addItem);

    document.getElementById('scrForm').addEventListener('submit', e => {
        if (!container.querySelector('.scr-item-row')) {
            e.preventDefault();
            alert('يجب إضافة صنف كيك واحد على الأقل.');
        }
    });

    // Add first item automatically
    addItem();
});
</script>
@endsection
