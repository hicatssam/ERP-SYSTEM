@extends('layouts.app')

@section('title', 'طلب: ' . $order->order_number)

@section('content')

@php

    $orderStatus = $order->status instanceof \BackedEnum

        ? $order->status->value

        : (string) $order->status;

    $paymentArrangement = $order->payment_arrangement instanceof \BackedEnum

        ? $order->payment_arrangement->value

        : (string) $order->payment_arrangement;

    $statusLabel = match ($orderStatus) {

        'draft' => 'مسودة',

        'confirmed' => 'مؤكد',

        'completed' => 'مكتمل',

        'cancelled' => 'ملغي',

        default => 'حالة غير معرفة',

    };

    $statusClass = match ($orderStatus) {

        'confirmed', 'completed' => 'badge-active',

        'cancelled' => 'badge-inactive',

        default => 'badge-pending',

    };

    $paymentArrangementLabel = match ($paymentArrangement) {

        'pay_now' => 'دفع فوري',

        'deposit' => 'عربون',

        'partial_payment' => 'دفع جزئي',

        'pay_on_pickup' => 'الدفع عند الاستلام',

        'pending_verification' => 'بانتظار التحقق',

        default => 'غير محدد',

    };

    $stockErrors = $errors->get('stock');


    /*
    |--------------------------------------------------------------------------
    | الملخص المالي للطلب
    |--------------------------------------------------------------------------
    |
    | إجمالي الطلب يبقى ثابتاً.
    | صافي المدفوع يأتي من الفاتورة بعد خصم الاستردادات.
    | الاستردادات تعرض بشكل مستقل.
    |
    */
    $orderRefundedAmount = (float) \App\Models\Refund::query()
        ->where('order_type', 'order')
        ->where('order_id', $order->id)
        ->sum('amount');

    $invoicePaidAmount = (float) (
        $order->invoice?->paid_amount
        ?? 0
    );

    $invoiceRemainingAmount = (float) (
        $order->invoice?->remaining_amount
        ?? max(
            0,
            (float) $order->total_amount
            - $invoicePaidAmount
        )
    );

    $financialStatusLabel = match (true) {
        $orderRefundedAmount >= (float) $order->total_amount
            && (float) $order->total_amount > 0
                => 'مسترد بالكامل',

        $orderRefundedAmount > 0
                => 'مسترد جزئياً',

        $invoicePaidAmount >= (float) $order->total_amount
            && (float) $order->total_amount > 0
                => 'مدفوع بالكامل',

        $invoicePaidAmount > 0
                => 'مدفوع جزئياً',

        default => 'غير مدفوع',
    };

    $financialStatusClass = match ($financialStatusLabel) {
        'مدفوع بالكامل'
            => 'badge-active',

        'مدفوع جزئياً',
        'مسترد جزئياً'
            => 'badge-pending',

        'مسترد بالكامل'
            => 'badge-inactive',

        default
            => 'badge-secondary',
    };

@endphp



{{-- =========================================================

     رأس الصفحة

\========================================================= --}}

<div class="page-actions">

    <div class="page-actions-title">

        {{ $order->order_number }}

    </div>





    <div class="action-btns">

        {{-- تأكيد الطلب --}}

        @if($orderStatus === 'draft')

            @can('confirm', $order)

                <button

                    type="button"

                    class="btn btn-gold btn-sm"

                    onclick="openOrderActionModal('confirm')"

                >

                    تأكيد الطلب

                </button>

            @endcan

        @endif



        {{-- إكمال الطلب --}}

        @if($orderStatus === 'confirmed')

            @can('update', $order)

                <button

                    type="button"

                    class="btn btn-gold btn-sm"

                    onclick="openOrderActionModal('complete')"

                >

                    إكمال الطلب

                </button>

            @endcan

        @endif



        {{-- تعديل الطلب --}}

        @if($orderStatus === 'draft')

            @can('update', $order)

                <a

                    href="{{ route('orders.edit', $order) }}"

                    class="btn btn-outline btn-sm"

                >

                    تعديل الطلب

                </a>

            @endcan

        @endif



        {{-- إلغاء الطلب --}}

        @if(in_array($orderStatus, ['draft', 'confirmed'], true))

            @can('cancel', $order)

                <button

                    type="button"

                    class="btn btn-ghost btn-sm"

                    style="color:var(--error)"

                    onclick="openOrderActionModal('cancel')"

                >

                    إلغاء الطلب

                </button>

            @endcan

        @endif



        <a

            href="{{ route('orders.index') }}"

            class="btn btn-ghost btn-sm"

        >

            رجوع

        </a>

    </div>

