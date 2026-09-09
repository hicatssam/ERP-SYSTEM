@php
    $supplier = $supplier ?? null;
    $contacts = old(
        'contacts',
        $supplier?->contacts
            ?->map(
                fn($contact) => $contact->only([
                    'name',
                    'position',
                    'phone',
                    'whatsapp',
                    'email',
                    'is_primary',
                    'notes',
                ]),
            )
            ->all() ?? [[]],
    );
    $supplierProducts = old(
        'products',
        $supplier?->supplierProducts
            ?->map(
                fn($row) => $row->only([
                    'product_id',
                    'supplier_sku',
                    'supplier_product_name',
                    'purchase_price',
                    'currency_id',
                    'purchase_unit_id',
                    'conversion_factor',
                    'package_description',
                    'minimum_order_quantity',
                    'lead_time_days',
                    'is_preferred',
                    'is_active',
                    'notes',
                ]),
            )
            ->all() ?? [[]],
    );
@endphp

<div style="display:grid;gap:1.25rem">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
        @if (!$supplier)
            <div class="form-group"><label class="form-label">كود المورد (اختياري)</label><input class="form-input"
                    name="supplier_code" value="{{ old('supplier_code') }}" placeholder="يولّد تلقائياً"></div>
        @else
            <div class="form-group"><label class="form-label">كود المورد</label><input class="form-input"
                    value="{{ $supplier->supplier_code }}" disabled></div>
        @endif
        <div class="form-group"><label class="form-label">اسم المورد *</label><input class="form-input" name="name"
                required value="{{ old('name', $supplier?->name) }}"></div>
        <div class="form-group"><label class="form-label">اسم الشركة</label><input class="form-input"
                name="company_name" value="{{ old('company_name', $supplier?->company_name) }}"></div>
        <div class="form-group"><label class="form-label">مسؤول التواصل</label><input class="form-input"
                name="contact_person" value="{{ old('contact_person', $supplier?->contact_person) }}"></div>
        <div class="form-group"><label class="form-label">الهاتف</label><input class="form-input" name="phone"
                value="{{ old('phone', $supplier?->phone) }}"></div>
        <div class="form-group"><label class="form-label">واتساب</label><input class="form-input" name="whatsapp"
                value="{{ old('whatsapp', $supplier?->whatsapp) }}"></div>
        <div class="form-group"><label class="form-label">البريد الإلكتروني</label><input class="form-input"
                type="email" name="email" value="{{ old('email', $supplier?->email) }}"></div>
        <div class="form-group"><label class="form-label">العملة الافتراضية *</label><select class="form-input"
                name="currency_id" required>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->id }}" @selected((string) old('currency_id', $supplier?->currency_id) === (string) $currency->id)>{{ $currency->displayName() }}
                        ({{ $currency->code }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group"><label class="form-label">شروط الدفع</label><input class="form-input"
                name="payment_terms" value="{{ old('payment_terms', $supplier?->payment_terms) }}"
                placeholder="مثال: 30 يوم"></div>
        <div class="form-group"><label class="form-label">حد الائتمان</label><input class="form-input" type="number"
                min="0" step="0.01" name="credit_limit"
                value="{{ old('credit_limit', $supplier?->credit_limit ?? 0) }}"></div>
        <div class="form-group"><label class="form-label">الرصيد الافتتاحي</label><input class="form-input"
                type="number" step="0.01" name="opening_balance"
                value="{{ old('opening_balance', $supplier?->opening_balance ?? 0) }}"></div>
        <div class="form-group"><label class="form-label">الحالة</label><select class="form-input" name="status">
                <option value="active" @selected(old('status', $supplier?->statusValue() ?? 'active') === 'active')>نشط</option>
                <option value="inactive" @selected(old('status', $supplier?->statusValue()) === 'inactive')>غير نشط</option>
                <option value="blocked" @selected(old('status', $supplier?->statusValue()) === 'blocked')>موقوف</option>
            </select></div>
        <div class="form-group"><label class="form-label">المدينة</label><input class="form-input" name="city"
                value="{{ old('city', $supplier?->city) }}"></div>
        <div class="form-group"><label class="form-label">الدولة</label><input class="form-input" name="country"
                value="{{ old('country', $supplier?->country) }}"></div>
        <div class="form-group"><label class="form-label">الرقم الضريبي</label><input class="form-input"
                name="tax_number" value="{{ old('tax_number', $supplier?->tax_number) }}"></div>
        <div class="form-group"><label class="form-label">السجل التجاري</label><input class="form-input"
                name="commercial_registration"
                value="{{ old('commercial_registration', $supplier?->commercial_registration) }}"></div>
    </div>
    <div class="form-group"><label class="form-label">العنوان</label>
        <textarea class="form-input" name="address" rows="2">{{ old('address', $supplier?->address) }}</textarea>
    </div>
    <div class="form-group"><label class="form-label">ملاحظات</label>
        <textarea class="form-input" name="notes" rows="3">{{ old('notes', $supplier?->notes) }}</textarea>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">جهات الاتصال</span><button class="btn btn-ghost btn-sm"
                type="button" data-add-contact>إضافة جهة اتصال</button></div>
        <div class="card-body" id="contacts-list" style="display:grid;gap:.75rem"></div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">أصناف المورد وأسعارها</span><button
                class="btn btn-ghost btn-sm" type="button" data-add-supplier-product>إضافة صنف</button></div>
        <div class="card-body" id="supplier-products-list" style="display:grid;gap:.75rem"></div>
    </div>
</div>

<template id="contact-template">
    <div class="card" data-row>
        <div class="card-body"
            style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem"><input
                class="form-input" name="contacts[__INDEX__][name]" placeholder="الاسم"><input class="form-input"
                name="contacts[__INDEX__][position]" placeholder="المنصب"><input class="form-input"
                name="contacts[__INDEX__][phone]" placeholder="الهاتف"><input class="form-input"
                name="contacts[__INDEX__][whatsapp]" placeholder="واتساب"><input class="form-input" type="email"
                name="contacts[__INDEX__][email]" placeholder="البريد"><label
                style="display:flex;align-items:center;gap:.4rem"><input type="checkbox"
                    name="contacts[__INDEX__][is_primary]" value="1"> أساسي</label><button type="button"
                class="btn btn-ghost btn-sm" data-remove>حذف</button></div>
    </div>
</template>
<template id="supplier-product-template">
    <div class="card" data-row>
        <div class="card-body"
            style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem"><select
                class="form-input" name="products[__INDEX__][product_id]">
                <option value="">اختر منتجاً</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}">
                        {{ $product->name_ar ?: $product->name }}{{ $product->sku ? ' — ' . $product->sku : '' }}
                    </option>
                @endforeach
            </select>
            <input class="form-input" name="products[__INDEX__][supplier_sku]" placeholder="كود المورد"><input
                class="form-input" type="number" min="0" step="0.0001"
                name="products[__INDEX__][purchase_price]" placeholder="سعر الشراء"><select class="form-input"
                name="products[__INDEX__][currency_id]">
                <option value="">عملة المورد</option>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->id }}">{{ $currency->code }}</option>
                @endforeach
            </select><input class="form-input" type="number" min="0.001" step="0.001"
                name="products[__INDEX__][minimum_order_quantity]" value="1" placeholder="أقل كمية">
            <select class="form-input" name="products[__INDEX__][purchase_unit_id]">
                <option value="">نفس وحدة المخزون</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->displayName() }} ({{ $unit->symbol ?: $unit->code }})</option>
                @endforeach
            </select>
            <input class="form-input" type="number" min="0.000001" step="0.000001"
                name="products[__INDEX__][conversion_factor]" value="1"
                placeholder="معامل التحويل لوحدة المخزون">
            <input class="form-input" name="products[__INDEX__][package_description]"
                placeholder="وصف العبوة، مثال: كرتونة 12 كغ">
            <input class="form-input" type="number" min="0"
                name="products[__INDEX__][lead_time_days]"
                placeholder="أيام التوريد"><label style="display:flex;align-items:center;gap:.4rem"><input
                    type="hidden" name="products[__INDEX__][is_preferred]" value="0"><input type="checkbox"
                    name="products[__INDEX__][is_preferred]" value="1"> مورد مفضّل</label><label
                style="display:flex;align-items:center;gap:.4rem"><input type="hidden"
                    name="products[__INDEX__][is_active]" value="0"><input type="checkbox"
                    name="products[__INDEX__][is_active]" value="1" checked> نشط</label><button type="button"
                class="btn btn-ghost btn-sm" data-remove>حذف</button>
        </div>
    </div>
