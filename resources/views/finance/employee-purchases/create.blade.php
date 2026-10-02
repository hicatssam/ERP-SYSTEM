@extends('layouts.app')
@section('title', 'صرف منتجات لموظف')
@section('page-title', 'صرف منتجات لموظف')

@section('content')
<div class="page-header"><div><h1 class="page-heading">صرف منتجات لموظف</h1><p class="page-subheading">تُثبت الأسعار الحالية ويُخصم المخزون عند الحفظ. الأقساط لا تخصم من الراتب تلقائيًا.</p></div>
    <a href="{{ route('accounting.employee-purchases.index') }}" class="btn btn-outline">حسابات الموظفين</a>
</div>

<form action="{{ route('accounting.employee-purchases.store') }}" method="POST" class="card" id="employeePurchaseForm">@csrf
    <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="card-header"><span class="card-title">بيانات العملية</span></div><div class="card-body">
        <div class="form-grid">
            <div class="form-group"><label class="form-label">الفرع *</label><select name="location_id" id="purchaseLocation" class="form-input" required>
                <option value="">اختر الفرع</option>
                @foreach($locations as $location)<option value="{{ $location->id }}" @selected((int) old('location_id', $locationId) === $location->id)>{{ $location->name }}</option>@endforeach
            </select></div>
            <div class="form-group" style="position:relative"><label class="form-label">الموظف * (ابحث بالاسم أو الرقم)</label>
                <input id="employeeSearch" class="form-input" autocomplete="off" placeholder="اكتب حرفين للبحث" value="{{ old('employee_name') }}">
                <input type="hidden" name="employee_id" id="employeeId" value="{{ old('employee_id') }}"><div id="employeeResults" class="card" style="display:none;position:absolute;z-index:20;max-height:220px;overflow:auto;left:0;right:0"></div>
            </div>
            <div class="form-group"><label class="form-label">طريقة السداد *</label><select name="payment_plan" id="paymentPlan" class="form-input"><option value="account">على الحساب (موعد واحد)</option><option value="installments" @selected(old('payment_plan') === 'installments')>أقساط شهرية</option></select></div>
            <div class="form-group" id="countGroup" style="display:none"><label class="form-label">عدد الأقساط</label><input name="installment_count" id="installmentCount" type="number" min="2" max="24" value="{{ old('installment_count', 2) }}" class="form-input"></div>
            <div class="form-group"><label class="form-label">أول موعد استحقاق *</label><input type="date" name="first_due_date" min="{{ now()->toDateString() }}" value="{{ old('first_due_date', now()->addMonthNoOverflow()->toDateString()) }}" class="form-input" required></div>
            <div class="form-group"><label class="form-label">العملة الأساسية</label><div class="form-input">{{ $currency?->displayName() ?? 'اضبط العملة الأساسية في الإعدادات' }}</div></div>
        </div>
        <div class="form-group"><label class="form-label">ملاحظات</label><textarea name="notes" class="form-input" maxlength="1000">{{ old('notes') }}</textarea></div>
        <h3 style="margin:1rem 0 .5rem">المنتجات</h3>
        <p class="page-subheading">ابحث عن منتجات الفرع المتاحة، وحدد الكمية. السعر يُقرأ من النظام عند حفظ العملية.</p>
        <div class="table-wrap"><table class="data-table" style="width:100%"><thead><tr><th>المنتج *</th><th>الكمية *</th><th>السعر الحالي</th><th>المتاح</th><th></th></tr></thead><tbody id="purchaseItems"></tbody></table></div>
        <button type="button" id="addPurchaseItem" class="btn btn-outline btn-sm" style="margin:.75rem 0">إضافة منتج</button>
        <div style="margin-top:1rem"><button class="btn btn-primary" @disabled(! $currency)>تسجيل الصرف على حساب الموظف</button></div>
    </div>
</form>

<template id="purchaseItemTemplate"><tr>
    <td style="position:relative;min-width:240px"><input class="form-input product-search" autocomplete="off" placeholder="ابحث بالاسم أو الرمز" aria-label="بحث المنتج"><input type="hidden" class="product-id"><div class="product-results card" style="display:none;position:absolute;z-index:15;left:0;right:0;max-height:180px;overflow:auto"></div></td>
    <td><input type="number" class="form-input item-quantity" step="0.001" min="0.001" value="1" required style="max-width:115px"></td>
    <td class="item-price">—</td><td class="item-stock">—</td><td><button type="button" class="btn btn-outline btn-sm remove-item">حذف</button></td>
