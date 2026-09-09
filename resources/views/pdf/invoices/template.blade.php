@php
    $enumValue = static function ($value) {
        return $value instanceof \BackedEnum
            ? $value->value
            : $value;
    };

    $invoiceTypeValue = (string) (
        $enumValue(
            $invoice->invoice_type
                ?? 'regular_order'
        )
        ?? 'regular_order'
    );

    $invoiceTypeLabel = match (
        $invoiceTypeValue
    ) {
        'regular_order' => 'فاتورة ضريبية',
        'special_cake_order' => 'فاتورة طلب كيك',
        'proforma' => 'فاتورة أولية',
        default => 'فاتورة',
    };

    $invoiceStatusValue = (string) (
        $enumValue(
            $invoice->status ?? ''
        )
        ?? ''
    );

    $invoiceStatusLabel = match (
        $invoiceStatusValue
    ) {
        'active',
        'issued',
        'completed' => 'نشطة',

        'cancelled',
        'canceled' => 'ملغاة',

        'draft' => 'مسودة',
        'refunded' => 'مسترجعة',

        default => $invoiceStatusValue !== ''
            ? $invoiceStatusValue
            : 'نشطة',
    };

    $paymentStatusValue = (string) (
        $enumValue(
            $invoice->payment_status ?? ''
        )
        ?? ''
    );

    $paymentStatusLabel = match (
        $paymentStatusValue
    ) {
        'paid' => 'مدفوعة',

        'partially_paid',
        'partial' => 'مدفوعة جزئيًا',

        'unpaid',
        'pending',
        'payment_pending' => 'غير مدفوعة',

        'refunded' => 'مسترجعة',

        default => $paymentStatusValue !== ''
            ? $paymentStatusValue
            : 'غير محددة',
    };

    $paymentMethodValue = (string) (
        $enumValue(
            $invoice->payment_method ?? ''
        )
        ?? ''
    );

    $paymentMethodLabel = match (
        $paymentMethodValue
    ) {
        'cash' => 'نقدي',

        'card',
        'credit_card' => 'بطاقة',

        'bank_transfer',
        'bank' => 'تحويل بنكي',

        'wallet',
        'e_wallet' => 'محفظة إلكترونية',

        'mixed' => 'دفع مختلط',

        default => $paymentMethodValue !== ''
            ? $paymentMethodValue
            : 'غير محددة',
    };

    $totalAmount = (float) (
        $invoice->total_amount ?? 0
    );

    $paidAttribute =
        $invoice->getAttribute('paid_amount')
        ?? $invoice->getAttribute(
            'amount_paid'
        );

    $paidAmount = is_numeric($paidAttribute)
        ? (float) $paidAttribute
        : (
            $paymentStatusValue === 'paid'
                ? $totalAmount
                : 0
        );

    $remainingAttribute =
        $invoice->getAttribute(
            'remaining_amount'
        );

    $remainingAmount = is_numeric(
        $remainingAttribute
    )
        ? max(
            (float) $remainingAttribute,
            0
        )
        : max(
            $totalAmount - $paidAmount,
            0
        );

    try {
        $issuedAt = \Carbon\Carbon::parse(
            $invoice->issued_at ?? now()
        );
    } catch (\Throwable) {
        $issuedAt = now();
    }

    $dueAt = null;

    if (! empty($invoice->due_at)) {
        try {
            $dueAt = \Carbon\Carbon::parse(
                $invoice->due_at
            );
        } catch (\Throwable) {
            $dueAt = null;
        }
    }

    $branchName =
        $invoice->location?->name
        ?? '—';

    $branchPhone =
        $invoice->location?->phone
        ?? null;

    $issuedByName =
        $invoice->issuedBy?->employee?->full_name
        ?? $invoice->issuedBy?->name
        ?? $invoice->issuedBy?->display_name
        ?? '—';

    $orderRecord = null;

    $orderType = (string) (
        $enumValue(
            $invoice->order_type ?? ''
        )
        ?? ''
    );

    if (
        $orderType === 'order'
        && ! empty($invoice->order_id)
    ) {
        $orderRecord = \App\Models\Order::query()
            ->with('salesChannel')
            ->find($invoice->order_id);
    }

    $orderNumber =
        $orderRecord?->order_number
        ?? '—';

    $salesChannelName =
        $orderRecord?->salesChannel?->name_ar
        ?? $orderRecord?->salesChannel?->name
        ?? '—';

    $baseCurrency = \App\Models\Currency::query()
        ->where('is_base', true)
        ->first();

    $currencySymbol =
        $invoice->currency?->symbol
        ?? $baseCurrency?->symbol
        ?? '₪';

    $currencyCode =
        $invoice->currency?->code
        ?? $baseCurrency?->code
        ?? 'ILS';

    $invoicePrintTheme = app(
        \App\Services\PrintThemeService::class
    )->settings();
@endphp

@extends('layouts.print')

@section('pdf_mode', '1')

@section(
    'document_title',
    $invoiceTypeLabel
)

@section(
    'document_number',
    $invoice->invoice_number
        ?? $invoice->number
        ?? ('INV-' . $invoice->id)
)