</template>

<script>
    (() => {
        const contacts = @json($contacts),
            products = @json($supplierProducts);
        const add = (templateId, containerId, index, values) => {
            const template = document.getElementById(templateId).innerHTML.replaceAll('__INDEX__', index);
            const holder = document.createElement('div');
            holder.innerHTML = template;
            const row = holder.firstElementChild;
            Object.entries(values || {}).forEach(([key, value]) => {
                const inputs = [...row.querySelectorAll(`[name$="[${key}]"]`)];
                const checkbox = inputs.find(input => input.type === 'checkbox');
                const input = checkbox || inputs[0];
                if (!input) return;
                if (checkbox) checkbox.checked = [true, 1, '1', 'true'].includes(value);
                else input.value = value ?? '';
            });
            row.querySelector('[data-remove]').addEventListener('click', () => row.remove());
            document.getElementById(containerId).appendChild(row);
        };
        let contactIndex = 0,
            productIndex = 0;
        contacts.forEach(row => add('contact-template', 'contacts-list', contactIndex++, row));
        products.forEach(row => add('supplier-product-template', 'supplier-products-list', productIndex++, row));
        document.querySelector('[data-add-contact]').addEventListener('click', () => add('contact-template',
            'contacts-list', contactIndex++, {}));
        document.querySelector('[data-add-supplier-product]').addEventListener('click', () => add(
            'supplier-product-template', 'supplier-products-list', productIndex++, {}));
    })();
</script>
