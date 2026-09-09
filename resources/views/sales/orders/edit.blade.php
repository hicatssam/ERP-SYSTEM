@extends('layouts.app')

@section('title', 'تعديل الطلب')

@section('content')

@php
    $orderNumber = $order->order_number ?? $order->id;

    $orderStatus = $order->status instanceof \BackedEnum
        ? $order->status->value
        : (string) $order->status;

    $paymentStatusValue = $order->payment_status instanceof \BackedEnum
        ? $order->payment_status->value
        : (string) ($order->payment_status ?? '');

    $paymentArrangementValue = $order->payment_arrangement instanceof \BackedEnum
        ? $order->payment_arrangement->value
        : (string) ($order->payment_arrangement ?? '');

    $statusLabels = [
        'draft' => 'مسودة',
        'confirmed' => 'مؤكد',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
    ];

    $paymentStatusLabels = [
        'payment_pending' => 'بانتظار الدفع',
        'pending' => 'بانتظار الدفع',
        'partially_paid' => 'مدفوع جزئيًا',
        'paid' => 'مدفوع',
        'refunded' => 'مسترد',
        'failed' => 'فشل الدفع',
    ];

    $paymentArrangementOptions = collect(\App\Enums\PaymentArrangement::cases())
        ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
        ->all();

    $statusLabel = is_object($order->status) && method_exists($order->status, 'label')
        ? $order->status->label()
        : ($statusLabels[$orderStatus] ?? 'غير محدد');

    $paymentStatusLabel = is_object($order->payment_status) && method_exists($order->payment_status, 'label')
        ? $order->payment_status->label()
        : ($paymentStatusLabels[$paymentStatusValue] ?? 'غير محدد');

    $paymentArrangementLabel = is_object($order->payment_arrangement) && method_exists($order->payment_arrangement, 'label')
        ? $order->payment_arrangement->label()
        : ($paymentArrangementOptions[$paymentArrangementValue] ?? 'غير محدد');

    $customerLabel = $order->customer?->name ?? 'عميل نقدي';

    if ($order->customer?->phone) {
        $customerLabel .= ' — ' . $order->customer->phone;
    }

    $products = $products ?? collect();
@endphp


<div class="page-header order-page-header">

    <div>
        <h1 class="page-heading">تعديل الطلب</h1>

        <p class="page-subheading">
            <a href="{{ route('orders.index') }}">الطلبات</a>
            <span>‹</span>

            <a href="{{ route('orders.show', $order) }}">
                #{{ $orderNumber }}
            </a>

            <span>‹ تعديل</span>
        </p>
    </div>

    <a
        href="{{ route('orders.show', $order) }}"
        class="btn btn-ghost btn-sm"
    >
        العودة للطلب
    </a>

</div>


@if($errors->any())
    <div class="order-alert order-alert-danger">
        <strong>تعذر حفظ التعديلات:</strong>

        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


@if(session('error'))
    <div class="order-alert order-alert-danger">
        {{ session('error') }}
    </div>
@endif


@if(session('success'))
    <div class="order-alert order-alert-success">
        {{ session('success') }}
    </div>
@endif


<form
    action="{{ route('orders.update', $order) }}"
    method="POST"
    id="orderEditForm"
