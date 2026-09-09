@php

    /*
    |--------------------------------------------------------------------------
    | Current Values
    |--------------------------------------------------------------------------
    */

    $currentSupplierId = old(
        'supplier_id',
        $purchaseOrder->supplier_id ?? ''
    );

    $currentLocationId = old(
        'location_id',
        $purchaseOrder->location_id ?? ''
    );

    $currentCurrencyId = old(
        'currency_id',
        $purchaseOrder->currency_id ?? ''
    );

    $currentExchangeRate = old(
        'exchange_rate',
        $purchaseOrder->exchange_rate ?? ''
    );

    $currentOrderDate = old(
        'order_date',
        isset($purchaseOrder)
            ? $purchaseOrder->order_date?->format('Y-m-d')
            : now()->toDateString()
    );

    $currentExpectedDate = old(
        'expected_delivery_date',
        isset($purchaseOrder)
            ? $purchaseOrder->expected_delivery_date?->format('Y-m-d')
            : ''
    );


    /*
    |--------------------------------------------------------------------------
    | Initial Items
    |--------------------------------------------------------------------------
    */

    $initialItems = old('items');

    if (
        $initialItems === null
        &&
        isset($purchaseOrder)
    ) {

        $initialItems =
            $purchaseOrder->items
                ->map(function ($item) {

                    return [
                        'product_id' =>
                            $item->product_id,

                        'description' =>
                            $item->description,

                        'ordered_quantity' =>
                            $item->ordered_quantity,

                        'unit_price' =>
                            $item->unit_price,

                        'discount_amount' =>
                            $item->discount_amount,

                        'tax_amount' =>
                            $item->tax_amount,

                        'notes' =>
                            $item->notes,
                    ];

                })
                ->values()
                ->all();
    }

    if (
        ! is_array($initialItems)
        ||
        count($initialItems) === 0
    ) {

        $initialItems = [];
    }

@endphp


{{-- ================================================================
     البيانات الأساسية
================================================================ --}}

