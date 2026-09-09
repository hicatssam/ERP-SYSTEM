@php
    $supplierPrintTheme = app(
        \App\Services\PrintThemeService::class
    )->settings();

    $currencyCode =
        $supplierInvoice->currency?->code
        ?? $supplierPrintTheme['currency_code']
        ?? 'ILS';

    $currencySymbol =
        $supplierInvoice->currency?->symbol
        ?? $supplierPrintTheme['currency_symbol']
        ?? '₪';

    $documentNumber =
        $supplierInvoice->invoice_number
        ?? ('SUP-INV-' . $supplierInvoice->id);

    $documentDate =
        $supplierInvoice->invoice_date?->format('Y-m-d')
        ?? now()->format('Y-m-d');

    $statusLabel =
        method_exists($supplierInvoice, 'statusLabel')
            ? $supplierInvoice->statusLabel()
            : (string) ($supplierInvoice->status ?? '—');

    $supplierName =
        $supplierInvoice->supplier?->name
        ?? 'مورد غير محدد';

    $locationName =
        $supplierInvoice->location?->name
        ?? '—';

    /*
    |--------------------------------------------------------------------------
    | Sprint 14 — Batch / Expiry trace
    |--------------------------------------------------------------------------
    | الربط يتم مرة واحدة هنا، أما استخدام $item فيتم فقط داخل foreach.
    | لذلك لا يوجد Undefined variable $item.
    */
    $receiptTraceByProduct = collect();

    if (!empty($supplierInvoice->goods_receipt_id)) {
        $receiptTraceByProduct =
            \App\Models\GoodsReceiptItem::query()
                ->where(
                    'goods_receipt_id',
                    $supplierInvoice->goods_receipt_id
                )
                ->get([
                    'id',
                    'product_id',
                    'batch_number',
                    'manufacturing_date',
                    'expiry_date',
                    'accepted_quantity',
                ])
                ->groupBy('product_id');
    }
@endphp

@extends('layouts.print')

@section('document_title', 'فاتورة مورد')
@section('document_number', $documentNumber)
@section('document_date', $documentDate)

@section(
    'document_subtitle',
    $supplierName . ' · ' . $statusLabel
)

@section(
    'document_meta',
    'الموقع: ' . $locationName
)

@section('signature_right', 'توقيع المستلم')
@section('signature_left', 'اعتماد الإدارة')

