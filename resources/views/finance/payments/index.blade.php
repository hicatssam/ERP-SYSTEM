@extends('layouts.app')

@section('title', 'الحركات المالية')

@section('content')

<div class="page-actions">

    <div class="page-actions-title">الحركات المالية</div>

</div>

{{-- Filters --}}

<div class="filter-row">

    <form method="GET" class="filter-grid">

        <div class="filter-group">

            <label class="filter-label">الحالة</label>

            <select name="status" class="form-select">

                <option value="">الكل</option>

                @foreach($statusOptions as $s)

                    <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>

                        {{ match($s->value) {

                            'pending_verification' => 'بانتظار التحقق',

                            'confirmed'            => 'مؤكدة',

                            'rejected'             => 'مرفوضة',

                            'corrected'            => 'مصححة',

                            'refunded'             => 'مستردة',

                            default                => $s->value,

                        } }}

                    </option>

                @endforeach

            </select>

        </div>

        <div class="filter-group">

            <label class="filter-label">طريقة الدفع</label>

            <select name="payment_method_id" class="form-select">

                <option value="">الكل</option>

                @foreach($paymentMethods as $pm)

                    <option value="{{ $pm->id }}" {{ request('payment_method_id') == $pm->id ? 'selected' : '' }}>

                        {{ $pm->name_ar }}

                    </option>

                @endforeach

            </select>

        </div>

        @if($locations->count() > 0)

        <div class="filter-group">

            <label class="filter-label">الفرع</label>

            <select name="location_id" class="form-select">

                <option value="">الكل</option>

                @foreach($locations as $loc)

                    <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>

                        {{ $loc->name }}

                    </option>

                @endforeach

            </select>

        </div>

        @endif

        <div class="filter-group">

            <label class="filter-label">نوع الطلب</label>

            <select name="order_type" class="form-select">

                <option value="">الكل</option>

                <option value="order"               {{ request('order_type') === 'order'               ? 'selected' : '' }}>طلب عادي</option>

                <option value="special_cake_order"  {{ request('order_type') === 'special_cake_order'  ? 'selected' : '' }}>طلب كيك خاص</option>

            </select>

        </div>

        <div class="filter-group">

            <label class="filter-label">من تاريخ</label>

            <input type="date" name="date_from" class="form-input" value="{{ request('date_from') }}">

        </div>

        <div class="filter-group">

            <label class="filter-label">إلى تاريخ</label>

            <input type="date" name="date_to" class="form-input" value="{{ request('date_to') }}">

        </div>

        <div class="filter-group" style="justify-content:flex-end;gap:.5rem;display:flex;align-items:flex-end">

            <button type="submit" class="btn btn-outline btn-sm">تصفية</button>

            <a href="{{ route('payments.index') }}" class="btn btn-ghost btn-sm">مسح</a>

        </div>

    </form>

</div>

{{-- Summary bar --}}
@php
    /*
    |--------------------------------------------------------------------------
    | ملخص مالي للصفحة الحالية
    |--------------------------------------------------------------------------
    */
    $pageGrossCollections = 0.0;
    $pageRefunds = 0.0;
    $pagePending = 0.0;
    $pageMovementCount = 0;

    foreach ($payments as $summaryPayment) {
        $summaryStatus = $summaryPayment->status?->value
            ?? (string) $summaryPayment->status;

        $summaryRefunded = (float) $summaryPayment
            ->refunds
            ->sum('amount');

        if (in_array(
            $summaryStatus,
            ['confirmed', 'corrected', 'refunded'],
            true
        )) {
            $pageGrossCollections +=
                (float) $summaryPayment->amount;
        }

        if (
            $summaryStatus
            === 'pending_verification'
        ) {
            $pagePending +=
                (float) $summaryPayment->amount;
        }

        $pageRefunds +=
            $summaryRefunded;

        $pageMovementCount +=
            1
            + $summaryPayment
                ->refunds
                ->count();
    }

    $pageNetCollections =
        $pageGrossCollections
        - $pageRefunds;
@endphp

<div
    class="stats-grid payment-stats-grid"
    style="margin-bottom:1.25rem"