<div class="purchase-order-main-grid">

    {{-- المورد --}}
    <div class="form-group">

        <label
            class="form-label"
            for="supplier-id"
        >
            المورد *
        </label>

        <select
            class="form-input"
            name="supplier_id"
            id="supplier-id"
            required
        >

            <option value="">
                اختر المورد
            </option>


            @foreach($suppliers as $supplier)

                <option
                    value="{{ $supplier->id }}"

                    data-currency="{{ $supplier->currency_id }}"

                    @selected(
                        (string) $currentSupplierId
                        ===
                        (string) $supplier->id
                    )
                >

                    {{ $supplier->name }}

                    @if($supplier->supplier_code)
                        — {{ $supplier->supplier_code }}
                    @endif

                </option>

            @endforeach

        </select>

        @error('supplier_id')
            <span class="form-error">
                {{ $message }}
            </span>
        @enderror

    </div>


    {{-- الموقع --}}
    <div class="form-group">

        <label
            class="form-label"
            for="location-id"
        >
            الموقع *
        </label>

        <select
            class="form-input"
            name="location_id"
            id="location-id"
            required
        >

            <option value="">
                اختر الموقع
            </option>

            @foreach($locations as $location)

                <option
                    value="{{ $location->id }}"
                    @selected(
                        (string) $currentLocationId
                        ===
                        (string) $location->id
                    )
                >
                    {{ $location->name }}
                </option>

            @endforeach

        </select>

        @error('location_id')
            <span class="form-error">
                {{ $message }}
            </span>
        @enderror

    </div>


    {{-- العملة --}}
    <div class="form-group">

        <label
            class="form-label"
            for="currency-id"
        >
            عملة أمر الشراء *
        </label>

        <select
            class="form-input"
            name="currency_id"
            id="currency-id"
            required
        >

            <option value="">
                اختر العملة
            </option>


            @foreach($currencies as $currency)

                <option
                    value="{{ $currency->id }}"

                    data-code="{{ $currency->code }}"

                    data-base="{{ $currency->is_base ? 1 : 0 }}"

                    @selected(
                        (string) $currentCurrencyId
                        ===
                        (string) $currency->id
                    )
                >
                    {{ $currency->displayName() }}
                    ({{ $currency->code }})
                </option>

            @endforeach

        </select>

        <small class="purchase-help">
            تتحدد تلقائياً حسب أصناف المورد.
        </small>

        @error('currency_id')
            <span class="form-error">
                {{ $message }}
            </span>
        @enderror

    </div>


    {{-- سعر الصرف --}}
    <div class="form-group">

        <label
            class="form-label"
            for="exchange-rate"
        >
            سعر الصرف
        </label>

        <input
            class="form-input numeric-input"
            id="exchange-rate"
            type="number"
            lang="en-US"
            dir="ltr"
            name="exchange_rate"
            min="0.00000001"
            step="0.00000001"
            value="{{ $currentExchangeRate }}"
        >

        @error('exchange_rate')
            <span class="form-error">
                {{ $message }}
            </span>
        @enderror

    </div>


    {{-- تاريخ الطلب --}}
    <div class="form-group">

        <label
            class="form-label"
            for="order-date"
        >
            تاريخ أمر الشراء *
        </label>

        <input
            class="form-input"
            id="order-date"
            type="date"
            name="order_date"
            required
            value="{{ $currentOrderDate }}"
        >

        @error('order_date')
            <span class="form-error">
                {{ $message }}
            </span>
        @enderror

    </div>


    {{-- التسليم المتوقع --}}
    <div class="form-group">

        <label
            class="form-label"
            for="expected-delivery-date"
        >
            تاريخ التسليم المتوقع
        </label>

        <input
            class="form-input"
            id="expected-delivery-date"
            type="date"
            name="expected_delivery_date"
            value="{{ $currentExpectedDate }}"
        >

        <small class="purchase-help">
            يتم اقتراحه حسب أطول مدة توريد للأصناف.
        </small>

        @error('expected_delivery_date')
            <span class="form-error">
                {{ $message }}
            </span>
        @enderror

    </div>


    {{-- الخصم --}}
    <div class="form-group">

        <label class="form-label">
            خصم إجمالي
        </label>

        <input
            class="form-input numeric-input"
            type="number"
            lang="en-US"
            dir="ltr"
            name="discount_amount"
            min="0"
            step="0.01"
            value="{{ old(
                'discount_amount',
                $purchaseOrder->discount_amount ?? 0
            ) }}"
        >

        @error('discount_amount')
            <span class="form-error">
                {{ $message }}
            </span>
        @enderror

    </div>


    {{-- الضريبة --}}
    <div class="form-group">

        <label class="form-label">
            ضريبة إجمالية
        </label>

        <input
            class="form-input numeric-input"
            type="number"
            lang="en-US"
            dir="ltr"
            name="tax_amount"
            min="0"
            step="0.01"
            value="{{ old(
                'tax_amount',
                $purchaseOrder->tax_amount ?? 0
            ) }}"
        >

        @error('tax_amount')
            <span class="form-error">
                {{ $message }}
            </span>
        @enderror

    </div>


    {{-- الشحن --}}
    <div class="form-group">

        <label class="form-label">
            تكلفة الشحن
        </label>

        <input
            class="form-input numeric-input"
            type="number"
            lang="en-US"
            dir="ltr"
            name="shipping_cost"
            min="0"
            step="0.01"
            value="{{ old(
                'shipping_cost',
                $purchaseOrder->shipping_cost ?? 0
            ) }}"
        >

        @error('shipping_cost')
            <span class="form-error">
                {{ $message }}
            </span>
        @enderror

    </div>

</div>


{{-- Notes --}}
<div
    class="form-group"
    style="margin-top:1rem"
>

    <label class="form-label">
        ملاحظات أمر الشراء
    </label>

    <textarea
        class="form-input"
        name="notes"
        rows="3"
    >{{ old(
        'notes',
        $purchaseOrder->notes ?? ''
    ) }}</textarea>

</div>



{{-- ================================================================
     Supplier Products Information
================================================================ --}}

<div
    id="supplier-products-message"
    class="supplier-products-message"
    style="display:none"