</div>



{{-- =========================================================

     مسار الطلب - يظهر مرة واحدة للطلب كاملًا

\========================================================= --}}

<x-workflow-toolbar

    type="order"

    :record="$order"

/>



{{-- =========================================================

     تفاصيل الطلب والعناصر

\========================================================= --}}

<div class="dashboard-row">

    {{-- تفاصيل الطلب --}}

    <div class="card">

        <div class="card-header">

            <span class="card-title">

                تفاصيل الطلب

            </span>

        </div>

        <div class="card-body">

            <table class="data-table">

                <tbody>

                    <tr>

                        <td style="color:var(--text-muted)">

                            رقم الطلب

                        </td>

                        <td>

                            {{ $order->order_number }}

                        </td>

                    </tr>

                    <tr>

                        <td style="color:var(--text-muted)">

                            الفرع

                        </td>

                        <td>

                            {{ $order->location?->name ?? 'غير محدد' }}

                        </td>

                    </tr>

                    <tr>

                        <td style="color:var(--text-muted)">

                            العميل

                        </td>

                        <td>

                            {{ $order->customer?->name ?? 'عميل نقدي' }}

                        </td>

                    </tr>

                    @if(($order->order_source ?? null) === 'customer_menu')
                        <tr>
                            <td style="color:var(--text-muted)">مصدر الطلب</td>
                            <td>
                                <span class="badge badge-pending">منيو العميل</span>
                            </td>
                        </tr>
                    @endif

                    @if($order->restaurant_service_type)
                        <tr>
                            <td style="color:var(--text-muted)">نوع خدمة المطعم</td>
                            <td>
                                {{ $order->restaurant_service_type->icon() }}
                                {{ $order->restaurant_service_type->label() }}
                            </td>
                        </tr>

                        @if($order->restaurantTable)
                            <tr>
                                <td style="color:var(--text-muted)">الطاولة</td>
                                <td>
                                    {{ $order->restaurantTable->area?->name ? $order->restaurantTable->area->name . ' — ' : '' }}
                                    {{ $order->restaurantTable->displayName() }}
                                </td>
                            </tr>
                        @endif

                        @if($order->guest_count)
                            <tr>
                                <td style="color:var(--text-muted)">عدد الضيوف</td>
                                <td>{{ $order->guest_count }}</td>
                            </tr>
                        @endif

                        @if($order->waiter)
                            <tr>
                                <td style="color:var(--text-muted)">الموظف / النادل</td>
                                <td>{{ $order->waiter?->employee?->full_name ?? $order->waiter?->display_name }}</td>
                            </tr>
                        @endif
                    @endif

                    <tr>

                        <td style="color:var(--text-muted)">

                            ترتيب الدفع

                        </td>

                        <td>

                            {{ $paymentArrangementLabel }}

                        </td>

                    </tr>

                    <tr>

                        <td style="color:var(--text-muted)">

                            الإجمالي

                        </td>

                        <td>

                            <strong>

                                ₪{{ number_format((float) $order->total_amount, 2) }}

                            </strong>

                        </td>

                    </tr>

                    <tr>

                        <td style="color:var(--text-muted)">

                            الحالة

                        </td>

                        <td>

                            <span class="badge {{ $statusClass }}">

                                {{ $statusLabel }}

                            </span>

                        </td>

                    </tr>

                    @if($order->invoice)

                        <tr>
                            <td style="color:var(--text-muted)">
                                الفاتورة
                            </td>

                            <td>
                                <div class="invoice-action-row">

                                    <a
                                        href="{{ route('invoices.show', $order->invoice) }}"
                                        class="invoice-number-link"
                                    >
                                        {{ $order->invoice->invoice_number }}
                                    </a>

                                    <a
                                        href="{{ route('invoices.print', $order->invoice) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="btn btn-outline btn-xs invoice-print-btn"
                                    >
                                        طباعة فاتورة
                                    </a>

                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td style="color:var(--text-muted)">
                                صافي المدفوع
                            </td>

                            <td>
                                <strong style="color:#16845b">
                                    ₪{{ number_format($invoicePaidAmount, 2) }}
                                </strong>
                            </td>
                        </tr>

                        <tr>
                            <td style="color:var(--text-muted)">
                                إجمالي المسترد
                            </td>

                            <td>
                                <strong style="color:#b42318">
                                    ₪{{ number_format($orderRefundedAmount, 2) }}
                                </strong>
                            </td>
                        </tr>

                        <tr>
                            <td style="color:var(--text-muted)">
                                المتبقي
                            </td>

                            <td>
                                <strong>
                                    ₪{{ number_format($invoiceRemainingAmount, 2) }}
                                </strong>
                            </td>
                        </tr>

                        <tr>
                            <td style="color:var(--text-muted)">
                                الحالة المالية
                            </td>

                            <td>
                                <span class="badge {{ $financialStatusClass }}">
                                    {{ $financialStatusLabel }}
                                </span>
                            </td>
                        </tr>

                    @endif

                </tbody>

            </table>



            @if($order->notes)

                <div style="margin-top:1rem">

                    <div style="color:var(--text-muted);margin-bottom:.4rem">

                        ملاحظات الطلب

                    </div>

                    <div>

                        {{ $order->notes }}

                    </div>

                </div>

            @endif

        </div>

    </div>



    {{-- عناصر الطلب --}}

    <div class="card">

        <div class="card-header">

            <span class="card-title">

                العناصر

            </span>

        </div>

        <div class="table-wrap">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>المنتج</th>

                        <th>الكمية</th>

                        <th>سعر الوحدة</th>

                        <th>الإجمالي</th>
                        @if($order->isRestaurantOrder())
                            <th>ملاحظة المطبخ</th>
                        @endif

                    </tr>

                </thead>

                <tbody>

                    @forelse($order->items as $item)

                        <tr>

                            <td>

                                {{

                                    $item->product?->name_ar

                                    ?? $item->product?->name

                                    ?? $item->product_name

                                    ?? 'غير محدد'

                                }}

                            </td>

                            <td>

                                {{ number_format((float) $item->quantity, 3) }}

                            </td>

                            <td>

                                ₪{{ number_format((float) $item->unit_price, 2) }}

                            </td>

                            <td>

                                ₪{{ number_format((float) $item->line_total, 2) }}

                            </td>

                            @if($order->isRestaurantOrder())
                                <td>{{ $item->kitchen_notes ?: '—' }}</td>
                            @endif

                        </tr>

                    @empty

                        <tr>

                            <td colspan="{{ $order->isRestaurantOrder() ? 5 : 4 }}">

                                لا توجد عناصر في الطلب.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>



    @if($order->isRestaurantOrder() && $order->kitchenTickets->isNotEmpty())
    <div class="card">
        <div class="card-header">
            <span class="card-title">المطبخ / KDS</span>
            @can('kitchen.view')
                @if(\Illuminate\Support\Facades\Route::has('kitchen.tickets.index'))
                    <a href="{{ route('kitchen.tickets.index', ['location_id' => $order->location_id]) }}" class="btn btn-ghost btn-sm">كل التذاكر</a>
                @endif
            @endcan
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>التذكرة</th>
                        <th>المحطة</th>
                        <th>الحالة</th>
                        <th>وقت الوصول</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->kitchenTickets->sortBy('id') as $ticket)
                        <tr>
                            <td><strong>{{ $ticket->ticket_number }}</strong></td>
                            <td>{{ $ticket->station?->name ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $ticket->status?->badgeClass() ?? 'badge-grey' }}">
                                    {{ $ticket->status?->label() ?? \App\Support\ArabicDisplay::status($ticket->status) }}
                                </span>
                            </td>
                            <td>{{ $ticket->queued_at?->format('H:i') ?? '—' }}</td>
                            <td>
                                @can('kitchen.view')
                                    @if(\Illuminate\Support\Facades\Route::has('kitchen.tickets.show'))
                                        <a href="{{ route('kitchen.tickets.show', $ticket) }}" class="btn btn-ghost btn-sm">عرض</a>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>