>
    @csrf
    @method('PUT')


    <div class="order-form-wrap">

        {{-- =========================================================
             بيانات الطلب
        ========================================================= --}}
        <section class="card">

            <div class="card-header">
                <span class="card-title">
                    بيانات الطلب
                </span>
            </div>


            <div class="card-body order-grid order-grid-2">

                <div class="form-group">

                    <label class="form-label">
                        العميل
                    </label>

                    <input
                        class="form-input"
                        value="{{ $customerLabel }}"
                        disabled
                    >

                    <input
                        type="hidden"
                        name="customer_id"
                        value="{{ old('customer_id', $order->customer_id) }}"
                    >

                    <small class="form-help">
                        لا يمكن تغيير العميل بعد إنشاء الطلب.
                    </small>

                </div>


                <div class="form-group">

                    <label class="form-label">
                        الفرع
                    </label>

                    <input
                        class="form-input"
                        value="{{ $order->location?->name ?? 'غير محدد' }}"
                        disabled
                    >

                    <input
                        type="hidden"
                        name="location_id"
                        value="{{ old('location_id', $order->location_id) }}"
                    >

                    <small class="form-help">
                        الطلب مرتبط بالفرع الذي أُنشئ منه.
                    </small>

                </div>


                <div class="form-group">

                    <label class="form-label">
                        حالة الطلب
                    </label>

                    <input
                        class="form-input"
                        value="{{ $statusLabel }}"
                        disabled
                    >

                    <small class="form-help">
                        تُغيّر الحالة من أزرار تأكيد أو إكمال أو إلغاء الطلب.
                    </small>

                </div>


                <div class="form-group">

                    <label
                        for="payment_arrangement"
                        class="form-label"
                    >
                        ترتيب الدفع *
                    </label>

                    <select
                        name="payment_arrangement"
                        id="payment_arrangement"
                        class="form-select @error('payment_arrangement') is-invalid @enderror"
                        required
                    >

                        @if(
                            $paymentArrangementValue
                            && ! array_key_exists(
                                $paymentArrangementValue,
                                $paymentArrangementOptions
                            )
                        )
                            <option
                                value="{{ $paymentArrangementValue }}"
                                selected
                            >
                                {{ $paymentArrangementLabel }}
                            </option>
                        @endif


                        @foreach($paymentArrangementOptions as $value => $label)

                            <option
                                value="{{ $value }}"
                                @selected(
                                    old(
                                        'payment_arrangement',
                                        $paymentArrangementValue
                                    ) === $value
                                )
                            >
                                {{ $label }}
                            </option>

                        @endforeach

                    </select>

                    @error('payment_arrangement')
                        <span class="form-error">
                            {{ $message }}
                        </span>
                    @enderror

                </div>


                <div class="form-group">

                    <label class="form-label">
                        حالة الدفع
                    </label>

                    <input
                        class="form-input"
                        value="{{ $paymentStatusLabel }}"
                        disabled
                    >

                    <small class="form-help">
                        تتحدث حالة الدفع من عملية الدفع أو الفاتورة.
                    </small>

                </div>


                <div class="form-group">

                    <label class="form-label">
                        تاريخ إنشاء الطلب
                    </label>

                    <input
                        class="form-input"
                        value="{{ $order->created_at?->format('Y-m-d H:i') ?? '—' }}"
                        disabled
                    >

                </div>

            </div>

        </section>


        {{-- =========================================================
             عناصر الطلب
        ========================================================= --}}
        <section
            class="card"
            id="orderItemsSection"
        >

            <div class="card-header">

                <span class="card-title">
                    عناصر الطلب
                </span>

                <span class="items-edit-hint">
                    عدّل الكمية ثم اضغط حفظ التعديلات
                </span>

            </div>


            <div class="table-wrap">

                <table class="data-table order-items-table">

                    <thead>
                        <tr>
                            <th>المنتج</th>
                            <th>الكمية *</th>
                            <th>سعر الوحدة (₪) *</th>
                            <th>إجمالي العنصر (₪)</th>
                        </tr>
                    </thead>


                    <tbody>

                        @forelse($order->items as $index => $item)

                            @php
                                $productName =
                                    $item->product?->name_ar
                                    ?? $item->product?->name
                                    ?? $item->product_name
                                    ?? 'منتج غير محدد';

                                $itemLineTotal = (float) (
                                    $item->line_total
                                    ?? (
                                        (float) $item->quantity
                                        * (float) $item->unit_price
                                    )
                                );

                                $productIdKey =
                                    "items.$index.product_id";

                                $productNameKey =
                                    "items.$index.product_name";

                                $quantityKey =
                                    "items.$index.quantity";

                                $unitPriceKey =
                                    "items.$index.unit_price";
                            @endphp


                            <tr data-order-item-row>

                                <td>

                                    <input
                                        type="hidden"
                                        name="items[{{ $index }}][id]"
                                        value="{{ $item->id }}"
                                    >


                                    @if($products->isNotEmpty())

                                        <select
                                            name="items[{{ $index }}][product_id]"
                                            class="form-select @if($errors->has($productIdKey)) is-invalid @endif"
                                            required
                                        >

                                            @foreach($products as $product)

                                                <option
                                                    value="{{ $product->id }}"
                                                    @selected(
                                                        (string) old(
                                                            $productIdKey,
                                                            $item->product_id
                                                        )
                                                        ===
                                                        (string) $product->id
                                                    )
                                                >
                                                    {{ $product->name_ar ?? $product->name }}
                                                </option>

                                            @endforeach

                                        </select>

                                    @else

                                        <input
                                            class="form-input"
                                            value="{{ $productName }}"
                                            disabled
                                        >

                                        <input
                                            type="hidden"
                                            name="items[{{ $index }}][product_id]"
                                            value="{{ old($productIdKey, $item->product_id) }}"
                                        >

                                    @endif


                                    <input
                                        type="hidden"
                                        name="items[{{ $index }}][product_name]"
                                        value="{{ old($productNameKey, $productName) }}"
                                    >


                                    @if($errors->has($productIdKey))
                                        <span class="form-error">
                                            {{ $errors->first($productIdKey) }}
                                        </span>
                                    @endif

                                </td>


                                <td>

                                    <input
                                        type="number"
                                        name="items[{{ $index }}][quantity]"
                                        class="form-input quantity-edit-input @if($errors->has($quantityKey)) is-invalid @endif"
                                        min="0.001"
                                        step="0.001"
                                        value="{{ old($quantityKey, $item->quantity) }}"
                                        data-item-quantity
                                        required
                                    >

                                    @if($errors->has($quantityKey))
                                        <span class="form-error">
                                            {{ $errors->first($quantityKey) }}
                                        </span>
                                    @endif

                                </td>


                                <td>

                                    <input
                                        type="number"
                                        name="items[{{ $index }}][unit_price]"
                                        class="form-input @if($errors->has($unitPriceKey)) is-invalid @endif"
                                        min="0"
                                        step="0.01"
                                        value="{{ old($unitPriceKey, $item->unit_price) }}"
                                        data-item-price
                                        required
                                    >

                                    @if($errors->has($unitPriceKey))
                                        <span class="form-error">
                                            {{ $errors->first($unitPriceKey) }}
                                        </span>
                                    @endif

                                </td>


                                <td>

                                    <input
                                        class="form-input item-line-total"
                                        value="{{ number_format($itemLineTotal, 2, '.', '') }}"
                                        data-item-total
                                        readonly
                                    >

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="4"
                                    class="empty-items-cell"
                                >
                                    لا توجد عناصر في هذا الطلب.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>


        {{-- =========================================================
             الخصم والضريبة والإجماليات
        ========================================================= --}}
        <section class="card">

            <div class="card-header">
                <span class="card-title">
                    الخصم والضريبة والإجماليات
                </span>
            </div>


            <div class="card-body">

                <div class="order-grid order-grid-2">

                    <div class="form-group">

                        <label
                            for="discount_amount"
                            class="form-label"
                        >
                            قيمة الخصم (₪)
                        </label>

                        <input
                            type="number"
                            name="discount_amount"
                            id="discount_amount"
                            class="form-input @error('discount_amount') is-invalid @enderror"
                            min="0"
                            step="0.01"
                            value="{{ old('discount_amount', $order->discount_amount ?? 0) }}"
                            data-discount-amount
                        >

                        @error('discount_amount')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="form-group">

                        <label
                            for="tax_amount"
                            class="form-label"
                        >
                            قيمة الضريبة (₪)
                        </label>

                        <input
                            type="number"
                            name="tax_amount"
                            id="tax_amount"
                            class="form-input @error('tax_amount') is-invalid @enderror"
                            min="0"
                            step="0.01"
                            value="{{ old('tax_amount', $order->tax_amount ?? 0) }}"
                            data-tax-amount
                        >

                        @error('tax_amount')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>

                </div>


                <div class="order-totals order-space-top">

                    <div class="order-total-row">

                        <span>
                            المجموع الفرعي
                        </span>

                        <div class="money-input-wrap">

                            <span>₪</span>

                            <input
                                name="subtotal"
                                value="{{ old('subtotal', $order->subtotal ?? 0) }}"
                                data-order-subtotal
                                readonly
                            >

                        </div>

                    </div>


                    <div class="order-total-row order-total-final">

                        <span>
                            الإجمالي النهائي
                        </span>

                        <div class="money-input-wrap">

                            <span>₪</span>

                            <input
                                name="total_amount"
                                value="{{ old('total_amount', $order->total_amount ?? 0) }}"
                                data-order-total
                                readonly
                            >

                        </div>

                    </div>

                </div>


                <small class="form-help order-space-top">
                    يتم تحديث الإجماليات تلقائيًا عند تغيير الكمية أو سعر الوحدة أو الخصم أو الضريبة.
                </small>

            </div>

        </section>


        {{-- =========================================================
             الملاحظات
        ========================================================= --}}
        <section class="card">

            <div class="card-header">
                <span class="card-title">
                    ملاحظات الطلب
                </span>
            </div>


            <div class="card-body">

                <div class="form-group">

                    <label
                        for="notes"
                        class="form-label"
                    >
                        الملاحظات
                    </label>

                    <textarea
                        name="notes"
                        id="notes"
                        class="form-textarea @error('notes') is-invalid @enderror"
                        rows="4"
                        maxlength="2000"
                        placeholder="أضف أي ملاحظات مرتبطة بالطلب..."
                    >{{ old('notes', $order->notes) }}</textarea>

                    @error('notes')
                        <span class="form-error">
                            {{ $message }}
                        </span>
                    @enderror

                </div>

            </div>

        </section>


        <div class="sticky-actions">

            <a
                href="{{ route('orders.show', $order) }}"
                class="btn btn-ghost"
            >
                إلغاء
            </a>

            <button
                class="btn btn-gold"
                type="submit"
                @disabled($order->items->isEmpty())
            >
                حفظ التعديلات
            </button>

        </div>

    </div>