></div>



{{-- ================================================================
     Items
================================================================ --}}

<div class="purchase-items-header">

    <div>

        <h3>
            أصناف أمر الشراء
        </h3>

        <p>
            لن تظهر إلا الأصناف المرتبطة بالمورد المحدد.
        </p>

    </div>


    <button
        class="btn btn-outline btn-sm"
        id="add-purchase-item"
        type="button"
    >
        + إضافة صنف
    </button>

</div>


<div
    id="purchase-items"
    class="purchase-items"
></div>


@error('items')
    <span
        class="form-error"
        style="display:block;margin-top:.75rem"
    >
        {{ $message }}
    </span>
@enderror



{{-- ================================================================
     Row Template
================================================================ --}}

<template id="purchase-item-template">

    <div
        class="purchase-item-card"
        data-purchase-row
    >

        <div class="purchase-item-grid">

            {{-- Product --}}
            <div class="form-group product-field">

                <label class="form-label">
                    المنتج *
                </label>

                <select
                    class="form-input product-select"
                    name="items[__INDEX__][product_id]"
                    required
                >
                    <option value="">
                        اختر المنتج
                    </option>
                </select>


                <div class="supplier-product-info"></div>

                <div
                    class="row-field-error"
                    data-error="product_id"
                ></div>

            </div>


            {{-- Quantity --}}
            <div class="form-group">

                <label class="form-label">
                    الكمية *
                </label>

                <input
                    class="form-input numeric-input quantity-input"
                    type="number"
                    lang="en-US"
                    dir="ltr"
                    min="0.001"
                    step="0.001"
                    name="items[__INDEX__][ordered_quantity]"
                    required
                >

                <small class="moq-note"></small>

                <div
                    class="row-field-error"
                    data-error="ordered_quantity"
                ></div>

            </div>


            {{-- Price --}}
            <div class="form-group">

                <label class="form-label">
                    سعر الشراء *
                </label>

                <div class="price-wrapper">

                    <input
                        class="form-input numeric-input price-input"
                        type="number"
                        lang="en-US"
                        dir="ltr"
                        min="0"
                        step="0.0001"
                        name="items[__INDEX__][unit_price]"
                        required
                    >

                    <span class="row-currency"></span>

                </div>

                <div
                    class="row-field-error"
                    data-error="unit_price"
                ></div>

            </div>


            {{-- Discount --}}
            <div class="form-group">

                <label class="form-label">
                    الخصم
                </label>

                <input
                    class="form-input numeric-input"
                    type="number"
                    lang="en-US"
                    dir="ltr"
                    min="0"
                    step="0.01"
                    name="items[__INDEX__][discount_amount]"
                    value="0"
                >

            </div>


            {{-- Tax --}}
            <div class="form-group">

                <label class="form-label">
                    الضريبة
                </label>

                <input
                    class="form-input numeric-input"
                    type="number"
                    lang="en-US"
                    dir="ltr"
                    min="0"
                    step="0.01"
                    name="items[__INDEX__][tax_amount]"
                    value="0"
                >

            </div>


            {{-- Description --}}
            <div class="form-group description-field">

                <label class="form-label">
                    الوصف
                </label>

                <input
                    class="form-input description-input"
                    type="text"
                    name="items[__INDEX__][description]"
                    maxlength="255"
                >

            </div>


            {{-- Notes --}}
            <div class="form-group notes-field">

                <label class="form-label">
                    ملاحظات
                </label>

                <input
                    class="form-input"
                    type="text"
                    name="items[__INDEX__][notes]"
                >

            </div>


            {{-- Remove --}}
            <div class="item-remove">

                <button
                    type="button"
                    class="btn btn-ghost btn-sm"
                    data-remove-item
                >
                    حذف
                </button>

            </div>

        </div>

    </div>

</template>