>
    <div class="stat-card stat-green" style="padding:.85rem 1rem">
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.15rem">
                ₪{{ number_format($pageGrossCollections, 2) }}
            </div>
            <div class="stat-label">
                التحصيلات الأصلية (هذه الصفحة)
            </div>
        </div>
    </div>

    <div class="stat-card stat-red" style="padding:.85rem 1rem">
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.15rem">
                ₪{{ number_format($pageRefunds, 2) }}
            </div>
            <div class="stat-label">
                الاستردادات (هذه الصفحة)
            </div>
        </div>
    </div>

    <div class="stat-card stat-gold" style="padding:.85rem 1rem">
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.15rem">
                ₪{{ number_format($pageNetCollections, 2) }}
            </div>
            <div class="stat-label">
                صافي التحصيل
            </div>
        </div>
    </div>

    <div class="stat-card stat-blue" style="padding:.85rem 1rem">
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.15rem">
                {{ $pageMovementCount }}
            </div>
            <div class="stat-label">
                عدد الحركات في الصفحة
            </div>
        </div>
    </div>
</div>

{{-- Table --}}

<div class="table-wrap">

    <table class="data-table">

        <thead>
            <tr>
                <th>#</th>
                <th>التاريخ</th>
                <th>نوع الطلب</th>
                <th>رقم الطلب</th>
                <th>العميل</th>
                <th>الفاتورة</th>
                <th>الفرع</th>
                <th>طريقة الدفع</th>
                <th>المبلغ</th>
                <th>الحالة</th>
                <th>المرجع / الإثبات</th>
                <th>المستلم</th>
                <th>إجراءات</th>
            </tr>
        </thead>

        <tbody>
        @forelse($payments as $payment)
            @php
                $statusValue =
                    $payment->status?->value
                    ?? (string) $payment->status;

                $orderType =
                    $payment->order_type?->value
                    ?? (string) $payment->order_type;

                $linkedOrder =
                    $payment->relationLoaded('linkedOrder')
                        ? $payment->getRelation('linkedOrder')
                        : null;

                $linkedInvoice =
                    $payment->relationLoaded('linkedInvoice')
                        ? $payment->getRelation('linkedInvoice')
                        : null;

                $orderNumber =
                    $linkedOrder?->order_number
                    ?? ('#' . $payment->order_id);

                $customerName =
                    $linkedOrder?->customer?->name
                    ?? (
                        $orderType === 'order'
                            ? 'عميل نقدي'
                            : '—'
                    );

                $orderLabel = match($orderType) {
                    'order' =>
                        'طلب عادي',

                    'special_cake_order' =>
                        'كيك خاص',

                    default =>
                        'غير محدد',
                };

                $orderLink = match($orderType) {
                    'order' =>
                        route(
                            'orders.show',
                            $payment->order_id
                        ),

                    'special_cake_order' =>
                        route(
                            'cake-orders.show',
                            $payment->order_id
                        ),

                    default =>
                        null,
                };

                $proofUrl =
                    $payment->payment_proof
                        ? asset(
                            'storage/'
                            . ltrim(
                                $payment->payment_proof,
                                '/'
                            )
                        )
                        : null;

                $refundedAmount =
                    round(
                        (float) $payment
                            ->refunds
                            ->sum('amount'),
                        2
                    );

                $paymentAmount =
                    round(
                        (float) $payment->amount,
                        2
                    );

                $netCollected =
                    round(
                        max(
                            0,
                            $paymentAmount
                            - $refundedAmount
                        ),
                        2
                    );

                $isFullyRefunded =
                    $paymentAmount > 0
                    &&
                    $refundedAmount
                    >=
                    $paymentAmount;

                $isPartiallyRefunded =
                    $refundedAmount > 0
                    &&
                    ! $isFullyRefunded;

                $statusBadge =
                    $isFullyRefunded
                        ? [
                            'class' =>
                                'badge-secondary',

                            'label' =>
                                'مستردة بالكامل',
                        ]
                        : (
                            $isPartiallyRefunded
                                ? [
                                    'class' =>
                                        'badge-warning',

                                    'label' =>
                                        'مستردة جزئياً',
                                ]
                                : match($statusValue) {
                                    'confirmed' => [
                                        'class' =>
                                            'badge-active',

                                        'label' =>
                                            'مؤكدة',
                                    ],

                                    'pending_verification' => [
                                        'class' =>
                                            'badge-warning',

                                        'label' =>
                                            'بانتظار التحقق',
                                    ],

                                    'rejected' => [
                                        'class' =>
                                            'badge-inactive',

                                        'label' =>
                                            'مرفوضة',
                                    ],

                                    'corrected' => [
                                        'class' =>
                                            'badge-info',

                                        'label' =>
                                            'مصححة',
                                    ],

                                    'refunded' => [
                                        'class' =>
                                            'badge-secondary',

                                        'label' =>
                                            'مستردة بالكامل',
                                    ],

                                    default => [
                                        'class' =>
                                            'badge-secondary',

                                        'label' =>
                                            $statusValue
                                            ?: 'غير محدد',
                                    ],
                                }
                        );

                $refundableAmount =
                    round(
                        max(
                            0,
                            $paymentAmount
                            - $refundedAmount
                        ),
                        2
                    );
            @endphp

            {{-- =====================================================
                 الدفعة الأصلية
            ====================================================== --}}
            <tr class="payment-movement-row">

                <td class="text-muted" style="font-size:.8rem">
                    {{ $payment->id }}
                </td>

                <td>
                    {{ $payment->paid_at?->format('Y-m-d') ?? '—' }}
                    <br>
                    <small class="text-muted">
                        {{ $payment->paid_at?->format('H:i') }}
                    </small>
                </td>

                <td>
                    <span
                        class="badge {{ $orderType === 'order' ? 'badge-info' : 'badge-warning' }}"
                        style="font-size:.75rem"
                    >
                        {{ $orderLabel }}
                    </span>
                </td>

                <td>
                    @if($linkedOrder && $orderLink)
                        <a
                            href="{{ $orderLink }}"
                            class="link-gold payment-order-link"
                            title="فتح الطلب"
                        >
                            {{ $orderNumber }}
                        </a>

                        <small class="payment-linked-id">
                            ID: {{ $payment->order_id }}
                        </small>
                    @else
                        <span class="text-muted">
                            {{ $orderNumber }}
                        </span>
                    @endif
                </td>

                <td>
                    <strong class="payment-customer-name">
                        {{ $customerName }}
                    </strong>
                </td>

                <td>
                    @if($linkedInvoice)
                        <div class="invoice-movement-actions">
                            <a
                                href="{{ route('invoices.show', $linkedInvoice) }}"
                                class="link-gold payment-invoice-link"
                            >
                                {{ $linkedInvoice->invoice_number }}
                            </a>

                            <a
                                href="{{ route('invoices.print', $linkedInvoice) }}"
                                target="_blank"
                                rel="noopener"
                                class="payment-print-link"
                            >
                                طباعة
                            </a>
                        </div>
                    @elseif($statusValue === 'pending_verification')
                        <span class="text-muted">
                            تصدر بعد اعتماد الدفع
                        </span>
                    @else
                        <span class="text-muted">
                            —
                        </span>
                    @endif
                </td>

                <td>
                    {{ $payment->location?->name ?? '—' }}
                </td>

                <td>
                    {{ $payment->paymentMethod?->name_ar
                        ?? $payment->paymentMethod?->name
                        ?? '—' }}
                </td>

                <td>
                    <div class="payment-amount-stack">
                        <strong class="movement-credit">
                            + ₪{{ number_format($paymentAmount, 2) }}
                        </strong>

                        @if($refundedAmount > 0)
                            <small>
                                الصافي:
                                ₪{{ number_format($netCollected, 2) }}
                            </small>
                        @endif
                    </div>
                </td>

                <td>
                    <span class="badge {{ $statusBadge['class'] }}">
                        {{ $statusBadge['label'] }}
                    </span>
                </td>

                <td>
                    <div class="payment-reference-cell">
                        <span title="{{ $payment->reference_number }}">
                            {{ $payment->reference_number ?: '—' }}
                        </span>

                        @if($proofUrl)
                            <a
                                href="{{ $proofUrl }}"
                                target="_blank"
                                rel="noopener"
                                class="payment-proof-link"
                            >
                                عرض الإثبات
                            </a>
                        @endif
                    </div>
                </td>

                <td>
                    {{ $payment->receivedBy?->display_name ?? 'غير مسجل' }}
                </td>

                <td>
                    <div
                        style="display:flex;gap:.4rem;flex-wrap:wrap"
                    >
                        @if($statusValue === 'pending_verification')
                            @can('payments.verify')
                                <form
                                    method="POST"
                                    action="{{ route('payments.verify', $payment) }}"
                                    style="display:inline"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="verify"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-success btn-xs"
                                        onclick="return confirm('تأكيد هذه الدفعة؟')"
                                    >
                                        ✓ تحقق
                                    </button>
                                </form>

                                <form
                                    method="POST"
                                    action="{{ route('payments.verify', $payment) }}"
                                    style="display:inline"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="reject"
                                    >

                                    <input
                                        type="hidden"
                                        name="rejection_reason"
                                        value="رفض من قائمة الحركات"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-danger btn-xs"
                                        onclick="return confirm('رفض هذه الدفعة؟')"
                                    >
                                        ✗ رفض
                                    </button>
                                </form>
                            @endcan

                        @elseif(
                            in_array(
                                $statusValue,
                                ['confirmed', 'corrected', 'refunded'],
                                true
                            )
                            &&
                            $refundableAmount > 0
                        )
                            @can('payments.refund')
                                <button
                                    type="button"
                                    class="btn btn-outline btn-xs"
                                    onclick="openRefundModal(
                                        {{ $payment->id }},
                                        {{ $refundableAmount }}
                                    )"
                                >
                                    استرداد
                                </button>
                            @endcan
                        @endif
                    </div>
                </td>

            </tr>


            {{-- =====================================================
                 حركات الاسترداد
            ====================================================== --}}
            @foreach($payment->refunds->sortBy('processed_at') as $refund)

                <tr class="refund-movement-row">

                    <td class="refund-prefix">
                        REF-{{ $refund->id }}
                    </td>

                    <td>
                        {{ $refund->processed_at?->format('Y-m-d') ?? '—' }}
                        <br>
                        <small class="text-muted">
                            {{ $refund->processed_at?->format('H:i') }}
                        </small>
                    </td>

                    <td>
                        <span class="badge badge-inactive">
                            استرداد
                        </span>
                    </td>

                    <td>
                        @if($linkedOrder && $orderLink)
                            <a
                                href="{{ $orderLink }}"
                                class="link-gold payment-order-link"
                            >
                                {{ $orderNumber }}
                            </a>
                        @else
                            {{ $orderNumber }}
                        @endif
                    </td>

                    <td>
                        {{ $customerName }}
                    </td>

                    <td>
                        @if($linkedInvoice)
                            <a
                                href="{{ route('invoices.show', $linkedInvoice) }}"
                                class="link-gold payment-invoice-link"
                            >
                                {{ $linkedInvoice->invoice_number }}
                            </a>
                        @else
                            —
                        @endif
                    </td>

                    <td>
                        {{ $payment->location?->name ?? '—' }}
                    </td>

                    <td>
                        {{ $refund->paymentMethod?->name_ar
                            ?? $refund->paymentMethod?->name
                            ?? '—' }}
                    </td>

                    <td>
                        <strong class="movement-debit">
                            − ₪{{ number_format((float) $refund->amount, 2) }}
                        </strong>
                    </td>

                    <td>
                        <span class="badge badge-inactive">
                            مسترد
                        </span>
                    </td>

                    <td>
                        <div class="refund-reason-cell">
                            {{ $refund->reason ?: 'استرداد دفعة' }}
                        </div>
                    </td>

                    <td>
                        {{ $refund->processedBy?->display_name ?? 'غير مسجل' }}
                    </td>

                    <td>
                        <span class="text-muted" style="font-size:.72rem">
                            من الدفعة #{{ $payment->id }}
                        </span>
                    </td>

                </tr>

            @endforeach

        @empty
            <tr>
                <td colspan="13">
                    <div class="empty-state-sm">
                        لا توجد حركات مالية تطابق هذه الفلاتر.
                    </div>
                </td>
            </tr>
        @endforelse
        </tbody>

    </table>