</form>


<style>
    .order-page-header {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:1rem;
    }

    .order-form-wrap {
        display:grid;
        gap:1.5rem;
        max-width:1100px;
    }

    .order-grid {
        display:grid;
        gap:1rem;
    }

    .order-grid-2 {
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .order-space-top {
        margin-top:1rem;
    }

    .form-help {
        display:block;
        margin-top:.35rem;
        color:var(--text-muted);
        font-size:.78rem;
    }

    .form-error {
        display:block;
        margin-top:.35rem;
        color:#dc3545;
        font-size:.82rem;
    }

    .is-invalid {
        border-color:#dc3545 !important;
    }

    .order-alert {
        max-width:1100px;
        margin-bottom:1.25rem;
        padding:1rem 1.2rem;
        border-radius:12px;
    }

    .order-alert ul {
        margin:.6rem 0 0;
        padding-inline-start:1.2rem;
    }

    .order-alert-danger {
        color:#dc3545;
        background:rgba(220,53,69,.1);
        border:1px solid rgba(220,53,69,.45);
    }

    .order-alert-success {
        color:#198754;
        background:rgba(25,135,84,.1);
        border:1px solid rgba(25,135,84,.4);
    }

    .items-edit-hint {
        color:var(--text-muted);
        font-size:.72rem;
    }

    .quantity-edit-input:focus {
        border-color:var(--gold) !important;
        box-shadow:0 0 0 3px rgba(212,175,55,.12);
    }

    .order-items-table .form-input,
    .order-items-table .form-select {
        min-width:130px;
    }

    .order-items-table td:first-child .form-input,
    .order-items-table td:first-child .form-select {
        min-width:220px;
    }

    .item-line-total {
        background:var(--surface-2,rgba(0,0,0,.03));
        font-weight:700;
    }

    .empty-items-cell {
        padding:2rem !important;
        text-align:center;
        color:var(--text-muted);
    }

    .order-totals {
        display:grid;
        gap:.75rem;
        max-width:520px;
        margin-inline-start:auto;
    }

    .order-total-row {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:1rem;
        padding:.85rem 1rem;
        background:var(--surface-2,rgba(0,0,0,.03));
        border:1px solid var(--border);
        border-radius:10px;
    }

    .order-total-final {
        color:var(--gold);
        background:rgba(212,175,55,.1);
        border-color:rgba(212,175,55,.4);
        font-size:1.05rem;
        font-weight:700;
    }

    .money-input-wrap {
        display:flex;
        align-items:center;
        gap:.35rem;
        font-weight:700;
    }

    .money-input-wrap input {
        width:115px;
        padding:0;
        color:inherit;
        background:transparent;
        border:0;
        outline:0;
        text-align:end;
        font:inherit;
    }

    .sticky-actions {
        position:sticky;
        bottom:0;
        z-index:20;
        display:flex;
        justify-content:flex-end;
        gap:.75rem;
        padding:1rem;
        background:var(--surface);
        border:1px solid var(--border);
        border-radius:12px;
        backdrop-filter:blur(10px);
    }

    @media(max-width:800px) {
        .order-page-header {
            align-items:flex-start;
            flex-direction:column;
        }

        .order-grid-2 {
            grid-template-columns:1fr;
        }

        .sticky-actions {
            position:static;
        }
    }
</style>


<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.querySelectorAll('[data-order-item-row]');
    const discountInput = document.querySelector('[data-discount-amount]');
    const taxInput = document.querySelector('[data-tax-amount]');
    const subtotalInput = document.querySelector('[data-order-subtotal]');
    const totalInput = document.querySelector('[data-order-total]');

    const numberValue = (input) => {
        const value = Number.parseFloat(input?.value ?? '0');

        return Number.isFinite(value)
            ? value
            : 0;
    };

    const money = (value) =>
        Math.max(0, value).toFixed(2);

    const calculateTotals = () => {
        let subtotal = 0;

        rows.forEach((row) => {
            const quantity =
                numberValue(
                    row.querySelector('[data-item-quantity]')
                );

            const unitPrice =
                numberValue(
                    row.querySelector('[data-item-price]')
                );

            const lineTotal =
                Math.max(
                    0,
                    quantity * unitPrice
                );

            subtotal += lineTotal;

            const itemTotal =
                row.querySelector('[data-item-total]');

            if (itemTotal) {
                itemTotal.value =
                    money(lineTotal);
            }
        });

        const discount =
            numberValue(discountInput);

        const tax =
            numberValue(taxInput);

        const total =
            subtotal - discount + tax;

        if (subtotalInput) {
            subtotalInput.value =
                money(subtotal);
        }

        if (totalInput) {
            totalInput.value =
                money(total);
        }
    };

    rows.forEach((row) => {
        row
            .querySelector('[data-item-quantity]')
            ?.addEventListener(
                'input',
                calculateTotals
            );

        row
            .querySelector('[data-item-price]')
            ?.addEventListener(
                'input',
                calculateTotals
            );
    });

    discountInput
        ?.addEventListener(
            'input',
            calculateTotals
        );

    taxInput
        ?.addEventListener(
            'input',
            calculateTotals
        );

    calculateTotals();


    /*
    |--------------------------------------------------------------------------
    | التركيز على الكميات عند الدخول من Popup نقص المخزون
    |--------------------------------------------------------------------------
    */

    const shouldFocusQuantities =
        new URLSearchParams(
            window.location.search
        ).get('focus') === 'quantities';

    if (shouldFocusQuantities) {
        const itemsSection =
            document.getElementById(
                'orderItemsSection'
            );

        const firstQuantityInput =
            document.querySelector(
                '.quantity-edit-input'
            );

        itemsSection?.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        window.setTimeout(() => {
            firstQuantityInput?.focus();
            firstQuantityInput?.select();
        }, 350);
    }
});
</script>

@endsection