@extends('layouts.app')
@section('title', 'تسجيل فاتورة مورد')
@section('content')
    <div class="page-header">
        <h1 class="page-heading">تسجيل فاتورة مورد</h1>
        <p class="page-subheading"><a href="{{ route('supplier-invoices.index') }}">فواتير الموردين</a> &laquo; جديد</p>
    </div>
    @include('procurement.partials.flash')
    <form method="POST" action="{{ route('supplier-invoices.store') }}">@csrf
        <div class="card">
            <div class="card-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem">
                    <div class="form-group"><label class="form-label">رقم فاتورة المورد *</label><input class="form-input"
                            name="invoice_number" value="{{ old('invoice_number') }}" required></div>
                    <div class="form-group"><label class="form-label">المورد *</label><select class="form-input"
                            name="supplier_id" id="supplier-id" required>
                            <option value="">اختر المورد</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">الموقع *</label><select class="form-input"
                            name="location_id" id="location-id" required>
                            <option value="">اختر الموقع</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">العملة *</label><select class="form-input"
                            name="currency_id" id="currency-id" required>
                            <option value="">اختر العملة</option>
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}" @selected((string) old('currency_id') === (string) $currency->id)>
                                    {{ $currency->displayName() }} ({{ $currency->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">سعر الصرف</label><input class="form-input"
                            type="number" min="0.00000001" step="0.00000001" name="exchange_rate"
                            value="{{ old('exchange_rate') }}"></div>
                    <div class="form-group"><label class="form-label">تاريخ الفاتورة *</label><input class="form-input"
                            type="date" name="invoice_date" required
                            value="{{ old('invoice_date', now()->toDateString()) }}"></div>
                    <div class="form-group"><label class="form-label">تاريخ الاستحقاق</label><input class="form-input"
                            type="date" name="due_date" value="{{ old('due_date') }}"></div>
                    <div class="form-group"><label class="form-label">خصم إجمالي</label><input class="form-input"
                            type="number" min="0" step="0.01" name="discount_amount"
                            value="{{ old('discount_amount', 0) }}"></div>
                    <div class="form-group"><label class="form-label">ضريبة إجمالية</label><input class="form-input"
                            type="number" min="0" step="0.01" name="tax_amount"
                            value="{{ old('tax_amount', 0) }}"></div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;margin-top:1rem">
                    <div class="form-group"><label class="form-label">أمر الشراء كمصدر (اختياري)</label><select
                            class="form-input source" id="po-source" name="purchase_order_id">
                            <option value="">لا يوجد</option>
                            @foreach ($purchaseOrders as $order)
                                <option value="{{ $order->id }}" data-supplier="{{ $order->supplier_id }}"
                                    data-location="{{ $order->location_id }}" data-currency="{{ $order->currency_id }}"
                                    @selected((string) old('purchase_order_id', request('purchase_order_id')) === (string) $order->id)>{{ $order->purchase_order_number }} —
                                    {{ $order->supplier?->name }}</option>
                            @endforeach
                        </select>
                        <small>اختر مصدراً واحداً فقط؛ تُنسخ البنود منه عند ترك البنود اليدوية فارغة.</small>
                    </div>
                    <div class="form-group"><label class="form-label">سند الاستلام كمصدر (اختياري)</label><select
                            class="form-input source" id="receipt-source" name="goods_receipt_id">
                            <option value="">لا يوجد</option>
                            @foreach ($goodsReceipts as $receipt)
                                <option value="{{ $receipt->id }}" data-supplier="{{ $receipt->supplier_id }}"
                                    data-location="{{ $receipt->location_id }}"
                                    data-currency="{{ $receipt->currency_id }}" @selected((string) old('goods_receipt_id', request('goods_receipt_id')) === (string) $receipt->id)>
                                    {{ $receipt->receipt_number }} — {{ $receipt->supplier?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-top:1rem"><label class="form-label">ملاحظات</label>
                    <textarea class="form-input" name="notes">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>
        <div class="card" style="margin-top:1rem">
            <div class="card-header"><span class="card-title">بنود يدوية</span><button class="btn btn-ghost btn-sm"
                    type="button" id="add-invoice-item">إضافة بند يدوي</button></div>
            <div class="card-body">
                <p style="font-size:.83rem;color:var(--text-muted)">اترك هذه البنود فارغة عند اختيار أمر شراء أو سند استلام
                    ليتم نسخ بنوده تلقائياً.</p>
                <div id="invoice-items" style="display:grid;gap:.75rem"></div>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1rem"><button class="btn btn-gold">حفظ الفاتورة</button><a
                class="btn btn-ghost" href="{{ route('supplier-invoices.index') }}">إلغاء</a></div>
    </form>
    <template id="invoice-item-template">
        <div class="card" data-row>
            <div class="card-body"
                style="display:grid;grid-template-columns:2fr repeat(4,minmax(100px,1fr)) auto;gap:.65rem"><select
                    class="form-input" name="items[__INDEX__][product_id]">
                    <option value="">بند عام</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name_ar ?: $product->name }}</option>
                    @endforeach
                </select><input class="form-input" name="items[__INDEX__][description]" placeholder="الوصف"><input
                    class="form-input" type="number" min="0.001" step="0.001" name="items[__INDEX__][quantity]"
                    placeholder="الكمية"><input class="form-input" type="number" min="0" step="0.0001"
                    name="items[__INDEX__][unit_price]" placeholder="السعر"><input class="form-input" type="number"
                    min="0" step="0.01" name="items[__INDEX__][discount_amount]" placeholder="خصم"><input
                    class="form-input" type="number" min="0" step="0.01" name="items[__INDEX__][tax_amount]"
                    placeholder="ضريبة"><button class="btn btn-ghost btn-sm" type="button" data-remove>حذف</button>
            </div>
        </div>
    </template>
    <script>
        (() => {
            let i = 0;
            const output = document.getElementById('invoice-items');
            const add = (value = {}) => {
                const h = document.createElement('div');
                h.innerHTML = document.getElementById('invoice-item-template').innerHTML.replaceAll('__INDEX__',
                    i++);
                const r = h.firstElementChild;
                Object.entries(value).forEach(([k, v]) => {
                    const x = r.querySelector(`[name$="[${k}]"]`);
                    if (x) x.value = v ?? '';
                });
                r.querySelector('[data-remove]').onclick = () => r.remove();
                output.appendChild(r);
            };
            @if (old('items'))
                @foreach (old('items') as $item)
                    add(@json($item));
                @endforeach
            @endif
            document.getElementById('add-invoice-item').onclick = () => add();
            const applySource = (source) => {
                if (!source.value) return;
                const option = source.selectedOptions[0];
                document.getElementById('supplier-id').value = option.dataset.supplier;
                document.getElementById('location-id').value = option.dataset.location;
                document.getElementById('currency-id').value = option.dataset.currency;
                document.querySelectorAll('.source').forEach(x => {
                    if (x !== source) x.value = '';
                });
            };
            document.querySelectorAll('.source').forEach(s => s.addEventListener('change', () => applySource(s)));
            const active = [...document.querySelectorAll('.source')].find(s => s.value);
            if (active) applySource(active);
        })();
    </script>
@endsection