@section(
    'document_date',
    $issuedAt->format('Y-m-d')
)

@section(
    'document_subtitle',
    $paymentStatusLabel
)

@section(
    'document_meta',
    'الفرع: ' . $branchName
)

@section(
    'signature_right',
    'توقيع المستلم'
)

@section(
    'signature_left',
    'اعتماد الإدارة'
)

@push('print_styles')
<style>
    .invoice-status {
        display: inline-block;
        padding: 3px 8px;
        border: 1px solid #d7dde4;
        color: #4b5563;
        font-size: 7.5pt;
        font-weight: bold;
    }

    .invoice-status-paid {
        border-color: #b9ddc7;
        color: #08783e;
        background: #edf8f1;
    }

    .invoice-status-unpaid {
        border-color: #e6c1bb;
        color: #b42318;
        background: #fff3f1;
    }

    .invoice-status-partial {
        border-color: #ead29d;
        color: #946716;
        background: #fff8e6;
    }

    .invoice-info-table {
        width: 100%;
        margin-top: 12px;
        table-layout: fixed;
    }

    .invoice-info-table > tbody > tr > td {
        width: 50%;
        padding: 0 4px;
        vertical-align: top;
    }

    .invoice-info-card {
        min-height: 112px;
        padding: 10px 11px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
    }

    .invoice-info-label {
        margin-bottom: 7px;
        color: {{ $invoicePrintTheme['primary_color'] ?? '#d6a925' }};
        font-size: 8pt;
        font-weight: bold;
    }

    .invoice-customer-name {
        margin-bottom: 4px;
        color: {{ $invoicePrintTheme['secondary_color'] ?? '#111827' }};
        font-size: 13pt;
        font-weight: bold;
    }

    .invoice-detail-table {
        width: 100%;
    }

    .invoice-detail-table td {
        width: 50%;
        padding: 3px 0 6px;
        vertical-align: top;
    }

    .invoice-detail-label {
        display: block;
        color: #6b7280;
        font-size: 7pt;
    }

    .invoice-detail-value {
        display: block;
        color: {{ $invoicePrintTheme['secondary_color'] ?? '#111827' }};
        font-size: 8pt;
        font-weight: bold;
    }

    .invoice-bottom-table {
        width: 100%;
        margin-top: 14px;
        table-layout: fixed;
    }

    .invoice-bottom-table > tbody > tr > td {
        vertical-align: top;
    }

    .invoice-notes-column {
        width: 56%;
        padding-left: 7px;
    }

    .invoice-totals-column {
        width: 44%;
        padding-right: 7px;
    }

    .invoice-notes {
        min-height: 130px;
        padding: 10px;
        border: 1px dashed #d1d5db;
        background: #ffffff;
    }

    .invoice-items .description {
        text-align: right;
    }

    .invoice-items .qty {
        width: 13%;
        text-align: center;
    }

    .invoice-items .price,
    .invoice-items .total {
        width: 20%;
        text-align: center;
    }

    .invoice-item-number {
        display: inline-block;
        margin-left: 5px;
        color: {{ $invoicePrintTheme['primary_color'] ?? '#d6a925' }};
        font-size: 7pt;
        font-weight: bold;
    }
</style>
@endpush