@push('print_styles')
<style>
    /*
    |--------------------------------------------------------------------------
    | Supplier invoice — compact A4
    |--------------------------------------------------------------------------
    | لا نعيد تعريف .print-paper أو @page هنا.
    | الحواف والطباعة تُدار مركزيًا من layouts.print.
    */

    .supplier-status-row {
        margin: 0 0 7px;
        text-align: left;
    }

    .supplier-status {
        display: inline-block;
        padding: 2px 7px;
        border: 1px solid #ead29d;
        background: #fff8e6;
        color: #946716;
        font-size: 7pt;
        font-weight: 800;
    }

    .supplier-info-table {
        width: 100%;
        margin-top: 5px;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 5px 0;
    }

    .supplier-info-table > tbody > tr > td {
        width: 50%;
        vertical-align: top;
    }

    .supplier-info-box {
        min-height: 130px;
        padding: 8px 9px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
    }

    .supplier-info-title {
        margin-bottom: 6px;
        padding-bottom: 5px;
        border-bottom: 1px solid #e5e7eb;
        color: {{ $supplierPrintTheme['primary_color'] }};
        font-size: 8pt;
        font-weight: 900;
    }

    .supplier-info-row {
        width: 100%;
        border-collapse: collapse;
    }

    .supplier-info-row td {
        padding: 2px 0;
        border: 0;
        font-size: 7pt;
        vertical-align: top;
    }

    .supplier-info-row td:first-child {
        width: 39%;
        color: #6b7280;
    }

    .supplier-info-row td:last-child {
        color: {{ $supplierPrintTheme['secondary_color'] }};
        font-weight: 800;
    }

    .supplier-items {
        width: 100%;
        table-layout: fixed;
    }

    .supplier-items th,
    .supplier-items td {
        padding: 5px 3px;
        text-align: center;
        vertical-align: middle;
        font-size: 6.8pt;
    }

    .supplier-items .col-index {
        width: 4%;
    }

    .supplier-items .col-description {
        width: 25%;
        text-align: right;
    }

    .supplier-items .col-qty {
        width: 9%;
    }

    .supplier-items .col-unit {
        width: 12%;
    }

    .supplier-items .col-batch {
        width: 14%;
    }

    .supplier-items .col-production {
        width: 11%;
    }

    .supplier-items .col-expiry {
        width: 15%;
    }

    .supplier-items .col-total {
        width: 10%;
    }

    .batch-cell {
        font-size: 6.4pt;
        line-height: 1.35;
        word-break: break-word;
    }

    .expiry-ok {
        color: #08783e;
        font-weight: 800;
    }

    .expiry-warning {
        color: #946716;
        font-weight: 800;
    }

    .expiry-critical,
    .expiry-expired {
        color: #b42318;
        font-weight: 900;
    }

    .supplier-bottom-table {
        width: 100%;
        margin-top: 10px;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 5px 0;
    }

    .supplier-bottom-table > tbody > tr > td {
        vertical-align: top;
    }

    .supplier-history-column {
        width: 56%;
    }

    .supplier-totals-column {
        width: 44%;
    }

    .supplier-list-grid {
        width: 100%;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 5px 0;
    }

    .supplier-list-grid > tbody > tr > td {
        width: 50%;
        vertical-align: top;
    }

    .supplier-list-box {
        min-height: 112px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
    }

    .supplier-list-title {
        padding: 6px 8px;
        border-bottom: 1px solid #e5e7eb;
        background: #f8fafc;
        color: {{ $supplierPrintTheme['secondary_color'] }};
        font-size: 7.4pt;
        font-weight: 900;
    }

    .supplier-list-item {
        padding: 5px 7px;
        border-bottom: 1px solid #edf0f3;
        font-size: 6.5pt;
    }

    .supplier-list-item:last-child {
        border-bottom: 0;
    }

    .supplier-list-head {
        width: 100%;
        border-collapse: collapse;
    }

    .supplier-list-head td {
        padding: 0;
        border: 0;
        font-size: 6.5pt;
        font-weight: 800;
    }

    .supplier-list-head td:last-child {
        text-align: left;
    }

    .supplier-list-sub {
        margin-top: 2px;
        color: #6b7280;
        font-size: 6.1pt;
        line-height: 1.35;
    }

    .supplier-empty {
        padding: 14px 8px;
        color: #6b7280;
        text-align: center;
        font-size: 6.6pt;
    }

    .supplier-notes {
        margin-top: 7px;
        padding: 7px 8px;
        border: 1px dashed #d1d5db;
        background: #ffffff;
        font-size: 6.6pt;
    }

    .supplier-notes strong {
        display: block;
        margin-bottom: 3px;
        color: {{ $supplierPrintTheme['primary_color'] }};
    }

    @media print {
        .supplier-info-box,
        .supplier-list-box,
        .supplier-items tr,
        .supplier-bottom-table {
            page-break-inside: avoid;
        }
    }
</style>
@endpush