<style>

    .purchase-order-main-grid {
        display: grid;

        grid-template-columns:
            repeat(
                auto-fit,
                minmax(210px, 1fr)
            );

        gap: 1rem;
    }


    .purchase-help {
        display: block;
        margin-top: .3rem;

        color: var(--text-muted);
        font-size: .7rem;
    }


    .supplier-products-message {
        margin-top: 1rem;

        padding: .8rem 1rem;

        border-radius: .65rem;

        background: #fffbea;

        border: 1px solid #ead99b;

        color: #786016;

        font-size: .8rem;
    }


    .supplier-products-message.error {
        background: #fff4f4;

        border-color: #fecaca;

        color: #b91c1c;
    }


    .purchase-items-header {
        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 1rem;

        margin-top: 1.5rem;
        margin-bottom: .75rem;
    }


    .purchase-items-header h3 {
        margin: 0;

        font-size: 1rem;
    }


    .purchase-items-header p {
        margin: .2rem 0 0;

        color: var(--text-muted);

        font-size: .75rem;
    }


    .purchase-items {
        display: grid;
        gap: .85rem;
    }


    .purchase-item-card {
        padding: 1rem;

        border: 1px solid var(--border-light);

        border-radius: .75rem;

        background: #fff;
    }


    .purchase-item-grid {
        display: grid;

        grid-template-columns:
            minmax(220px, 2fr)
            repeat(4, minmax(110px, 1fr))
            minmax(170px, 1.4fr)
            minmax(160px, 1.2fr)
            auto;

        gap: .65rem;

        align-items: start;
    }


    .supplier-product-info {
        min-height: 18px;

        margin-top: .4rem;

        font-size: .68rem;

        color: var(--text-muted);
    }


    .supplier-product-meta {
        display: flex;

        gap: .3rem .55rem;

        flex-wrap: wrap;
    }


    .supplier-product-meta span {
        display: inline-flex;

        padding: .16rem .4rem;

        border-radius: 20px;

        background: #f6f3e8;

        color: #806a23;
    }


    .supplier-product-meta .preferred {
        background: #ecfdf5;
        color: #047857;
    }


    .numeric-input {
        direction: ltr !important;
        text-align: right !important;

        font-variant-numeric:
            tabular-nums;
    }


    .price-wrapper {
        position: relative;
    }


    .price-wrapper .price-input {
        padding-left: 50px;
    }


    .row-currency {
        position: absolute;

        left: 10px;
        top: 50%;

        transform: translateY(-50%);

        direction: ltr;

        color: var(--text-muted);

        font-size: .7rem;
        font-weight: 700;

        pointer-events: none;
    }


    .moq-note {
        display: block;

        min-height: 16px;

        margin-top: .25rem;

        color: #92751b;

        font-size: .65rem;
    }


    .item-remove {
        padding-top: 28px;
    }


    .row-field-error {
        margin-top: .3rem;

        color: var(--error, #b91c1c);

        font-size: .68rem;
    }


    @media (max-width: 1250px) {

        .purchase-item-grid {
            grid-template-columns:
                repeat(
                    3,
                    minmax(180px, 1fr)
                );
        }


        .product-field,
        .description-field,
        .notes-field {
            grid-column: span 1;
        }


        .item-remove {
            padding-top: 0;
        }

    }


    @media (max-width: 768px) {

        .purchase-item-grid {
            grid-template-columns: 1fr;
        }


        .purchase-items-header {
            align-items: stretch;
            flex-direction: column;
        }


        .purchase-items-header .btn {
            width: 100%;
            justify-content: center;
        }

    }

</style>



<script>

    (() => {

        /*
        |--------------------------------------------------------------------------
        | Data From Laravel
        |--------------------------------------------------------------------------
        */

        const supplierProducts =
            @json($supplierProducts);

        const escapeHtml = value => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');


        const initialItems =
            @json($initialItems);


        const validationErrors =
            @json($errors->toArray());


        /*
        |--------------------------------------------------------------------------
        | Elements
        |--------------------------------------------------------------------------
        */

        const supplierSelect =
            document.getElementById(
                'supplier-id'
            );


        const currencySelect =
            document.getElementById(
                'currency-id'
            );


        const exchangeRate =
            document.getElementById(
                'exchange-rate'
            );


        const orderDate =
            document.getElementById(
                'order-date'
            );


        const expectedDate =
            document.getElementById(
                'expected-delivery-date'
            );


        const addButton =
            document.getElementById(
                'add-purchase-item'
            );


        const itemsContainer =
            document.getElementById(
                'purchase-items'
            );


        const messageBox =
            document.getElementById(
                'supplier-products-message'
            );


        const template =
            document.getElementById(
                'purchase-item-template'
            );


        /*
        |--------------------------------------------------------------------------
        | Index
        |--------------------------------------------------------------------------
        */

        let rowIndex = 0;


        /*
        |--------------------------------------------------------------------------
        | Helpers
        |--------------------------------------------------------------------------
        */

        const numberValue = value => {

            const parsed =
                Number.parseFloat(value);

            return Number.isFinite(parsed)
                ? parsed
                : 0;

        };


        const getSupplierProducts = () => {

            if (!supplierSelect.value) {
                return [];
            }


            return supplierProducts.filter(
                item =>
                    String(item.supplier_id)
                    ===
                    String(supplierSelect.value)
            );

        };


        const getProductLink = (
            productId
        ) => {

            return getSupplierProducts()
                .find(
                    item =>
                        String(item.product_id)
                        ===
                        String(productId)
                );

        };


        const formatQuantity = value => {

            return numberValue(value)
                .toFixed(3)
                .replace(/\.?0+$/, '');

        };


        const formatPrice = value => {

            return numberValue(value)
                .toFixed(4)
                .replace(/\.?0+$/, '');

        };


        /*
        |--------------------------------------------------------------------------
        | Supplier Message
        |--------------------------------------------------------------------------
        */

        const updateSupplierMessage = () => {

            if (!supplierSelect.value) {

                messageBox.style.display =
                    'block';

                messageBox.classList.remove(
                    'error'
                );

                messageBox.textContent =
                    'اختر المورد أولاً حتى تظهر الأصناف التي يوردها.';

                addButton.disabled = true;

                return;

            }


            const products =
                getSupplierProducts();


            if (!products.length) {

                messageBox.style.display =
                    'block';

                messageBox.classList.add(
                    'error'
                );

                messageBox.textContent =
                    'هذا المورد لا يوجد لديه أي صنف نشط مسجل. أضف أصناف المورد أولاً من صفحة المورد.';

                addButton.disabled = true;

                return;

            }


            messageBox.style.display =
                'block';

            messageBox.classList.remove(
                'error'
            );

            messageBox.textContent =
                `يوجد ${products.length} صنف متاح لهذا المورد فقط.`;

            addButton.disabled = false;

        };


        /*
        |--------------------------------------------------------------------------
        | Product Options
        |--------------------------------------------------------------------------
        */

        const fillProductOptions = (
            select,
            selectedValue = ''
        ) => {

            select.innerHTML = '';


            const blank =
                document.createElement(
                    'option'
                );

            blank.value = '';

            blank.textContent =
                'اختر المنتج';

            select.appendChild(blank);


            getSupplierProducts()
                .forEach(item => {

                    const option =
                        document.createElement(
                            'option'
                        );

                    option.value =
                        item.product_id;


                    let text =
                        item.product_name;


                    if (item.supplier_sku) {

                        text +=
                            ` — ${item.supplier_sku}`;

                    }


                    if (item.is_preferred) {

                        text +=
                            ' — مفضّل';

                    }


                    option.textContent =
                        text;


                    if (
                        String(item.product_id)
                        ===
                        String(selectedValue)
                    ) {

                        option.selected =
                            true;

                    }


                    select.appendChild(
                        option
                    );

                });

        };


        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Product
        |--------------------------------------------------------------------------
        */

        const refreshDisabledProducts = () => {

            const selects =
                [
                    ...itemsContainer
                        .querySelectorAll(
                            '.product-select'
                        )
                ];


            const selected =
                selects
                    .map(
                        select =>
                            select.value
                    )
                    .filter(Boolean);


            selects.forEach(select => {

                [
                    ...select.options
                ].forEach(option => {

                    if (!option.value) {
                        return;
                    }


                    option.disabled =
                        selected.includes(
                            option.value
                        )
                        &&
                        option.value
                            !==
                            select.value;

                });

            });

        };


        /*
        |--------------------------------------------------------------------------
        | Update Expected Delivery
        |--------------------------------------------------------------------------
        */

        const updateExpectedDelivery = () => {

            if (!orderDate.value) {
                return;
            }


            let maxLeadDays = 0;


            itemsContainer
                .querySelectorAll(
                    '.product-select'
                )
                .forEach(select => {

                    if (!select.value) {
                        return;
                    }


                    const link =
                        getProductLink(
                            select.value
                        );


                    if (
                        link
                        &&
                        link.lead_time_days
                    ) {

                        maxLeadDays =
                            Math.max(
                                maxLeadDays,
                                Number(
                                    link.lead_time_days
                                )
                            );

                    }

                });


            if (maxLeadDays <= 0) {
                return;
            }


            const dateParts =
                orderDate.value
                    .split('-')
                    .map(Number);


            const date =
                new Date(
                    dateParts[0],
                    dateParts[1] - 1,
                    dateParts[2]
                );


            date.setDate(
                date.getDate()
                +
                maxLeadDays
            );


            const yyyy =
                date.getFullYear();


            const mm =
                String(
                    date.getMonth() + 1
                ).padStart(2, '0');


            const dd =
                String(
                    date.getDate()
                ).padStart(2, '0');


            expectedDate.value =
                `${yyyy}-${mm}-${dd}`;

        };


        /*
        |--------------------------------------------------------------------------
        | Determine Order Currency
        |--------------------------------------------------------------------------
        */

        const applyProductCurrency = (
            link,
            currentSelect
        ) => {

            if (!link) {
                return true;
            }


            /*
             * افحص الأصناف الأخرى.
             */
            const otherCurrencyIds = [];


            itemsContainer
                .querySelectorAll(
                    '.product-select'
                )
                .forEach(select => {

                    if (
                        select === currentSelect
                        ||
                        !select.value
                    ) {
                        return;
                    }


                    const otherLink =
                        getProductLink(
                            select.value
                        );


                    if (otherLink) {

                        otherCurrencyIds.push(
                            String(
                                otherLink.currency_id
                            )
                        );

                    }

                });


            if (
                otherCurrencyIds.length
                &&
                !otherCurrencyIds.includes(
                    String(
                        link.currency_id
                    )
                )
            ) {

                alert(
                    'لا يمكن جمع أصناف بعملات مختلفة في أمر شراء واحد. أنشئ أمر شراء منفصل للعملة الأخرى.'
                );

                currentSelect.value = '';

                return false;

            }


            currencySelect.value =
                link.currency_id;


            const option =
                currencySelect
                    .selectedOptions[0];


            if (
                option
                &&
                option.dataset.base === '1'
            ) {

                exchangeRate.value = '1';

            }


            return true;

        };


        /*
        |--------------------------------------------------------------------------
        | Apply Product To Row
        |--------------------------------------------------------------------------
        */

        const applyProduct = (
            row,
            forceDefaults = true
        ) => {

            const select =
                row.querySelector(
                    '.product-select'
                );


            const quantity =
                row.querySelector(
                    '.quantity-input'
                );


            const price =
                row.querySelector(
                    '.price-input'
                );


            const description =
                row.querySelector(
                    '.description-input'
                );


            const info =
                row.querySelector(
                    '.supplier-product-info'
                );


            const moqNote =
                row.querySelector(
                    '.moq-note'
                );


            const currencyBadge =
                row.querySelector(
                    '.row-currency'
                );


            if (!select.value) {

                info.innerHTML = '';

                moqNote.textContent = '';

                currencyBadge.textContent =
                    '';

                refreshDisabledProducts();

                updateExpectedDelivery();

                return;

            }


            const link =
                getProductLink(
                    select.value
                );


            if (!link) {

                select.value = '';

                info.innerHTML = '';

                return;

            }


            if (
                !applyProductCurrency(
                    link,
                    select
                )
            ) {

                info.innerHTML = '';

                refreshDisabledProducts();

                return;

            }


            /*
            |--------------------------------------------------------------------------
            | Defaults
            |--------------------------------------------------------------------------
            */

            if (forceDefaults) {

                price.value =
                    formatPrice(
                        link.purchase_price
                    );


                if (
                    !quantity.value
                    ||
                    numberValue(
                        quantity.value
                    )
                    <
                    numberValue(
                        link.minimum_order_quantity
                    )
                ) {

                    quantity.value =
                        formatQuantity(
                            link.minimum_order_quantity
                        );

                }


                if (!description.value) {

                    description.value =
                        link.supplier_product_name
                        ||
                        link.product_name;

                }

            }


            /*
            |--------------------------------------------------------------------------
            | MOQ
            |--------------------------------------------------------------------------
            */

            const minimum =
                numberValue(
                    link.minimum_order_quantity
                );


            quantity.min =
                minimum > 0
                    ? minimum
                    : 0.001;


            moqNote.textContent =
                minimum > 0
                    ? `الحد الأدنى: ${formatQuantity(minimum)}`
                    : '';


            /*
            |--------------------------------------------------------------------------
            | Currency
            |--------------------------------------------------------------------------
            */

            currencyBadge.textContent =
                link.currency_code || '';


            /*
            |--------------------------------------------------------------------------
            | Supplier Product Metadata
            |--------------------------------------------------------------------------
            */

            const metadata = [];


            if (link.supplier_sku) {

                metadata.push(
                    `<span>كود المورد: ${escapeHtml(link.supplier_sku)}</span>`
                );

            }

            if (link.purchase_unit_name) {
                metadata.push(
                    `<span>وحدة الشراء: ${escapeHtml(link.purchase_unit_name)}</span>`
                );
            }

            if (Number(link.conversion_factor || 1) !== 1) {
                metadata.push(
                    `<span>معامل التحويل: ${formatQuantity(link.conversion_factor)}</span>`
                );
            }

            if (link.package_description) {
                metadata.push(
                    `<span>${escapeHtml(link.package_description)}</span>`
                );
            }


            if (minimum > 0) {

                metadata.push(
                    `<span>MOQ: ${formatQuantity(minimum)}</span>`
                );

            }


            if (
                link.lead_time_days
                !==
                null
            ) {

                metadata.push(
                    `<span>التوريد: ${link.lead_time_days} يوم</span>`
                );

            }


            if (link.is_preferred) {

                metadata.push(
                    `<span class="preferred">★ مورد مفضّل للصنف</span>`
                );

            }


            info.innerHTML =
                `<div class="supplier-product-meta">${metadata.join('')}</div>`;


            refreshDisabledProducts();

            updateExpectedDelivery();

        };


        /*
        |--------------------------------------------------------------------------
        | Apply Validation Errors
        |--------------------------------------------------------------------------
        */

        const applyErrors = (
            row,
            index
        ) => {

            [
                'product_id',
                'ordered_quantity',
                'unit_price',
            ].forEach(field => {

                const key =
                    `items.${index}.${field}`;


                const output =
                    row.querySelector(
                        `[data-error="${field}"]`
                    );


                if (
                    output
                    &&
                    validationErrors[key]
                ) {

                    output.textContent =
                        validationErrors[key][0];

                }

            });

        };


        /*
        |--------------------------------------------------------------------------
        | Add Row
        |--------------------------------------------------------------------------
        */

        const addRow = (
            values = {}
        ) => {

            if (!supplierSelect.value) {

                alert(
                    'اختر المورد أولاً.'
                );

                return;

            }


            const products =
                getSupplierProducts();


            if (!products.length) {

                alert(
                    'لا توجد أصناف مرتبطة بهذا المورد.'
                );

                return;

            }


            const index =
                rowIndex++;


            const holder =
                document.createElement(
                    'div'
                );


            holder.innerHTML =
                template.innerHTML
                    .replaceAll(
                        '__INDEX__',
                        index
                    );


            const row =
                holder.firstElementChild;


            const productSelect =
                row.querySelector(
                    '.product-select'
                );


            fillProductOptions(
                productSelect,
                values.product_id || ''
            );


            /*
            |--------------------------------------------------------------------------
            | Populate Old/Edit Values
            |--------------------------------------------------------------------------
            */

            const setValue = (
                field,
                value
            ) => {

                const element =
                    row.querySelector(
                        `[name="items[${index}][${field}]"]`
                    );


                if (
                    element
                    &&
                    value !== undefined
                    &&
                    value !== null
                ) {

                    element.value =
                        value;

                }

            };


            setValue(
                'ordered_quantity',
                values.ordered_quantity
            );

            setValue(
                'unit_price',
                values.unit_price
            );

            setValue(
                'discount_amount',
                values.discount_amount ?? 0
            );

            setValue(
                'tax_amount',
                values.tax_amount ?? 0
            );

            setValue(
                'description',
                values.description ?? ''
            );

            setValue(
                'notes',
                values.notes ?? ''
            );


            /*
            |--------------------------------------------------------------------------
            | Product Change
            |--------------------------------------------------------------------------
            */

            productSelect
                .addEventListener(
                    'change',
                    () => {

                        applyProduct(
                            row,
                            true
                        );

                    }
                );


            /*
            |--------------------------------------------------------------------------
            | Quantity Validation
            |--------------------------------------------------------------------------
            */

            const quantity =
                row.querySelector(
                    '.quantity-input'
                );


            quantity.addEventListener(
                'input',
                () => {

                    const link =
                        getProductLink(
                            productSelect.value
                        );


                    if (!link) {
                        return;
                    }


                    const minimum =
                        numberValue(
                            link.minimum_order_quantity
                        );


                    quantity
                        .setCustomValidity(
                            ''
                        );


                    if (
                        minimum > 0
                        &&
                        numberValue(
                            quantity.value
                        )
                        <
                        minimum
                    ) {

                        quantity
                            .setCustomValidity(
                                `الحد الأدنى للطلب هو ${formatQuantity(minimum)}`
                            );

                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | Remove
            |--------------------------------------------------------------------------
            */

            row
                .querySelector(
                    '[data-remove-item]'
                )
                .addEventListener(
                    'click',
                    () => {

                        row.remove();

                        refreshDisabledProducts();

                        updateExpectedDelivery();

                    }
                );


            itemsContainer.appendChild(
                row
            );


            /*
             * عند edit / validation error
             * نحافظ على السعر والكمية الموجودة.
             */
            if (values.product_id) {

                applyProduct(
                    row,
                    false
                );

            }


            applyErrors(
                row,
                index
            );


            refreshDisabledProducts();

        };


        /*
        |--------------------------------------------------------------------------
        | Supplier Change
        |--------------------------------------------------------------------------
        */

        supplierSelect.addEventListener(
            'change',
            () => {

                /*
                 * عند تغيير المورد:
                 * الأصناف القديمة لم تعد صالحة.
                 */
                itemsContainer.innerHTML = '';

                rowIndex = 0;


                const supplierOption =
                    supplierSelect
                        .selectedOptions[0];


                const supplierCurrency =
                    supplierOption
                        ?.dataset
                        ?.currency;


                if (supplierCurrency) {

                    currencySelect.value =
                        supplierCurrency;

                }


                updateSupplierMessage();


                if (
                    supplierSelect.value
                    &&
                    getSupplierProducts().length
                ) {

                    addRow();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Add Button
        |--------------------------------------------------------------------------
        */

        addButton.addEventListener(
            'click',
            () => {

                addRow();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Date Change
        |--------------------------------------------------------------------------
        */

        orderDate.addEventListener(
            'change',
            updateExpectedDelivery
        );


        /*
        |--------------------------------------------------------------------------
        | Initial Load
        |--------------------------------------------------------------------------
        */

        updateSupplierMessage();


        if (supplierSelect.value) {

            if (
                Array.isArray(initialItems)
                &&
                initialItems.length
            ) {

                initialItems.forEach(
                    item => addRow(item)
                );

            } else {

                addRow();

            }

        }

    })();

</script>
