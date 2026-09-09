@extends('layouts.app')

@section('title', 'سند استلام مشتريات')

@section('content')

    <div class="page-header">
        <div>
            <h1 class="page-heading">سند استلام مشتريات</h1>

            <p class="page-subheading">
                <a href="{{ route('goods-receipts.index') }}">
                    سندات الاستلام
                </a>
                &laquo; جديد
            </p>
        </div>
    </div>

    @include('procurement.partials.flash')


    {{-- اختيار أمر الشراء --}}
    <div class="card" style="margin-bottom:1rem">
        <div class="card-body">

            <form method="GET" class="order-select-form">

                <div class="form-group" style="min-width:320px">

                    <label class="form-label">
                        أمر الشراء المعتمد
                    </label>

                    <select
                        class="form-input"
                        name="purchase_order_id"
                    >
                        <option value="">
                            اختر أمر شراء
                        </option>

                        @foreach ($orders as $order)

                            <option
                                value="{{ $order->id }}"
                                @selected(
                                    (string) $selectedOrder?->id === (string) $order->id
                                )
                            >
                                {{ $order->purchase_order_number }}
                                —
                                {{ $order->supplier?->name }}
                                —
                                {{ $order->location?->name }}
                            </option>

                        @endforeach

                    </select>

                </div>

                <button
                    class="btn btn-outline"
                    type="submit"
                >
                    تحميل البنود
                </button>

            </form>

        </div>
    </div>


    @if ($selectedOrder)

        <form
            action="{{ route('goods-receipts.store') }}"
            method="POST"
            id="goods-receipt-form"
        >

            @csrf

            <input
                type="hidden"
                name="purchase_order_id"
                value="{{ $selectedOrder->id }}"
            >


            <div class="card">

                <div class="card-body">

                    {{-- البيانات الأساسية --}}
                    <div class="receipt-main-grid">

                        <div class="form-group">

                            <label class="form-label">
                                المورد
                            </label>

                            <input
                                class="form-input"
                                value="{{ $selectedOrder->supplier?->name }}"
                                disabled
                            >

                        </div>


                        <div class="form-group">

                            <label class="form-label">
                                الموقع
                            </label>

                            <input
                                class="form-input"
                                value="{{ $selectedOrder->location?->name }}"
                                disabled
                            >

                        </div>


                        <div class="form-group">

                            <label class="form-label">
                                العملة
                            </label>

                            <input
                                class="form-input"
                                value="{{ $selectedOrder->currency?->code }}"
                                disabled
                                dir="ltr"
                            >

                        </div>


                        <div class="form-group">

                            <label class="form-label">
                                تاريخ الاستلام *
                            </label>

                            <input
                                class="form-input"
                                type="datetime-local"
                                name="received_at"
                                required
                                value="{{ old(
                                    'received_at',
                                    now()->format('Y-m-d\TH:i')
                                ) }}"
                            >

                            @error('received_at')
                                <span class="form-error">
                                    {{ $message }}
                                </span>
                            @enderror

                        </div>

                    </div>


                    {{-- الملاحظات --}}
                    <div class="form-group">

                        <label class="form-label">
                            ملاحظات
                        </label>

                        <textarea
                            class="form-input"
                            name="notes"
                            rows="3"
                        >{{ old('notes') }}</textarea>

                        @error('notes')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    {{-- تنبيه التتبع --}}
                    <div class="receipt-info">

                        <div class="receipt-info-icon">
                            📦
                        </div>

                        <div>

                            <strong>
                                بيانات تتبع المخزون
                            </strong>

                            <span>
                                المنتجات التي تتبع الدفعات تحتاج رقم دفعة،
                                والمنتجات التي تتبع الصلاحية تحتاج تاريخ صلاحية
                                قبل ترحيل السند إلى المخزون.
                            </span>

                        </div>

                    </div>


                    {{-- البنود --}}
                    <div class="table-wrap">

                        <table class="data-table receipt-items-table">

                            <thead>

                                <tr>
                                    <th>المنتج</th>
                                    <th>المتبقي بالأمر</th>
                                    <th>المستلم</th>
                                    <th>المقبول</th>
                                    <th>المرفوض</th>
                                    <th>تكلفة الوحدة</th>
                                    <th>رقم الدفعة</th>
                                    <th>تاريخ الإنتاج</th>
                                    <th>تاريخ الصلاحية</th>
                                </tr>

                            </thead>


                            <tbody>

                                @php
                                    $rowIndex = 0;
                                @endphp


                                @foreach ($selectedOrder->items as $item)

                                    @if ($item->remainingQuantity() > 0)

                                        @php
                                            $tracksBatch = (bool) ($item->product?->tracks_batch ?? false);
                                            $tracksExpiry = (bool) ($item->product?->tracks_expiry ?? false);

                                            $remaining = (float) $item->remainingQuantity();

                                            $productName =
                                                $item->product?->name_ar
                                                ?: $item->product?->name
                                                ?: 'منتج';
                                        @endphp


                                        <tr data-item-row>

                                            {{-- المنتج --}}
                                            <td>

                                                <div class="receipt-product">

                                                    <strong>
                                                        {{ $productName }}
                                                    </strong>


                                                    @if($item->product?->sku)
                                                        <small class="product-sku">
                                                            {{ $item->product->sku }}
                                                        </small>
                                                    @endif


                                                    <div class="tracking-badges">

                                                        @if($tracksBatch)
                                                            <span class="tracking-badge">
                                                                يتتبع الدفعات
                                                            </span>
                                                        @endif


                                                        @if($tracksExpiry)
                                                            <span class="tracking-badge">
                                                                يتتبع الصلاحية
                                                            </span>
                                                        @endif

                                                    </div>

                                                </div>


                                                <input
                                                    type="hidden"
                                                    name="items[{{ $rowIndex }}][purchase_order_item_id]"
                                                    value="{{ $item->id }}"
                                                >

                                            </td>


                                            {{-- المتبقي --}}
                                            <td class="numeric-cell">

                                                {{ number_format(
                                                    $remaining,
                                                    3,
                                                    '.',
                                                    ''
                                                ) }}

                                            </td>


                                            {{-- المستلم --}}
                                            <td>

                                                <input
                                                    class="form-input numeric-input received-input"
                                                    type="number"
                                                    lang="en-US"
                                                    dir="ltr"
                                                    min="0.001"
                                                    max="{{ $remaining }}"
                                                    step="0.001"
                                                    name="items[{{ $rowIndex }}][received_quantity]"
                                                    value="{{ old(
                                                        "items.$rowIndex.received_quantity",
                                                        $remaining
                                                    ) }}"
                                                    required
                                                >

                                                @error("items.$rowIndex.received_quantity")
                                                    <span class="form-error">
                                                        {{ $message }}
                                                    </span>
                                                @enderror

                                            </td>


                                            {{-- المقبول --}}
                                            <td>

                                                <input
                                                    class="form-input numeric-input accepted-input"
                                                    type="number"
                                                    lang="en-US"
                                                    dir="ltr"
                                                    min="0"
                                                    step="0.001"
                                                    name="items[{{ $rowIndex }}][accepted_quantity]"
                                                    value="{{ old(
                                                        "items.$rowIndex.accepted_quantity",
                                                        $remaining
                                                    ) }}"
                                                    required
                                                >

                                                @error("items.$rowIndex.accepted_quantity")
                                                    <span class="form-error">
                                                        {{ $message }}
                                                    </span>
                                                @enderror

                                            </td>


                                            {{-- المرفوض --}}
                                            <td>

                                                <input
                                                    class="form-input numeric-input rejected-input"
                                                    type="number"
                                                    lang="en-US"
                                                    dir="ltr"
                                                    min="0"
                                                    step="0.001"
                                                    name="items[{{ $rowIndex }}][rejected_quantity]"
                                                    value="{{ old(
                                                        "items.$rowIndex.rejected_quantity",
                                                        0
                                                    ) }}"
                                                    required
                                                >

                                                @error("items.$rowIndex.rejected_quantity")
                                                    <span class="form-error">
                                                        {{ $message }}
                                                    </span>
                                                @enderror

                                            </td>


                                            {{-- التكلفة --}}
                                            <td>

                                                <div class="cost-input-wrap">

                                                    <input
                                                        class="form-input numeric-input"
                                                        type="number"
                                                        lang="en-US"
                                                        dir="ltr"
                                                        min="0"
                                                        step="0.0001"
                                                        name="items[{{ $rowIndex }}][unit_cost]"
                                                        value="{{ old(
                                                            "items.$rowIndex.unit_cost",
                                                            $item->unit_price
                                                        ) }}"
                                                        required
                                                    >

                                                    <span>
                                                        {{ $selectedOrder->currency?->displayName() }}
                                                    </span>

                                                </div>

                                                @error("items.$rowIndex.unit_cost")
                                                    <span class="form-error">
                                                        {{ $message }}
                                                    </span>
                                                @enderror

                                            </td>


                                            {{-- رقم الدفعة --}}
                                            <td>

                                                <input
                                                    class="form-input"
                                                    type="text"
                                                    name="items[{{ $rowIndex }}][batch_number]"
                                                    value="{{ old(
                                                        "items.$rowIndex.batch_number"
                                                    ) }}"
                                                    maxlength="100"
                                                    placeholder="{{ $tracksBatch
                                                        ? 'رقم الدفعة'
                                                        : 'اختياري'
                                                    }}"
                                                >

                                                @if($tracksBatch)
                                                    <small class="field-required-note">
                                                        مطلوب قبل الترحيل
                                                    </small>
                                                @endif

                                                @error("items.$rowIndex.batch_number")
                                                    <span class="form-error">
                                                        {{ $message }}
                                                    </span>
                                                @enderror

                                            </td>


                                            {{-- تاريخ الإنتاج --}}
                                            <td>

                                                <input
                                                    class="form-input date-input"
                                                    type="date"
                                                    name="items[{{ $rowIndex }}][manufacturing_date]"
                                                    value="{{ old(
                                                        "items.$rowIndex.manufacturing_date"
                                                    ) }}"
                                                >

                                                @error("items.$rowIndex.manufacturing_date")
                                                    <span class="form-error">
                                                        {{ $message }}
                                                    </span>
                                                @enderror

                                            </td>


                                            {{-- تاريخ الصلاحية --}}
                                            <td>

                                                <input
                                                    class="form-input date-input"
                                                    type="date"
                                                    name="items[{{ $rowIndex }}][expiry_date]"
                                                    value="{{ old(
                                                        "items.$rowIndex.expiry_date"
                                                    ) }}"
                                                >

                                                @if($tracksExpiry)
                                                    <small class="field-required-note">
                                                        مطلوب قبل الترحيل
                                                    </small>
                                                @endif

                                                @error("items.$rowIndex.expiry_date")
                                                    <span class="form-error">
                                                        {{ $message }}
                                                    </span>
                                                @enderror

                                            </td>

                                        </tr>


                                        @php
                                            $rowIndex++;
                                        @endphp

                                    @endif

                                @endforeach


                                @if($rowIndex === 0)

                                    <tr>
                                        <td
                                            colspan="9"
                                            style="text-align:center;padding:2rem"
                                        >
                                            لا توجد كميات متبقية للاستلام في أمر الشراء.
                                        </td>
                                    </tr>

                                @endif

                            </tbody>

                        </table>

                    </div>

                </div>
            </div>


            {{-- الأزرار --}}
            <div class="receipt-actions">

                @if($rowIndex > 0)

                    <button
                        class="btn btn-gold"
                        type="submit"
                    >
                        حفظ كسند مسودة
                    </button>

                @endif


                <a
                    class="btn btn-ghost"
                    href="{{ route('goods-receipts.index') }}"
                >
                    إلغاء
                </a>

            </div>

        </form>

    @endif


    <style>

        .order-select-form {
            display: flex;
            gap: .75rem;
            align-items: end;
        }


        .receipt-main-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(220px, 1fr)
                );
            gap: 1rem;
            margin-bottom: 1rem;
        }


        .receipt-info {
            display: flex;
            align-items: center;
            gap: .75rem;

            margin: 1rem 0;
            padding: .85rem 1rem;

            border-radius: .65rem;

            background: #fffbea;
            border: 1px solid #ead99b;

            color: #796116;
        }


        .receipt-info-icon {
            width: 40px;
            height: 40px;

            display: flex;
            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            border-radius: .55rem;

            background: #fff3bd;

            font-size: 1.15rem;
        }


        .receipt-info strong {
            display: block;
            margin-bottom: .15rem;
        }


        .receipt-info span {
            display: block;
            color: #927b35;
            font-size: .78rem;
        }


        .receipt-product {
            min-width: 150px;
        }


        .receipt-product strong {
            display: block;
        }


        .product-sku {
            display: block;

            margin-top: .2rem;

            direction: ltr;

            color: var(--text-muted);

            font-size: .68rem;
        }


        .tracking-badges {
            display: flex;
            gap: .3rem;
            flex-wrap: wrap;

            margin-top: .35rem;
        }


        .tracking-badge {
            display: inline-flex;

            padding: .18rem .4rem;

            border-radius: 20px;

            background: #f5f1df;

            color: #8c721d;

            font-size: .65rem;
            font-weight: 700;
        }


        .field-required-note {
            display: block;

            margin-top: .3rem;

            color: #9a7b19;

            font-size: .67rem;
        }


        .numeric-cell,
        .numeric-input {
            direction: ltr !important;

            font-variant-numeric:
                tabular-nums;
        }


        .numeric-cell {
            text-align: center;
            white-space: nowrap;
        }


        .numeric-input {
            min-width: 95px;
            text-align: right;
        }


        .date-input {
            min-width: 145px;
        }


        .cost-input-wrap {
            position: relative;
            min-width: 125px;
        }


        .cost-input-wrap input {
            padding-left: 42px;
        }


        .cost-input-wrap span {
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


        .receipt-actions {
            display: flex;
            gap: .75rem;

            margin-top: 1rem;
        }


        @media (max-width: 768px) {

            .order-select-form {
                align-items: stretch;
                flex-direction: column;
            }


            .order-select-form .form-group {
                min-width: 0 !important;
            }


            .receipt-actions {
                flex-direction: column;
            }


            .receipt-actions .btn {
                width: 100%;
                justify-content: center;
            }

        }

    </style>


    <script>

        (() => {

            document
                .querySelectorAll('[data-item-row]')
                .forEach(row => {

                    const received =
                        row.querySelector('.received-input');

                    const accepted =
                        row.querySelector('.accepted-input');

                    const rejected =
                        row.querySelector('.rejected-input');


                    if (
                        !received ||
                        !accepted ||
                        !rejected
                    ) {
                        return;
                    }


                    const numberValue = value => {

                        const parsed =
                            Number.parseFloat(value);

                        return Number.isFinite(parsed)
                            ? parsed
                            : 0;

                    };


                    /*
                    |--------------------------------------------------------------------------
                    | Accepted / Rejected
                    |--------------------------------------------------------------------------
                    |
                    | المقبول + المرفوض يجب ألا يتجاوز المستلم.
                    |
                    */

                    const validateQuantities = () => {

                        const receivedQty =
                            numberValue(received.value);

                        const acceptedQty =
                            numberValue(accepted.value);

                        const rejectedQty =
                            numberValue(rejected.value);


                        accepted.setCustomValidity('');
                        rejected.setCustomValidity('');


                        if (
                            acceptedQty + rejectedQty >
                            receivedQty + 0.000001
                        ) {

                            const message =
                                'مجموع المقبول والمرفوض لا يمكن أن يتجاوز الكمية المستلمة.';

                            accepted.setCustomValidity(message);
                            rejected.setCustomValidity(message);

                        }

                    };


                    received.addEventListener(
                        'input',
                        validateQuantities
                    );

                    accepted.addEventListener(
                        'input',
                        validateQuantities
                    );

                    rejected.addEventListener(
                        'input',
                        validateQuantities
                    );


                    validateQuantities();

                });

        })();

    </script>

@endsection