{{-- =========================================================

     Popup تأكيد الطلب

\========================================================= --}}

@if($orderStatus === 'draft')

    @can('confirm', $order)

        <div

            id="confirmOrderModal"

            class="order-modal"

            aria-hidden="true"

            onclick="closeOrderActionModalFromBackdrop(event, 'confirmOrderModal')"

        >

            <div

                class="order-modal-dialog"

                role="dialog"

                aria-modal="true"

                aria-labelledby="confirmOrderModalTitle"

            >

                <div class="order-modal-header">

                    <div>

                        <span class="order-modal-eyebrow">

                            اعتماد الطلب

                        </span>

                        <h3 id="confirmOrderModalTitle">

                            تأكيد الطلب

                        </h3>

                        <p>

                            طلب رقم

                            <strong>{{ $order->order_number }}</strong>

                        </p>

                    </div>

                    <button

                        type="button"

                        class="order-modal-close"

                        onclick="closeOrderActionModal('confirmOrderModal')"

                        aria-label="إغلاق"

                    >

                        ×

                    </button>

                </div>



                <div class="order-current-box">

                    <span>

                        الحالة الحالية

                    </span>

                    <strong>

                        {{ $statusLabel }}

                    </strong>

                </div>



                <form

                    action="{{ route('orders.confirm', $order) }}"

                    method="POST"

                    id="confirmOrderForm"

                >

                    @csrf

                    <div class="order-modal-body">

                        <div class="order-action-message">

                            <div class="order-action-icon">

                                ✓

                            </div>

                            <div>

                                <strong>

                                    هل تريد اعتماد هذا الطلب؟

                                </strong>

                                <p>

                                    عند التأكيد سيتم اعتماد الطلب وخصم المخزون وإنشاء الفاتورة حسب منطق النظام.

                                </p>

                            </div>

                        </div>

                    </div>



                    <div class="order-modal-footer">

                        <button

                            type="button"

                            class="btn btn-ghost"

                            onclick="closeOrderActionModal('confirmOrderModal')"

                        >

                            إلغاء

                        </button>

                        <button

                            type="submit"

                            class="btn btn-gold"

                            id="confirmOrderSubmitBtn"

                        >

                            تأكيد الطلب

                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endcan