</tr></template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const location = document.getElementById('purchaseLocation');
    const employeeInput = document.getElementById('employeeSearch');
    const employeeId = document.getElementById('employeeId');
    const employeeResults = document.getElementById('employeeResults');
    const rows = document.getElementById('purchaseItems');
    const plan = document.getElementById('paymentPlan');
    const count = document.getElementById('installmentCount');
    const group = document.getElementById('countGroup');
    const currency = @json($currency?->code);
    const endpoints = { employees: @json(route('accounting.employee-purchases.employees')), products: @json(route('accounting.employee-purchases.products')) };
    let timer;
    function updatePlan() { group.style.display = plan.value === 'installments' ? '' : 'none'; count.required = plan.value === 'installments'; }
    plan.addEventListener('change', updatePlan); updatePlan();
    function options(box, items, format, choose) {
        box.replaceChildren();
        items.forEach(item => {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'btn btn-outline btn-full';
            button.style.cssText = 'text-align:start;display:block;margin:.15rem 0';
            button.textContent = format(item);
            button.addEventListener('click', () => { choose(item); box.style.display = 'none'; });
            box.appendChild(button);
        });
        if (!items.length) box.textContent = 'لا توجد نتائج متاحة.';
        box.style.display = 'block';
    }
    function search(kind, input, box, format, choose) {
        clearTimeout(timer);
        if (!location.value || input.value.trim().length < 2) { box.style.display = 'none'; return; }
        const query = input.value.trim(), selectedLocation = location.value;
        timer = setTimeout(async () => {
            try {
                const url = new URL(endpoints[kind], window.location.origin);
                url.searchParams.set('q', query); url.searchParams.set('location_id', selectedLocation);
                const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                if (!response.ok || input.value.trim() !== query || location.value !== selectedLocation) return;
                options(box, await response.json(), format, choose);
            } catch (_) { box.style.display = 'none'; }
        }, 250);
    }
    employeeInput.addEventListener('input', () => {
        employeeId.value = '';
        search('employees', employeeInput, employeeResults, item => `${item.full_name} · ${item.employee_number}`, item => {
            employeeInput.value = `${item.full_name} · ${item.employee_number}`;
            employeeId.value = item.id;
        });
    });
    function addItem() {
        if (rows.children.length >= 10) return;
        const row = document.getElementById('purchaseItemTemplate').content.firstElementChild.cloneNode(true);
        rows.appendChild(row);
        const productInput = row.querySelector('.product-search');
        const productId = row.querySelector('.product-id');
        const box = row.querySelector('.product-results');
        productInput.addEventListener('input', () => {
            productId.value = ''; row.querySelector('.item-price').textContent = '—';
            search('products', productInput, box, item => `${item.name} · ${item.sku || ''} · ${item.price} ${currency} · المتاح ${item.available}`, item => {
                productInput.value = item.name; productId.value = item.id;
                row.querySelector('.item-price').textContent = `${item.price} ${currency}`;
                row.querySelector('.item-stock').textContent = item.available;
            });
        });
        row.querySelector('.remove-item').addEventListener('click', () => { if (rows.children.length > 1) { row.remove(); renumber(); } });
        renumber();
    }
    function renumber() {
        [...rows.children].forEach((row, i) => {
            row.querySelector('.product-id').name = `items[${i}][product_id]`;
            row.querySelector('.item-quantity').name = `items[${i}][quantity]`;
        });
    }
    location.addEventListener('change', () => {
        employeeId.value = ''; employeeInput.value = ''; employeeResults.style.display = 'none';
        rows.replaceChildren(); addItem();
    });
    document.getElementById('addPurchaseItem').addEventListener('click', addItem);
    document.getElementById('employeePurchaseForm').addEventListener('submit', event => {
        if (!employeeId.value || [...rows.children].some(row => !row.querySelector('.product-id').value)) {
            event.preventDefault(); alert('اختر الموظف والمنتجات من نتائج البحث أولًا.');
        }
    });
    addItem();
});
</script>
@endpush