</div>

{{-- Pagination --}}

@if($payments->hasPages())

<div style="margin-top:1rem">{{ $payments->links() }}</div>

@endif

{{-- Refund Modal --}}

<div id="refundModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:999;align-items:center;justify-content:center">

    <div style="background:#fff;border-radius:12px;padding:1.5rem;width:100%;max-width:400px;box-shadow:0 8px 32px rgba(0,0,0,.18)">

        <h3 style="margin:0 0 1rem;font-size:1rem;font-weight:700">استرداد دفعة</h3>

        <form method="POST" id="refundForm">

            @csrf

            <div class="form-group">

                <label class="form-label">المبلغ المسترد <span id="refundMax"></span></label>

                <input type="number" name="amount" id="refundAmount" step="0.01" min="0.01" class="form-input" required>

            </div>

            <div class="form-group">

                <label class="form-label">طريقة الاسترداد</label>

                <select name="payment_method_id" class="form-select" required>

                    @foreach($paymentMethods as $pm)

                        <option value="{{ $pm->id }}">{{ $pm->name_ar }}</option>

                    @endforeach

                </select>

            </div>

            <div class="form-group">

                <label class="form-label">سبب الاسترداد</label>

                <textarea name="reason" class="form-input" rows="2" required></textarea>

            </div>

            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1rem">

                <button type="button" class="btn btn-ghost" onclick="closeRefundModal()">إلغاء</button>

                <button type="submit" class="btn btn-danger">تأكيد الاسترداد</button>

            </div>

        </form>

    </div>

