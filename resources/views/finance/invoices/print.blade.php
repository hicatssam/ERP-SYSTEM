@php
    $invoicePrintTheme = app(
        \App\Services\PrintThemeService::class
    )->settings();
    $invoicePrintTheme = array_replace(
        $invoicePrintTheme,
        array_intersect_key(
            $printThemeOverrides ?? [],
            array_flip(['business_name', 'business_name_en', 'logo_src'])
        )
    );

    $publicOrder = $order ?? null;
    $sourceOrder = $sourceOrder ?? $publicOrder;
    $paymentMethod = (string) ($invoice->payment_method ?? '');
    $paymentMethodLabel = match ($paymentMethod) {
        'cash' => 'نقدي',
        'card', 'credit_card' => 'بطاقة',
        'bank', 'bank_transfer' => 'تحويل بنكي',
        'wallet', 'e_wallet' => 'محفظة إلكترونية',
        'mixed' => 'دفع مختلط',
        default => '',
    };
    $customerName = $invoice->customer?->name
        ?? $publicOrder?->customer?->name
        ?? $publicOrder?->guest_name
        ?? 'عميل نقدي';
    $customerPhone = $invoice->customer?->phone
        ?? $publicOrder?->customer?->phone
        ?? $publicOrder?->guest_phone;

    $invoiceRows = $invoice->items->map(fn ($item) => [
        'description' => $item->description ?: ($item->product?->name_ar ?: $item->product?->name ?: 'صنف'),
        'quantity' => (float) $item->quantity,
        'unit_price' => (float) $item->unit_price,
        'line_total' => (float) $item->line_total,
    ]);
    if ($invoiceRows->isEmpty() && $publicOrder) {
        $invoiceRows = $publicOrder->items->map(fn ($item) => [
            'description' => $item->product_name ?: 'صنف',
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'line_total' => (float) $item->line_total,
        ]);
    }

    $currencySymbol =
        $invoice->currency?->symbol
        ?? $invoicePrintTheme['currency_symbol']
        ?? '₪';

    $currencyCode =
        $invoice->currency?->code
        ?? $invoicePrintTheme['currency_code']
        ?? 'ILS';

    $paymentStatusValue = $invoice->paymentStatusValue();

    $paymentStatusClass = match ($paymentStatusValue) {
        'paid' => 'invoice-status-paid',
        'partially_paid', 'partial' => 'invoice-status-partial',
        default => 'invoice-status-unpaid',
    };

    $invoiceStatusClass = $invoice->isActive()
        ? 'invoice-status-active'
        : 'invoice-status-inactive';

    $issuedAt = $invoice->issued_at ?? now();

    $documentNumber =
        $invoice->invoice_number
        ?? $invoice->number
        ?? ('INV-' . $invoice->id);

    $documentSubtitle =
        $customerName
        . ' · '
        . $invoice->paymentStatusLabel();
@endphp

@extends('layouts.print')

@section('pdf_mode', ($pdfMode ?? false) ? '1' : '0')
@section('document_title', $invoice->invoiceTypeValue() === 'special_cake' ? 'فاتورة طلب كيك' : 'فاتورة بيع')
@section('document_number', $documentNumber)
@section('document_date', $issuedAt?->format('Y-m-d') ?? now()->format('Y-m-d'))
@section('document_subtitle', $documentSubtitle)

@section(
    'document_meta',
    'الفرع: ' . ($invoice->location?->name ?? '—')
)

{{-- فواتير البيع لا تحتاج اعتمادًا إداريًا أو ختمًا رسميًا. --}}
@section('show_signatures', '0')
@section('show_stamp', '0')

@if($publicOrder)
    @section('print_toolbar')
        <a class="print-screen-btn" href="{{ route('customer-menu.track', ['token' => $publicOrder->public_token]) }}">تتبع الطلب</a>
        <a class="print-screen-btn" href="{{ route('customer-menu.show', $publicOrder->location->code) }}">المنيو</a>
    @endsection
@endif