@section('print_content')
    <div style="margin-top:5px;text-align:left">
        <span
            class="invoice-status
                {{ $paymentStatusValue === 'paid'
                    ? 'invoice-status-paid'
                    : (
                        in_array(
                            $paymentStatusValue,
                            ['partially_paid', 'partial'],
                            true
                        )
                            ? 'invoice-status-partial'
                            : 'invoice-status-unpaid'
                    )
                }}"
        >
            {{ $paymentStatusLabel }}
        </span>
    </div>

    <table
        class="invoice-info-table"
        cellpadding="0"
        cellspacing="0"
    >
        <tr>
            <td>
                <div class="invoice-info-card">
                    <div class="invoice-info-label">
                        بيانات العميل
                    </div>

                    <div class="invoice-customer-name">
                        {{ $invoice->customer?->name
                            ?? 'عميل نقدي' }}
                    </div>

                    @if($invoice->customer?->phone)
                        <div class="print-muted">
                            الهاتف:
                            <strong dir="ltr">
                                {{ $invoice->customer->phone }}
                            </strong>
                        </div>
                    @endif

                    @if($invoice->notes)
                        <div
                            class="print-muted"
                            style="margin-top:4px"
                        >
                            ملاحظة:
                            <strong>
                                {{ $invoice->notes }}
                            </strong>
                        </div>
                    @endif
                </div>
            </td>

            <td>
                <div class="invoice-info-card">
                    <div class="invoice-info-label">
                        تفاصيل الفاتورة
                    </div>

                    <table
                        class="invoice-detail-table"
                        cellpadding="0"
                        cellspacing="0"
                    >
                        <tr>
                            <td>
                                <span class="invoice-detail-label">
                                    رقم الطلب
                                </span>

                                <span
                                    class="invoice-detail-value"
                                    dir="ltr"
                                >
                                    {{ $orderNumber }}
                                </span>
                            </td>

                            <td>
                                <span class="invoice-detail-label">
                                    حالة الفاتورة
                                </span>

                                <span class="invoice-detail-value">
                                    {{ $invoiceStatusLabel }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <span class="invoice-detail-label">
                                    طريقة الدفع
                                </span>

                                <span class="invoice-detail-value">
                                    {{ $paymentMethodLabel }}
                                </span>
                            </td>

                            <td>
                                <span class="invoice-detail-label">
                                    قناة البيع
                                </span>

                                <span class="invoice-detail-value">
                                    {{ $salesChannelName }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <span class="invoice-detail-label">
                                    الموظف / الكاشير
                                </span>

                                <span class="invoice-detail-value">
                                    {{ $issuedByName }}
                                </span>
                            </td>

                            <td>
                                <span class="invoice-detail-label">
                                    وقت الإصدار
                                </span>

                                <span
                                    class="invoice-detail-value"
                                    dir="ltr"
                                >
                                    {{ $issuedAt->format('H:i') }}
                                </span>
                            </td>
                        </tr>

                        @if($dueAt)
                            <tr>
                                <td>
                                    <span class="invoice-detail-label">
                                        تاريخ الاستحقاق
                                    </span>

                                    <span
                                        class="invoice-detail-value"
                                        dir="ltr"
                                    >
                                        {{ $dueAt->format('Y-m-d') }}
                                    </span>
                                </td>

                                <td>
                                    <span class="invoice-detail-label">
                                        العملة
                                    </span>

                                    <span class="invoice-detail-value">
                                        {{ $currencyCode }}
                                    </span>
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
            تفاصيل المنتجات
        </div>

        <table
            class="print-table invoice-items"
            cellpadding="0"
            cellspacing="0"
        >
            <thead>
                <tr>
                    <th class="description">
                        الوصف
                    </th>

                    <th class="qty">
                        الكمية
                    </th>

                    <th class="price">
                        سعر الوحدة
                    </th>

                    <th class="total">
                        الإجمالي
                    </th>
                </tr>
            </thead>

            <tbody>
                @forelse($invoice->items as $item)
                    <tr>
                        <td class="description">
                            <span class="invoice-item-number">
                                {{ str_pad(
                                    (string) $loop->iteration,
                                    2,
                                    '0',
                                    STR_PAD_LEFT
                                ) }}
                            </span>

                            {{ $item->description }}
                        </td>

                        <td style="text-align:center">
                            {{ number_format(
                                (float) $item->quantity,
                                3
                            ) }}
                        </td>

                        <td
                            class="print-money"
                            style="text-align:center"
                        >
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $item->unit_price,
                                2
                            ) }}
                        </td>

                        <td
                            class="print-money print-primary"
                            style="text-align:center"
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
                            colspan="4"
                            class="print-empty"
                        >
                            لا توجد عناصر في هذه الفاتورة.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <table
        class="invoice-bottom-table"
        cellpadding="0"
        cellspacing="0"
    >
        <tr>
            <td class="invoice-notes-column">
                <div class="invoice-notes">
                    <div class="invoice-info-label">
                        ملاحظات الدفع
                    </div>

                    <div>
                        {{ $invoice->notes
                            ?: (
                                $invoicePrintTheme['footer_text']
                                ?: 'شكرًا لاختياركم.'
                            ) }}
                    </div>

                    <div
                        class="print-muted"
                        style="margin-top:14px"
                    >
                        طريقة التحصيل:
                        <strong>
                            {{ $paymentMethodLabel }}
                        </strong>
                    </div>

                    @if($branchPhone)
                        <div
                            class="print-muted"
                            style="margin-top:4px"
                        >
                            هاتف الفرع:
                            <strong dir="ltr">
                                {{ $branchPhone }}
                            </strong>
                        </div>
                    @endif
                </div>
            </td>

            <td class="invoice-totals-column">
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
                                (float) $invoice->subtotal,
                                2
                            ) }}
                        </td>
                    </tr>

                    @if(
                        (float) $invoice->discount_amount > 0
                    )
                        <tr>
                            <td>الخصم</td>

                            <td class="print-debit">
                                -
                                {{ $currencySymbol }}
                                {{ number_format(
                                    (float) $invoice->discount_amount,
                                    2
                                ) }}
                            </td>
                        </tr>
                    @endif

                    @if(
                        (float) $invoice->tax_amount > 0
                    )
                        <tr>
                            <td>الضريبة</td>

                            <td>
                                {{ $currencySymbol }}
                                {{ number_format(
                                    (float) $invoice->tax_amount,
                                    2
                                ) }}
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td>المدفوع</td>

                        <td class="print-credit">
                            {{ $currencySymbol }}
                            {{ number_format(
                                $paidAmount,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td>المتبقي</td>

                        <td>
                            {{ $currencySymbol }}
                            {{ number_format(
                                $remainingAmount,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr class="grand">
                        <td>الإجمالي</td>

                        <td>
                            {{ $currencySymbol }}
                            {{ number_format(
                                $totalAmount,
                                2
                            ) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