@endif

{{-- =========================================================

     Popup إكمال الطلب

\========================================================= --}}

@if($orderStatus === 'confirmed')

    @can('update', $order)

        <div

            id="completeOrderModal"

            class="order-modal"

            aria-hidden="true"

            onclick="closeOrderActionModalFromBackdrop(event, 'completeOrderModal')"

        >

            <div

                class="order-modal-dialog"

                role="dialog"

                aria-modal="true"

            >

                <div class="order-modal-header">

                    <div>

                        <span class="order-modal-eyebrow">

                            تحديث حالة الطلب

                        </span>

                        <h3>

                            إكمال الطلب

                        </h3>

                        <p>

                            طلب رقم

                            <strong>{{ $order->order_number }}</strong>

                        </p>

                    </div>



                    <button

                        type="button"

                        class="order-modal-close"

                        onclick="closeOrderActionModal('completeOrderModal')"

                        aria-label="إغلاق"

                    >

                        ×

                    </button>

                </div>



                <div class="order-current-box">

                    <span>

                        الحالة الحالية

                    </span>

                    <strong>

                        {{ $statusLabel }}

                    </strong>

                </div>



                <form

                    action="{{ route('orders.complete', $order) }}"

                    method="POST"

                >

                    @csrf

                    <div class="order-modal-body">

                        <div class="order-action-message">

                            <div class="order-action-icon">

                                ✓

                            </div>

                            <div>

                                <strong>

                                    هل تم تسليم الطلب؟

                                </strong>

                                <p>

                                    عند التأكيد سيتم تحويل حالة الطلب إلى مكتمل.

                                </p>

                            </div>

                        </div>

                    </div>



                    <div class="order-modal-footer">

                        <button

                            type="button"

                            class="btn btn-ghost"

                            onclick="closeOrderActionModal('completeOrderModal')"

                        >

                            إلغاء

                        </button>

                        <button

                            type="submit"

                            class="btn btn-gold"

                        >

                            إكمال الطلب

                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endcan

@endif



{{-- =========================================================

     Popup إلغاء الطلب

\========================================================= --}}