@section('print_content')
    <div class="supplier-status-row">
        <span class="supplier-status">
            {{ $statusLabel }}
        </span>
    </div>

    <table
        class="supplier-info-table"
        cellpadding="0"
        cellspacing="0"
    >
        <tr>
            <td>
                <div class="supplier-info-box">
                    <div class="supplier-info-title">
                        بيانات المورد
                    </div>

                    <table
                        class="supplier-info-row"
                        cellpadding="0"
                        cellspacing="0"
                    >
                        <tr>
                            <td>المورد</td>
                            <td>{{ $supplierName }}</td>
                        </tr>

                        @if($supplierInvoice->supplier?->company_name)
                            <tr>
                                <td>الشركة</td>
                                <td>
                                    {{ $supplierInvoice->supplier->company_name }}
                                </td>
                            </tr>
                        @endif

                        @if($supplierInvoice->supplier?->supplier_code)
                            <tr>
                                <td>كود المورد</td>
                                <td dir="ltr">
                                    {{ $supplierInvoice->supplier->supplier_code }}
                                </td>
                            </tr>
                        @endif

                        @if($supplierInvoice->supplier?->contact_person)
                            <tr>
                                <td>جهة الاتصال</td>
                                <td>
                                    {{ $supplierInvoice->supplier->contact_person }}
                                </td>
                            </tr>
                        @endif

                        @if($supplierInvoice->supplier?->phone)
                            <tr>
                                <td>الهاتف</td>
                                <td dir="ltr">
                                    {{ $supplierInvoice->supplier->phone }}
                                </td>
                            </tr>
                        @endif

                        @if($supplierInvoice->supplier?->address)
                            <tr>
                                <td>العنوان</td>
                                <td>
                                    {{ $supplierInvoice->supplier->address }}
                                </td>
                            </tr>
                        @endif
                    </table>
                </div>
            </td>

            <td>
                <div class="supplier-info-box">
                    <div class="supplier-info-title">
                        بيانات الفاتورة
                    </div>

                    <table
                        class="supplier-info-row"
                        cellpadding="0"
                        cellspacing="0"
                    >
                        <tr>
                            <td>رقم الفاتورة</td>
                            <td dir="ltr">{{ $documentNumber }}</td>
                        </tr>

                        <tr>
                            <td>الموقع</td>
                            <td>{{ $locationName }}</td>
                        </tr>

                        <tr>
                            <td>تاريخ الفاتورة</td>
                            <td dir="ltr">
                                {{ $supplierInvoice->invoice_date?->format('Y/m/d') ?? '—' }}
                            </td>
                        </tr>

                        <tr>
                            <td>تاريخ الاستحقاق</td>
                            <td dir="ltr">
                                {{ $supplierInvoice->due_date?->format('Y/m/d') ?? '—' }}
                            </td>
                        </tr>

                        <tr>
                            <td>العملة</td>
                            <td dir="ltr">
                                {{ $currencyCode }}
                            </td>
                        </tr>

                        <tr>
                            <td>سعر الصرف</td>
                            <td dir="ltr">
                                {{ number_format(
                                    (float) $supplierInvoice->exchange_rate,
                                    8,
                                    '.',
                                    ''
                                ) }}
                            </td>
                        </tr>

                        @if($supplierInvoice->purchaseOrder)
                            <tr>
                                <td>أمر الشراء</td>
                                <td dir="ltr">
                                    {{ $supplierInvoice->purchaseOrder->purchase_order_number }}
                                </td>
                            </tr>
                        @endif

                        @if($supplierInvoice->goodsReceipt)
                            <tr>
                                <td>سند الاستلام</td>
                                <td dir="ltr">
                                    {{ $supplierInvoice->goodsReceipt->receipt_number }}
                                </td>
                            </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="print-section">
        <div class="print-section-title">
            بنود الفاتورة
        </div>

        <table
            class="print-table supplier-items"
            cellpadding="0"
            cellspacing="0"
        >
            <thead>
                <tr>
                    <th class="col-index">#</th>
                    <th class="col-description">البند</th>
                    <th class="col-qty">الكمية</th>
                    <th class="col-unit">سعر الوحدة</th>
                    <th class="col-batch">Batch</th>
                    <th class="col-production">الإنتاج</th>
                    <th class="col-expiry">الصلاحية</th>
                    <th class="col-total">الإجمالي</th>
                </tr>
            </thead>

            <tbody>
                @forelse($supplierInvoice->items as $item)
                    @php
                        $traceItems = $item->product_id
                            ? (
                                $receiptTraceByProduct
                                    ->get($item->product_id)
                                ?? collect()
                            )
                            : collect();

                        $batchNumbers = $traceItems
                            ->pluck('batch_number')
                            ->filter()
                            ->unique()
                            ->values();

                        $manufacturingDates = $traceItems
                            ->pluck('manufacturing_date')
                            ->filter()
                            ->map(
                                fn ($date) =>
                                    \Carbon\Carbon::parse($date)
                                        ->format('Y-m-d')
                            )
                            ->unique()
                            ->values();

                        $expiryDates = $traceItems
                            ->pluck('expiry_date')
                            ->filter()
                            ->map(
                                fn ($date) =>
                                    \Carbon\Carbon::parse($date)
                                        ->format('Y-m-d')
                            )
                            ->unique()
                            ->values();

                        $nearestExpiry = $traceItems
                            ->pluck('expiry_date')
                            ->filter()
                            ->map(
                                fn ($date) =>
                                    \Carbon\Carbon::parse($date)
                                        ->startOfDay()
                            )
                            ->sort()
                            ->first();

                        $today = now()->startOfDay();

                        $daysLeft = $nearestExpiry
                            ? $today->diffInDays(
                                $nearestExpiry,
                                false
                            )
                            : null;

                        $expiryClass = match (true) {
                            $daysLeft === null => '',
                            $daysLeft < 0 => 'expiry-expired',
                            $daysLeft <= 7 => 'expiry-critical',
                            $daysLeft <= 60 => 'expiry-warning',
                            default => 'expiry-ok',
                        };
                    @endphp

                    <tr>
                        <td class="col-index">
                            {{ $loop->iteration }}
                        </td>

                        <td class="col-description">
                            {{ $item->description }}
                        </td>

                        <td class="col-qty print-money">
                            {{ number_format(
                                (float) $item->quantity,
                                3
                            ) }}
                        </td>

                        <td class="col-unit print-money">
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $item->unit_price,
                                4
                            ) }}
                        </td>

                        <td
                            class="col-batch batch-cell"
                            dir="ltr"
                        >
                            {{ $batchNumbers->isNotEmpty()
                                ? $batchNumbers->implode(' / ')
                                : '—' }}
                        </td>

                        <td
                            class="col-production batch-cell"
                            dir="ltr"
                        >
                            {{ $manufacturingDates->isNotEmpty()
                                ? $manufacturingDates->implode(' / ')
                                : '—' }}
                        </td>

                        <td
                            class="col-expiry batch-cell {{ $expiryClass }}"
                            dir="ltr"
                        >
                            @if($expiryDates->isNotEmpty())
                                {{ $expiryDates->implode(' / ') }}

                                @if($daysLeft !== null)
                                    <div style="margin-top:2px">
                                        @if($daysLeft < 0)
                                            منتهي منذ
                                            {{ abs($daysLeft) }}
                                            يوم
                                        @elseif($daysLeft === 0)
                                            ينتهي اليوم
                                        @else
                                            متبقي
                                            {{ $daysLeft }}
                                            يوم
                                        @endif
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>

                        <td
                            class="col-total print-money print-primary"
                        >
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $item->line_total,
                                2
                            ) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="8"
                            class="print-empty"
                        >
                            لا توجد بنود في الفاتورة.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <table
        class="supplier-bottom-table"
        cellpadding="0"
        cellspacing="0"
    >
        <tr>
            <td class="supplier-history-column">
                <table
                    class="supplier-list-grid"
                    cellpadding="0"
                    cellspacing="0"
                >
                    <tr>
                        <td>
                            <div class="supplier-list-box">
                                <div class="supplier-list-title">
                                    الدفعات
                                </div>

                                @forelse(
                                    $supplierInvoice->payments
                                    as $payment
                                )
                                    <div class="supplier-list-item">
                                        <table
                                            class="supplier-list-head"
                                            cellpadding="0"
                                            cellspacing="0"
                                        >
                                            <tr>
                                                <td>
                                                    {{ $payment->payment_number }}
                                                </td>

                                                <td dir="ltr">
                                                    {{ number_format(
                                                        (float) $payment->applied_amount,
                                                        2
                                                    ) }}
                                                    {{ $currencyCode }}
                                                </td>
                                            </tr>
                                        </table>

                                        <div class="supplier-list-sub">
                                            {{ $payment->payment_date?->format('Y/m/d') ?? '—' }}

                                            @if($payment->paymentMethod)
                                                —
                                                {{ $payment->paymentMethod->name_ar
                                                    ?: $payment->paymentMethod->name }}
                                            @endif

                                            @if($payment->reference_number)
                                                —
                                                المرجع:
                                                <span dir="ltr">
                                                    {{ $payment->reference_number }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="supplier-empty">
                                        لا توجد دفعات مسجلة.
                                    </div>
                                @endforelse
                            </div>
                        </td>

                        <td>
                            <div class="supplier-list-box">
                                <div class="supplier-list-title">
                                    المرتجعات
                                </div>

                                @forelse(
                                    $supplierInvoice->purchaseReturns
                                    as $return
                                )
                                    <div class="supplier-list-item">
                                        <table
                                            class="supplier-list-head"
                                            cellpadding="0"
                                            cellspacing="0"
                                        >
                                            <tr>
                                                <td>
                                                    {{ $return->return_number }}
                                                </td>

                                                <td dir="ltr">
                                                    {{ number_format(
                                                        (float) $return->grand_total,
                                                        2
                                                    ) }}
                                                    {{ $currencyCode }}
                                                </td>
                                            </tr>
                                        </table>

                                        <div class="supplier-list-sub">
                                            {{ $return->returned_at?->format('Y/m/d') ?? '—' }}
                                            —
                                            {{ $return->statusLabel() }}
                                        </div>
                                    </div>
                                @empty
                                    <div class="supplier-empty">
                                        لا توجد مرتجعات مرتبطة.
                                    </div>
                                @endforelse
                            </div>
                        </td>
                    </tr>
                </table>

                @if($supplierInvoice->notes)
                    <div class="supplier-notes">
                        <strong>ملاحظات:</strong>
                        {{ $supplierInvoice->notes }}
                    </div>
                @endif
            </td>

            <td class="supplier-totals-column">
                <table
                    class="print-total-box"
                    style="width:100%;margin-top:0"
                    cellpadding="0"
                    cellspacing="0"
                >
                    <tr>
                        <td>المجموع الفرعي</td>
                        <td>
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $supplierInvoice->subtotal,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td>الخصم</td>
                        <td class="print-debit">
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $supplierInvoice->discount_amount,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td>الضريبة</td>
                        <td>
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $supplierInvoice->tax_amount,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr class="grand">
                        <td>إجمالي الفاتورة</td>
                        <td>
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $supplierInvoice->grand_total,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td>المرتجعات المطبقة</td>
                        <td>
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $supplierInvoice->credited_amount,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td>المدفوع</td>
                        <td class="print-credit">
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $supplierInvoice->paid_amount,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td>المتبقي</td>
                        <td
                            class="{{ (float) $supplierInvoice->remaining_amount > 0
                                ? 'print-debit'
                                : 'print-credit' }}"
                        >
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $supplierInvoice->remaining_amount,
                                2
                            ) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection

@push('print_scripts')
<script>
document.addEventListener('keydown', function (event) {
    if (
        (event.ctrlKey || event.metaKey)
        && event.key.toLowerCase() === 'p'
    ) {
        event.preventDefault();
        window.print();
    }
});
</script>
@endpush