@push('print_styles')
<style>
    .invoice-statuses {
        margin-top: 8px;
        text-align: left;
    }

    .invoice-status {
        display: inline-block;
        margin-right: 5px;
        padding: 3px 8px;
        font-size: 7.5pt;
        font-weight: 800;
    }

    .invoice-status-active,
    .invoice-status-paid {
        color: #08783e;
        background: #edf8f1;
    }

    .invoice-status-inactive,
    .invoice-status-unpaid {
        color: #b42318;
        background: #fff3f1;
    }

    .invoice-status-partial {
        color: #946716;
        background: #fff8e6;
    }

    .invoice-party-table {
        width: 100%;
        margin-top: 12px;
        table-layout: fixed;
    }

    .invoice-party-table > tbody > tr > td {
        width: 50%;
        padding: 0 4px;
        vertical-align: top;
    }

    .invoice-party-box {
        min-height: 108px;
        padding: 10px 11px;
        background: #f8fafc;
    }

    .invoice-party-label {
        margin-bottom: 7px;
        color: {{ $invoicePrintTheme['primary_color'] }};
        font-size: 8pt;
        font-weight: 800;
    }

    .invoice-party-name {
        margin-bottom: 4px;
        color: {{ $invoicePrintTheme['secondary_color'] }};
        font-size: 12pt;
        font-weight: 900;
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
        color: {{ $invoicePrintTheme['secondary_color'] }};
        font-size: 8pt;
        font-weight: 800;
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
        min-height: 122px;
        padding: 10px;
        background: #ffffff;
    }

    .invoice-note-title {
        margin-bottom: 6px;
        color: {{ $invoicePrintTheme['primary_color'] }};
        font-size: 8pt;
        font-weight: 800;
    }

    .invoice-thanks {
        margin-top: 12px;
        color: #6b7280;
        font-size: 7.5pt;
        line-height: 1.65;
    }
</style>
@endpush