</div>

@endsection


@push('styles')
<style>
.payment-order-link,
.payment-invoice-link {
    display: inline-block;
    font-weight: 800;
    direction: ltr;
}

.payment-linked-id,
.payment-link-missing {
    display: block;
    margin-top: .2rem;
    font-size: .65rem;
}

.payment-linked-id {
    color: var(--text-muted);
}

.payment-link-missing {
    color: var(--error);
}

.payment-customer-name {
    font-size: .82rem;
    font-weight: 700;
}

.payment-reference-cell {
    max-width: 145px;
    display: grid;
    gap: .25rem;
    color: var(--text-muted);
    font-size: .78rem;
    overflow-wrap: anywhere;
}

.payment-proof-link {
    width: fit-content;
    color: var(--gold);
    font-size: .7rem;
    font-weight: 800;
    text-decoration: none;
}

.payment-proof-link:hover {
    text-decoration: underline;
}

@media (max-width: 1100px) {
    .table-wrap {
        overflow-x: auto;
    }

    .data-table {
        min-width: 1350px;
    }
}
</style>
@endpush


@push('styles')
<style>
.payment-stats-grid {
    grid-template-columns:repeat(4,minmax(0,1fr));
}

.stat-red .stat-value {
    color:#b42318;
}

.payment-movement-row {
    background:#fff;
}