@if(in_array($orderStatus, ['draft', 'confirmed'], true))

    @can('cancel', $order)

        <div

            id="cancelOrderModal"

            class="order-modal {{ $errors->has('cancellation_reason') ? 'is-open' : '' }}"

            aria-hidden="{{ $errors->has('cancellation_reason') ? 'false' : 'true' }}"

            onclick="closeOrderActionModalFromBackdrop(event, 'cancelOrderModal')"

        >

            <div

                class="order-modal-dialog"

                role="dialog"

                aria-modal="true"

            >

                <div class="order-modal-header">

                    <div>

                        <span class="order-modal-eyebrow is-danger">

                            إلغاء الطلب

                        </span>

                        <h3>

                            تأكيد إلغاء الطلب

                        </h3>

                        <p>

                            طلب رقم

                            <strong>{{ $order->order_number }}</strong>

                        </p>

                    </div>



                    <button

                        type="button"

                        class="order-modal-close"

                        onclick="closeOrderActionModal('cancelOrderModal')"

                        aria-label="إغلاق"

                    >

                        ×

                    </button>

                </div>



                <div class="order-current-box is-danger">

                    <span>

                        الحالة الحالية

                    </span>

                    <strong>

                        {{ $statusLabel }}

                    </strong>

                </div>



                <form

                    action="{{ route('orders.cancel', $order) }}"

                    method="POST"

                >

                    @csrf



                    <div class="order-modal-body">

                        <div class="form-group">

                            <label

                                for="cancellation_reason"

                                class="form-label"

                            >

                                سبب الإلغاء *

                            </label>

                            <textarea

                                name="cancellation_reason"

                                id="cancellation_reason"

                                class="form-textarea @error('cancellation_reason') is-invalid @enderror"

                                rows="4"

                                placeholder="اكتب سبب إلغاء الطلب..."

                                required

                            >{{ old('cancellation_reason') }}</textarea>

                            @error('cancellation_reason')

                                <div class="invalid-feedback">

                                    {{ $message }}

                                </div>

                            @enderror

                        </div>

                    </div>



                    <div class="order-modal-footer">

                        <button

                            type="button"

                            class="btn btn-ghost"

                            onclick="closeOrderActionModal('cancelOrderModal')"

                        >

                            تراجع

                        </button>

                        <button

                            class="btn btn-danger"

                            type="submit"

                        >

                            تأكيد الإلغاء

                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endcan

@endif





{{-- =========================================================

     Popup خطأ المخزون

\========================================================= --}}

@if(!empty($stockErrors))

    <div

        id="stockErrorModal"

        class="order-modal is-open"

        aria-hidden="false"

        onclick="closeOrderActionModalFromBackdrop(event, 'stockErrorModal')"

    >

        <div

            class="order-modal-dialog"

            role="dialog"

            aria-modal="true"

            aria-labelledby="stockErrorModalTitle"

        >

            <div class="order-modal-header">

                <div>

                    <span class="order-modal-eyebrow is-danger">تعذر تأكيد الطلب</span>

                    <h3 id="stockErrorModalTitle">المخزون غير كافٍ</h3>

                    <p>

                        طلب رقم

                        <strong>{{ $order->order_number }}</strong>

                    </p>

                </div>

                <button

                    type="button"

                    class="order-modal-close"

                    onclick="closeOrderActionModal('stockErrorModal')"

                    aria-label="إغلاق"

                >×</button>

            </div>

            <div class="order-current-box is-danger">

                <span>حالة الطلب</span>

                <strong>لم يتم تأكيد الطلب</strong>

            </div>

            <div class="order-modal-body">

                <div class="stock-error-message">

                    <div class="stock-error-icon">!</div>

                    <div class="stock-error-content">

                        <strong>لا يمكن تنفيذ الطلب بسبب نقص المخزون</strong>

                        <p>لم يتم خصم أي كمية ولم يتم إنشاء الفاتورة.</p>

                        <ul>

                            @foreach($stockErrors as $error)

                                <li>{{ $error }}</li>

                            @endforeach

                        </ul>

                    </div>

                </div>

            </div>

            <div class="order-modal-footer">

                <button

                    type="button"

                    class="btn btn-ghost"

                    onclick="closeOrderActionModal('stockErrorModal')"

                >

                    إغلاق

                </button>

                @if($orderStatus === 'draft')

                    @can('update', $order)

                        <a

                            href="{{ route('orders.edit', $order) }}"

                            class="btn btn-gold"

                            id="editOrderQuantitiesBtn"

                        >

                            تعديل الكميات

                        </a>

                    @endcan

                @endif

            </div>

        </div>

    </div>

@endif