@section('print_content')
    <div class="invoice-statuses">
        <div class="invoice-status {{ $invoiceStatusClass }}">
            {{ $invoice->statusLabel() }}
        </div>

        <div class="invoice-status {{ $paymentStatusClass }}">
            {{ $invoice->paymentStatusLabel() }}
        </div>
    </div>

    <table
        class="invoice-party-table"
        cellpadding="0"
        cellspacing="0"
    >
        <tr>
            <td>
                <div class="invoice-party-box">
                    <div class="invoice-party-label">
                        بيانات العميل
                    </div>

                    <div class="invoice-party-name">
                        {{ $customerName }}
                    </div>

                    @if($customerPhone)
                        <div class="print-muted">
                            الهاتف:
                            <strong dir="ltr">
                                {{ $customerPhone }}
                            </strong>
                        </div>
                    @endif

                    @if($publicOrder?->delivery_address)
                        <div class="print-muted">{{ $publicOrder->delivery_address }}</div>
                    @endif

                    @if($invoice->notes)
                        <div
                            class="print-muted"
                            style="margin-top:5px"
                        >
                            {{ $invoice->notes }}
                        </div>
                    @endif
                </div>
            </td>

            <td>
                <div class="invoice-party-box">
                    <div class="invoice-party-label">
                        تفاصيل الفاتورة
                    </div>

                    <table
                        class="invoice-detail-table"
                        cellpadding="0"
                        cellspacing="0"
                    >
                        @if($sourceOrder)
                            <tr>
                                <td>
                                    <span class="invoice-detail-label">رقم الطلب</span>
                                    <span class="invoice-detail-value">{{ $sourceOrder->order_number }}</span>
                                </td>
                                <td>
                                    <span class="invoice-detail-label">وقت الإصدار</span>
                                    <span class="invoice-detail-value" dir="ltr">{{ $issuedAt?->format('H:i') }}</span>
                                </td>
                            </tr>
                        @endif

                        <tr>
                            <td>
                                <span class="invoice-detail-label">
                                    الفرع
                                </span>

                                <span class="invoice-detail-value">
                                    {{ $invoice->location?->name ?? '—' }}
                                </span>
                            </td>

                            <td>
                                <span class="invoice-detail-label">
                                    هاتف الفرع
                                </span>

                                <span
                                    class="invoice-detail-value"
                                    dir="ltr"
                                >
                                    {{ $invoice->location?->phone ?? '—' }}
                                </span>
                            </td>
                        </tr>

                        @if($paymentMethodLabel !== '' || $sourceOrder?->salesChannel)
                            <tr>
                                <td>
                                    <span class="invoice-detail-label">طريقة الدفع</span>
                                    <span class="invoice-detail-value">{{ $paymentMethodLabel ?: '—' }}</span>
                                </td>
                                <td>
                                    <span class="invoice-detail-label">قناة البيع</span>
                                    <span class="invoice-detail-value">{{ $sourceOrder?->salesChannel?->name_ar ?? $sourceOrder?->salesChannel?->name ?? '—' }}</span>
                                </td>
                            </tr>
                        @endif

                        @if(! $publicOrder && $invoice->issuedBy)
                            <tr>
                                <td>
                                    <span class="invoice-detail-label">الموظف / الكاشير</span>
                                    <span class="invoice-detail-value">{{ $invoice->issuedBy->employee?->full_name ?? $invoice->issuedBy->display_name ?? '—' }}</span>
                                </td>
                            </tr>
                        @endif

                        <tr>
                            <td>
                                <span class="invoice-detail-label">
                                    حالة الفاتورة
                                </span>

                                <span class="invoice-detail-value">
                                    {{ $invoice->statusLabel() }}
                                </span>
                            </td>

                            <td>
                                <span class="invoice-detail-label">
                                    حالة السداد
                                </span>

                                <span class="invoice-detail-value">
                                    {{ $invoice->paymentStatusLabel() }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                @if($invoice->due_at)
                                    <span class="invoice-detail-label">
                                        تاريخ الاستحقاق
                                    </span>

                                    <span
                                        class="invoice-detail-value"
                                        dir="ltr"
                                    >
                                        {{ $invoice->due_at->format('Y-m-d') }}
                                    </span>
                                @else
                                    <span class="invoice-detail-label">وقت الإصدار</span>
                                    <span class="invoice-detail-value" dir="ltr">{{ $issuedAt?->format('H:i') }}</span>
                                @endif
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
                    <th class="description">الوصف</th>
                    <th class="qty">الكمية</th>
                    <th class="price">سعر الوحدة</th>
                    <th class="total">الإجمالي</th>
                </tr>
            </thead>

            <tbody>
                @forelse($invoiceRows as $item)
                    <tr>
                        <td class="description">
                            {{ $item['description'] }}
                        </td>

                        <td style="text-align:center">
                            {{ number_format(
                                $item['quantity'],
                                3
                            ) }}
                        </td>

                        <td
                            class="print-money"
                            style="text-align:center"
                        >
                            {{ $currencySymbol }}
                            {{ number_format(
                                $item['unit_price'],
                                2
                            ) }}
                        </td>

                        <td
                            class="print-money print-primary"
                            style="text-align:center"
                        >
                            {{ $currencySymbol }}
                            {{ number_format(
                                $item['line_total'],
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
                    <div class="invoice-note-title">
                        ملاحظات
                    </div>

                    <div>
                        {{ $invoice->notes
                            ?: (
                                $invoicePrintTheme['footer_text']
                                ?: 'شكرًا لاختياركم.'
                            ) }}
                    </div>

                    <div class="invoice-thanks">
                        <div>
                            شكرًا لاختياركم
                            {{ $invoicePrintTheme['business_name'] }}.
                        </div>

                        @if($invoicePrintTheme['business_name_en'])
                            <div dir="ltr">
                                Thank you for choosing
                                {{ $invoicePrintTheme['business_name_en'] }}.
                            </div>
                        @endif
                    </div>
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

                    @if((float) $invoice->discount_amount > 0)
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
                        isset($invoice->tax_amount)
                        && (float) $invoice->tax_amount > 0
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
                                (float) $invoice->paid_amount,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td>المتبقي</td>
                        <td class="{{ (float) $invoice->remaining_amount > 0 ? 'print-debit' : 'print-credit' }}">
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $invoice->remaining_amount,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr class="grand">
                        <td>الإجمالي</td>
                        <td>
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $invoice->total_amount,
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
@unless($pdfMode ?? false)
<script>
    window.addEventListener('load', function () {
        window.setTimeout(function () {
            window.print();
        }, 350);
    });
</script>
@endunless
@endpush