.refund-movement-row {
    background:rgba(180,35,24,.045) !important;
}

.refund-movement-row td {
    border-top:1px dashed rgba(180,35,24,.18);
}

.refund-prefix {
    color:#b42318;
    font-size:.72rem;
    font-weight:800;
}

.payment-amount-stack {
    display:grid;
    gap:.15rem;
}

.payment-amount-stack small {
    color:var(--text-muted);
    font-size:.68rem;
}

.movement-credit {
    color:#16845b;
}

.movement-debit {
    color:#b42318;
}

.refund-reason-cell {
    max-width:170px;
    color:#7a2930;
    font-size:.75rem;
    line-height:1.5;
}

.invoice-movement-actions {
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:.4rem;
}

.payment-print-link {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:.2rem .45rem;
    color:var(--gold);
    border:1px solid rgba(212,175,55,.38);
    border-radius:6px;
    font-size:.67rem;
    font-weight:800;
    text-decoration:none;
}

.payment-print-link:hover {
    background:rgba(212,175,55,.08);
}

@media(max-width:1100px) {
    .payment-stats-grid {
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:650px) {
    .payment-stats-grid {
        grid-template-columns:1fr;
    }
}
</style>
@endpush

@push('scripts')

<script>

function openRefundModal(paymentId, refundableAmount) {
    const modal =
        document.getElementById('refundModal');

    document
        .getElementById('refundForm')
        .action =
            `/payments/${paymentId}/refund`;

    const amountInput =
        document.getElementById('refundAmount');

    amountInput.max =
        Number(refundableAmount)
            .toFixed(2);

    amountInput.value =
        Number(refundableAmount)
            .toFixed(2);

    document
        .getElementById('refundMax')
        .textContent =
            `(المتاح للاسترداد ₪${Number(refundableAmount).toFixed(2)})`;

    modal.style.display =
        'flex';
}

function closeRefundModal() {

    document.getElementById('refundModal').style.display = 'none';

}

document.getElementById('refundModal').addEventListener('click', function(e) {

    if (e.target === this) closeRefundModal();

});

</script>

@endpush