<style>
    .invoice-action-row {
        display:flex;
        align-items:center;
        flex-wrap:wrap;
        gap:.6rem;
    }

    .invoice-number-link {
        color:var(--gold);
        font-weight:800;
        text-decoration:none;
        direction:ltr;
    }

    .invoice-print-btn {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        white-space:nowrap;
    }



    .dashboard-row {

        display:grid;

        grid-template-columns:repeat(2,minmax(0,1fr));

        gap:1.5rem;

    }

    .dashboard-row .data-table td:first-child {

        width:42%;

    }

    .card {

        border-radius:14px;

    }

    .card-header {

        min-height:52px;

    }

    .data-table td {

        line-height:1.65;

    }

    .page-actions-title {

        color:var(--gold);

        font-weight:800;

    }

    .action-btns {

        display:flex;

        flex-wrap:wrap;

        gap:.5rem;

    }



    /* =========================================================

       Popup تغيير حالة الطلب

    ========================================================= */

    .order-modal {

        position:fixed;

        inset:0;

        z-index:99999;

        display:flex;

        align-items:center;

        justify-content:center;

        padding:1.25rem;

        background:rgba(15,23,42,.58);

        backdrop-filter:blur(3px);

        opacity:0;

        visibility:hidden;

        pointer-events:none;

        transition:

            opacity .18s ease,

            visibility .18s ease;

    }

    .order-modal.is-open {

        opacity:1;

        visibility:visible;

        pointer-events:auto;

    }

    .order-modal-dialog {

        width:100%;

        max-width:520px;

        background:#fff;

        border:1px solid var(--border);

        border-radius:18px;

        box-shadow:0 24px 60px rgba(0,0,0,.22);

        overflow:hidden;

        transform:translateY(18px) scale(.98);

        transition:transform .18s ease;

    }

    .order-modal.is-open .order-modal-dialog {

        transform:translateY(0) scale(1);

    }

    .order-modal-header {

        display:flex;

        align-items:flex-start;

        justify-content:space-between;

        gap:1rem;

        padding:1.2rem 1.3rem;

        border-bottom:1px solid var(--border);

    }

    .order-modal-eyebrow {

        display:block;

        margin-bottom:.22rem;

        color:var(--gold);

        font-size:.68rem;

        font-weight:800;

    }

    .order-modal-eyebrow.is-danger {

        color:#dc3545;

    }

    .order-modal-header h3 {

        margin:0;

        color:var(--text);

        font-size:1.05rem;

        font-weight:900;

    }

    .order-modal-header p {

        margin:.3rem 0 0;

        color:var(--text-muted);

        font-size:.72rem;

    }

    .order-modal-header p strong {

        color:var(--gold);

    }

    .order-modal-close {

        width:34px;

        height:34px;

        flex:0 0 34px;

        display:flex;

        align-items:center;

        justify-content:center;

        padding:0;

        border:1px solid var(--border);

        border-radius:50%;

        background:#fff;

        color:var(--text-muted);

        font-size:1.4rem;

        line-height:1;

        cursor:pointer;

        transition:.15s ease;

    }

    .order-modal-close:hover {

        color:#dc3545;

        border-color:rgba(220,53,69,.25);

        background:rgba(220,53,69,.06);

    }

    .order-current-box {

        display:flex;

        align-items:center;

        justify-content:space-between;

        gap:1rem;

        margin:1rem 1.3rem 0;

        padding:.8rem .9rem;

        border:1px solid rgba(212,160,23,.22);

        border-radius:11px;

        background:rgba(212,160,23,.07);

    }

    .order-current-box span {

        color:var(--text-muted);

        font-size:.72rem;

    }

    .order-current-box strong {

        color:var(--gold);

        font-size:.8rem;

    }

    .order-current-box.is-danger {

        border-color:rgba(220,53,69,.18);

        background:rgba(220,53,69,.06);

    }

    .order-current-box.is-danger strong {

        color:#dc3545;

    }

    .order-modal-body {

        display:grid;

        gap:1rem;

        padding:1.2rem 1.3rem;

    }

    .order-modal-footer {

        display:flex;

        align-items:center;

        justify-content:flex-end;

        gap:.65rem;

        padding:1rem 1.3rem;

        border-top:1px solid var(--border);

        background:var(--off-white);

    }

    .order-action-message {

        display:flex;

        align-items:flex-start;

        gap:.85rem;

        padding:.9rem;

        background:rgba(212,160,23,.055);

        border:1px solid rgba(212,160,23,.16);

        border-radius:12px;

    }

    .order-action-icon {

        width:34px;

        height:34px;

        flex:0 0 34px;

        display:flex;

        align-items:center;

        justify-content:center;

        color:#198754;

        background:rgba(25,135,84,.10);

        border-radius:50%;

        font-weight:900;

    }

    .order-action-message strong {

        color:var(--text);

        font-size:.82rem;

    }

    .order-action-message p {

        margin:.25rem 0 0;

        color:var(--text-muted);

        font-size:.7rem;

        line-height:1.7;

    }



    .stock-error-message {

        display:flex;

        align-items:flex-start;

        gap:.9rem;

        padding:1rem;

        border:1px solid rgba(220,53,69,.20);

        border-radius:13px;

        background:rgba(220,53,69,.055);

    }

    .stock-error-icon {

        width:42px;

        height:42px;

        flex:0 0 42px;

        display:flex;

        align-items:center;

        justify-content:center;

        border-radius:50%;

        background:rgba(220,53,69,.12);

        color:#dc3545;

        font-size:1.2rem;

        font-weight:900;

    }

    .stock-error-content {

        flex:1;

        min-width:0;

    }

    .stock-error-content > strong {

        display:block;

        margin-bottom:.25rem;

        color:#b42318;

        font-size:.86rem;

        font-weight:900;

    }

    .stock-error-content p {

        margin:0;

        color:var(--text-muted);

        font-size:.72rem;

        line-height:1.7;

    }

    .stock-error-content ul {

        margin:.65rem 0 0;

        padding-right:1.2rem;

        color:#7a2930;

        font-size:.76rem;

        line-height:1.9;

    }



    @media(max-width:800px) {

        .dashboard-row {

            grid-template-columns:1fr;

        }

        .page-actions {

            align-items:flex-start;

            flex-direction:column;

            gap:1rem;

        }

        .data-table {

            min-width:0;

        }

        .data-table td {

            padding:.65rem;

        }

        .data-table td:first-child {

            width:38%;

        }

    }



    @media(max-width:600px) {

        .order-modal {

            padding:.75rem;

        }

        .order-modal-dialog {

            max-width:none;

        }

        .order-modal-footer {

            flex-direction:column-reverse;

        }

        .order-modal-footer .btn {

            width:100%;

        }

    }

