@extends('layouts.app')

@section('title', 'تسجيل دفعة مورد')

@section('content')

    <div class="page-header">
        <div>
            <h1 class="page-heading">تسجيل دفعة مورد</h1>

            <p class="page-subheading">
                <a href="{{ route('supplier-payments.index') }}">
                    دفعات الموردين
                </a>
                &laquo; جديد
            </p>
        </div>
    </div>

    @include('procurement.partials.flash')

    @php
        $paymentStyleMap = [
            'فودافون' => ['bg' => '#e60000', 'icon' => '📱'],
            'تحويل بنكي' => ['bg' => '#0f766e', 'icon' => '🏦'],
            'نقد' => ['bg' => '#7c3aed', 'icon' => '💵'],
            'فلسطين' => ['bg' => '#1e3a8a', 'icon' => '🏛️'],
            'بال باي' => ['bg' => '#db2777', 'icon' => '💳'],
            'ائتمانية' => ['bg' => '#334155', 'icon' => '💳'],
            'الإسلامي' => ['bg' => '#166534', 'icon' => '🕌'],
            'سيدار' => ['bg' => '#059669', 'icon' => '📶'],
            'جوال باي' => ['bg' => '#7e22ce', 'icon' => '📲'],
            'باي بوكس' => ['bg' => '#475569', 'icon' => '💰'],
        ];

        $getStyle = function ($nameAr) use ($paymentStyleMap) {
            $nameAr = (string) $nameAr;

            foreach ($paymentStyleMap as $key => $style) {
                if (str_contains($nameAr, $key)) {
                    return $style;
                }
            }

            return [
                'bg' => '#6b7280',
                'icon' => '💰',
            ];
        };
    @endphp


    <form
        method="POST"
        action="{{ route('supplier-payments.store') }}"
        id="supplier-payment-form"
    >
        @csrf

        <input
            type="hidden"
            name="supplier_id"
            id="supplier-id"
            value="{{ old('supplier_id', $selectedInvoice?->supplier_id) }}"
        >


        <div class="card">
            <div class="card-body">

                <div class="supplier-payment-data-grid">

                    {{-- فاتورة المورد --}}
                    <div class="form-group">

                        <label class="form-label" for="invoice-id">
                            فاتورة المورد *
                        </label>

                        <select
                            class="form-input"
                            name="supplier_invoice_id"
                            id="invoice-id"
                            required
                        >
                            <option value="">
                                اختر فاتورة
                            </option>

                            @foreach ($invoices as $invoice)

                                <option
                                    value="{{ $invoice->id }}"

                                    data-supplier="{{ $invoice->supplier_id }}"

                                    data-remaining="{{ number_format(
                                        (float) $invoice->remaining_amount,
                                        2,
                                        '.',
                                        ''
                                    ) }}"

                                    data-currency="{{ $invoice->currency_id }}"

                                    data-currency-code="{{ $invoice->currency?->code }}"

                                    data-exchange-rate="{{ $invoice->exchange_rate }}"

                                    @selected(
                                        (string) old(
                                            'supplier_invoice_id',
                                            $selectedInvoice?->id
                                        ) === (string) $invoice->id
                                    )
                                >

                                    {{ $invoice->invoice_number }}
                                    —
                                    {{ $invoice->supplier?->name }}
                                    —
                                    المتبقي
                                    {{ number_format((float) $invoice->remaining_amount, 2) }}
                                    {{ $invoice->currency?->displayName() }}

                                </option>

                            @endforeach

                        </select>

                        @error('supplier_invoice_id')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    {{-- عملة الدفعة --}}
                    <div class="form-group">

                        <label class="form-label" for="currency-id">
                            عملة الدفعة *
                        </label>

                        <select
                            class="form-input"
                            name="currency_id"
                            id="currency-id"
                            required
                        >

                            @foreach ($currencies as $currency)

                                <option
                                    value="{{ $currency->id }}"
                                    @selected(
                                        (string) old(
                                            'currency_id',
                                            $selectedInvoice?->currency_id
                                        ) === (string) $currency->id
                                    )
                                >

                                    {{ $currency->displayName() }}
                                    ({{ $currency->code }})

                                </option>

                            @endforeach

                        </select>

                        @error('currency_id')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    {{-- سعر الصرف --}}
                    <div class="form-group">

                        <label class="form-label" for="exchange-rate">
                            سعر الصرف
                        </label>

                        <input
                            class="form-input numeric-input"
                            id="exchange-rate"
                            type="number"
                            lang="en-US"
                            dir="ltr"
                            min="0.00000001"
                            step="0.00000001"
                            name="exchange_rate"
                            value="{{ old(
                                'exchange_rate',
                                $selectedInvoice?->exchange_rate
                            ) }}"
                            inputmode="decimal"
                            autocomplete="off"
                        >

                        @error('exchange_rate')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    {{-- قيمة الدفعة --}}
                    <div class="form-group">

                        <label class="form-label" for="amount">
                            قيمة الدفعة *
                        </label>

                        <div class="amount-input-wrapper">

                            <input
                                class="form-input numeric-input"
                                id="amount"
                                type="number"
                                lang="en-US"
                                dir="ltr"
                                min="0.01"
                                step="0.01"
                                name="amount"
                                required
                                inputmode="decimal"
                                autocomplete="off"

                                value="{{ old(
                                    'amount',
                                    $selectedInvoice
                                        ? number_format(
                                            (float) $selectedInvoice->remaining_amount,
                                            2,
                                            '.',
                                            ''
                                        )
                                        : ''
                                ) }}"

                                @if($selectedInvoice)
                                    max="{{ number_format(
                                        (float) $selectedInvoice->remaining_amount,
                                        2,
                                        '.',
                                        ''
                                    ) }}"
                                @endif
                            >

                            <span
                                class="amount-currency"
                                id="amount-currency-code"
                            >
                                {{ $selectedInvoice?->currency?->displayName() }}
                            </span>

                        </div>


                        <div
                            id="remaining-balance-info"
                            class="remaining-balance-info"
                            @if(!$selectedInvoice)
                                style="display:none"
                            @endif
                        >

                            الحد الأقصى للدفع:

                            <strong
                                dir="ltr"
                                id="remaining-balance-value"
                            >
                                @if($selectedInvoice)
                                    {{ $selectedInvoice->currency?->displayName() }}
                                    {{ number_format(
                                        (float) $selectedInvoice->remaining_amount,
                                        2
                                    ) }}
                                @endif
                            </strong>

                        </div>


                        <div
                            id="amount-client-error"
                            class="amount-client-error"
                            style="display:none"
                        ></div>


                        @error('amount')
                            <span
                                class="form-error"
                                style="display:block;margin-top:.35rem"
                            >
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    {{-- التاريخ --}}
                    <div class="form-group">

                        <label class="form-label">
                            التاريخ *
                        </label>

                        <input
                            class="form-input"
                            type="date"
                            name="payment_date"
                            required
                            value="{{ old(
                                'payment_date',
                                now()->toDateString()
                            ) }}"
                        >

                        @error('payment_date')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    {{-- طريقة الدفع --}}
                    <div
                        class="form-group"
                        style="grid-column:1/-1"
                    >

                        <label class="form-label">
                            طريقة الدفع *
                        </label>


                        <div class="payment-methods-grid">

                            @forelse ($paymentMethods as $method)

                                @php
                                    $style = $getStyle($method->name_ar);
                                @endphp


                                <label class="payment-method-card">

                                    <input
                                        type="radio"
                                        name="payment_method_id"
                                        value="{{ $method->id }}"
                                        @checked(
                                            (string) old('payment_method_id')
                                            ===
                                            (string) $method->id
                                        )
                                        required
                                    >


                                    <span
                                        class="payment-method-icon"
                                        style="background: {{ $style['bg'] }}"
                                    >
                                        {{ $style['icon'] }}
                                    </span>


                                    <span class="payment-method-labels">

                                        <span class="payment-method-name-ar">
                                            {{ $method->name_ar ?: $method->name }}
                                        </span>


                                        @if($method->name_en ?? null)

                                            <span class="payment-method-name-en">
                                                {{ $method->name_en }}
                                            </span>

                                        @elseif(
                                            $method->name
                                            &&
                                            $method->name !== $method->name_ar
                                        )

                                            <span class="payment-method-name-en">
                                                {{ $method->name }}
                                            </span>

                                        @endif

                                    </span>

                                </label>


                            @empty

                                <div class="empty-payment-methods">
                                    لا توجد طرق دفع متاحة.
                                </div>

                            @endforelse

                        </div>


                        @error('payment_method_id')
                            <span
                                class="form-error"
                                style="display:block;margin-top:.75rem"
                            >
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    {{-- رقم المرجع --}}
                    <div class="form-group">

                        <label
                            class="form-label"
                            for="reference-number"
                        >
                            رقم المرجع
                        </label>

                        <input
                            class="form-input"
                            id="reference-number"
                            name="reference_number"
                            value="{{ old('reference_number') }}"
                            placeholder="رقم شيك / تحويل / عملية"
                        >

                        @error('reference_number')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>

                </div>


                {{-- ملاحظات --}}
                <div
                    class="form-group"
                    style="margin-top:1rem"
                >

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

            </div>
        </div>


        {{-- الأزرار --}}
        <div class="supplier-payment-actions">

            <button
                class="btn btn-gold"
                id="submit-payment-btn"
                type="submit"
            >
                تسجيل الدفعة
            </button>

            <a
                class="btn btn-ghost"
                href="{{ route('supplier-payments.index') }}"
            >
                إلغاء
            </a>

        </div>

    </form>


    <style>

        .supplier-payment-data-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            align-items: start;
        }


        /*
        |--------------------------------------------------------------------------
        | Numeric Fields
        |--------------------------------------------------------------------------
        */

        .numeric-input {
            direction: ltr !important;
            text-align: right !important;
            unicode-bidi: isolate !important;
            font-variant-numeric: lining-nums tabular-nums;
        }


        .amount-input-wrapper {
            position: relative;
        }


        .amount-input-wrapper #amount {
            padding-left: 65px;
        }


        .amount-currency {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);

            direction: ltr;

            font-size: .78rem;
            font-weight: 700;

            color: #64748b;

            pointer-events: none;
        }


        .remaining-balance-info {
            margin-top: .45rem;
            padding: .55rem .7rem;

            border-radius: .5rem;

            background: #f8fafc;
            border: 1px solid #e5e7eb;

            color: #64748b;
            font-size: .78rem;
        }


        .remaining-balance-info strong {
            direction: ltr;
            color: #111827;
            margin-inline-start: .25rem;
        }


        .amount-client-error {
            margin-top: .45rem;
            padding: .55rem .7rem;

            border: 1px solid #fecaca;
            border-radius: .5rem;

            background: #fef2f2;

            color: #b91c1c;

            font-size: .8rem;
            font-weight: 600;
        }


        .numeric-input.is-invalid {
            border-color: #dc2626 !important;
            box-shadow: 0 0 0 1px #dc2626 !important;
        }


        /*
        |--------------------------------------------------------------------------
        | Payment Methods
        |--------------------------------------------------------------------------
        */

        .payment-methods-grid {
            display: grid;

            grid-template-columns:
                repeat(auto-fit, minmax(200px, 1fr));

            gap: 1rem;

            margin-top: .5rem;
        }


        .payment-method-card {
            position: relative;

            display: flex;
            align-items: center;

            gap: .75rem;

            min-height: 76px;

            padding: 1rem 1.25rem;

            border: 2px solid #e6e6e6;
            border-radius: .75rem;

            cursor: pointer;

            background: #fff;

            transition:
                border-color .15s,
                box-shadow .15s,
                transform .15s,
                background .15s;
        }


        .payment-method-card:hover {
            border-color: #d4af37;
            transform: translateY(-1px);
        }


        .payment-method-card input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }


        .payment-method-card:has(input:checked) {
            border-color: #d4af37;

            background: #fdf9ee;

            box-shadow:
                0 0 0 1px #d4af37;
        }


        .payment-method-icon {
            width: 46px;
            height: 46px;

            flex-shrink: 0;

            border-radius: .5rem;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 1.4rem;

            color: #fff;
        }


        .payment-method-labels {
            min-width: 0;

            display: flex;
            flex-direction: column;

            gap: .15rem;
        }


        .payment-method-name-ar {
            font-weight: 700;
            color: #222;
        }


        .payment-method-name-en {
            direction: ltr;

            text-align: right;

            font-size: .75rem;

            color: #999;
        }


        .empty-payment-methods {
            grid-column: 1 / -1;

            padding: 1rem;

            border: 1px dashed #d1d5db;
            border-radius: .75rem;

            color: #6b7280;

            text-align: center;
        }


        /*
        |--------------------------------------------------------------------------
        | Actions
        |--------------------------------------------------------------------------
        */

        .supplier-payment-actions {
            display: flex;

            gap: .75rem;

            margin-top: 1rem;

            justify-content: flex-start;
        }


        @media (max-width: 768px) {

            .supplier-payment-data-grid {
                grid-template-columns: 1fr;
            }

            .payment-methods-grid {
                grid-template-columns: 1fr;
            }

            .supplier-payment-actions {
                flex-direction: column;
            }

            .supplier-payment-actions .btn {
                width: 100%;
                justify-content: center;
            }

        }

    </style>


    <script>

        (() => {

            const form =
                document.getElementById(
                    'supplier-payment-form'
                );

            const invoice =
                document.getElementById(
                    'invoice-id'
                );

            const supplier =
                document.getElementById(
                    'supplier-id'
                );

            const currency =
                document.getElementById(
                    'currency-id'
                );

            const exchangeRate =
                document.getElementById(
                    'exchange-rate'
                );

            const amount =
                document.getElementById(
                    'amount'
                );

            const currencyCode =
                document.getElementById(
                    'amount-currency-code'
                );

            const remainingInfo =
                document.getElementById(
                    'remaining-balance-info'
                );

            const remainingValue =
                document.getElementById(
                    'remaining-balance-value'
                );

            const amountError =
                document.getElementById(
                    'amount-client-error'
                );


            let currentRemaining = 0;
            let currentCurrencyCode = '';


            /*
            |--------------------------------------------------------------------------
            | Numeric parser
            |--------------------------------------------------------------------------
            */

            const numericValue = value => {

                const parsed =
                    Number.parseFloat(value);

                return Number.isFinite(parsed)
                    ? parsed
                    : 0;

            };


            /*
            |--------------------------------------------------------------------------
            | Money formatter
            |--------------------------------------------------------------------------
            */

            const formatMoney = value => {

                return numericValue(value)
                    .toFixed(2);

            };


            /*
            |--------------------------------------------------------------------------
            | Exchange formatter
            |--------------------------------------------------------------------------
            */

            const formatExchangeRate = value => {

                const parsed =
                    numericValue(value);

                if (parsed <= 0) {
                    return '';
                }

                return parsed
                    .toFixed(8)
                    .replace(/0+$/, '')
                    .replace(/\.$/, '');

            };


            /*
            |--------------------------------------------------------------------------
            | Clear client error
            |--------------------------------------------------------------------------
            */

            const clearAmountError = () => {

                amountError.style.display =
                    'none';

                amountError.textContent =
                    '';

                amount.classList.remove(
                    'is-invalid'
                );

            };


            /*
            |--------------------------------------------------------------------------
            | Validate amount
            |--------------------------------------------------------------------------
            */

            const validateAmount = () => {

                clearAmountError();


                if (!invoice.value) {
                    return true;
                }


                const entered =
                    numericValue(amount.value);


                if (entered <= 0) {

                    amountError.textContent =
                        'يجب أن تكون قيمة الدفعة أكبر من صفر.';

                    amountError.style.display =
                        'block';

                    amount.classList.add(
                        'is-invalid'
                    );

                    return false;
                }


                if (
                    entered >
                    currentRemaining + 0.000001
                ) {

                    amountError.textContent =
                        `قيمة الدفعة لا يمكن أن تتجاوز الرصيد المتبقي: ${formatMoney(currentRemaining)} ${currentCurrencyCode}`;

                    amountError.style.display =
                        'block';

                    amount.classList.add(
                        'is-invalid'
                    );

                    return false;
                }


                return true;

            };


            /*
            |--------------------------------------------------------------------------
            | Apply invoice data
            |--------------------------------------------------------------------------
            */

            const applyInvoice = (
                resetAmount = false
            ) => {

                clearAmountError();


                if (!invoice.value) {

                    supplier.value = '';

                    currentRemaining = 0;
                    currentCurrencyCode = '';

                    amount.removeAttribute(
                        'max'
                    );

                    currencyCode.textContent =
                        '';

                    remainingInfo.style.display =
                        'none';

                    return;

                }


                const option =
                    invoice.selectedOptions[0];


                const remaining =
                    numericValue(
                        option.dataset.remaining
                    );


                const invoiceCurrency =
                    option.dataset.currency || '';


                const invoiceCurrencyCode =
                    option.dataset.currencyCode || '';


                const invoiceExchangeRate =
                    option.dataset.exchangeRate || '';


                currentRemaining =
                    remaining;


                currentCurrencyCode =
                    invoiceCurrencyCode;


                /*
                |--------------------------------------------------------------------------
                | Supplier
                |--------------------------------------------------------------------------
                */

                supplier.value =
                    option.dataset.supplier || '';


                /*
                |--------------------------------------------------------------------------
                | Currency
                |--------------------------------------------------------------------------
                */

                if (invoiceCurrency) {

                    currency.value =
                        invoiceCurrency;

                }


                /*
                |--------------------------------------------------------------------------
                | Exchange Rate
                |--------------------------------------------------------------------------
                */

                if (invoiceExchangeRate) {

                    exchangeRate.value =
                        formatExchangeRate(
                            invoiceExchangeRate
                        );

                }


                /*
                |--------------------------------------------------------------------------
                | Max amount
                |--------------------------------------------------------------------------
                */

                amount.max =
                    formatMoney(remaining);


                /*
                |--------------------------------------------------------------------------
                | Reset amount when invoice changes
                |--------------------------------------------------------------------------
                */

                if (
                    resetAmount ||
                    !amount.value
                ) {

                    amount.value =
                        formatMoney(remaining);

                }


                /*
                |--------------------------------------------------------------------------
                | Currency badge
                |--------------------------------------------------------------------------
                */

                currencyCode.textContent =
                    invoiceCurrencyCode;


                /*
                |--------------------------------------------------------------------------
                | Remaining balance display
                |--------------------------------------------------------------------------
                */

                remainingValue.textContent =
                    `${invoiceCurrencyCode} ${formatMoney(remaining)}`;


                remainingInfo.style.display =
                    'block';


                validateAmount();

            };


            /*
            |--------------------------------------------------------------------------
            | Invoice change
            |--------------------------------------------------------------------------
            */

            invoice.addEventListener(
                'change',
                () => {

                    applyInvoice(true);

                }
            );


            /*
            |--------------------------------------------------------------------------
            | Amount live validation
            |--------------------------------------------------------------------------
            */

            amount.addEventListener(
                'input',
                validateAmount
            );


            amount.addEventListener(
                'blur',
                () => {

                    if (
                        amount.value &&
                        numericValue(amount.value) > 0
                    ) {

                        amount.value =
                            formatMoney(
                                amount.value
                            );

                    }

                    validateAmount();

                }
            );


            /*
            |--------------------------------------------------------------------------
            | Exchange Rate Blur
            |--------------------------------------------------------------------------
            */

            exchangeRate.addEventListener(
                'blur',
                () => {

                    if (
                        exchangeRate.value &&
                        numericValue(exchangeRate.value) > 0
                    ) {

                        exchangeRate.value =
                            formatExchangeRate(
                                exchangeRate.value
                            );

                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | Prevent invalid submit
            |--------------------------------------------------------------------------
            */

            form.addEventListener(
                'submit',
                event => {

                    if (!validateAmount()) {

                        event.preventDefault();

                        amount.focus();

                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | Initial state
            |--------------------------------------------------------------------------
            */

            applyInvoice(false);

        })();

    </script>

@endsection