</style>



<script>

    function openOrderActionModal(action) {

        const modalIds = {

            confirm: 'confirmOrderModal',

            complete: 'completeOrderModal',

            cancel: 'cancelOrderModal'

        };

        const modalId = modalIds[action];

        if (!modalId) {

            return;

        }

        const modal =

            document.getElementById(modalId);

        if (!modal) {

            return;

        }

        modal.classList.add('is-open');

        modal.setAttribute(

            'aria-hidden',

            'false'

        );

        document.body.style.overflow =

            'hidden';

    }



    function closeOrderActionModal(modalId) {

        const modal =

            document.getElementById(modalId);

        if (!modal) {

            return;

        }

        modal.classList.remove('is-open');

        modal.setAttribute(

            'aria-hidden',

            'true'

        );

        document.body.style.overflow =

            '';

    }



    function closeOrderActionModalFromBackdrop(event, modalId) {

        if (

            event.target.id === modalId

        ) {

            closeOrderActionModal(modalId);

        }

    }



    document.addEventListener(

        'keydown',

        function (event) {

            if (event.key !== 'Escape') {

                return;

            }

            document

                .querySelectorAll('.order-modal.is-open')

                .forEach(function (modal) {

                    modal.classList.remove('is-open');

                    modal.setAttribute(

                        'aria-hidden',

                        'true'

                    );

                });

            document.body.style.overflow =

                '';

        }

    );



    document.addEventListener('DOMContentLoaded', function () {

        const openModal = document.querySelector('.order-modal.is-open');

        if (openModal) {

            document.body.style.overflow = 'hidden';

        }

    });



    const confirmOrderForm = document.getElementById('confirmOrderForm');

    if (confirmOrderForm) {

        confirmOrderForm.addEventListener('submit', function () {

            const submitButton = document.getElementById('confirmOrderSubmitBtn');

            if (submitButton) {

                submitButton.disabled = true;

                submitButton.textContent = 'جاري تأكيد الطلب...';

            }

        });

    }

</script>

@endsection
