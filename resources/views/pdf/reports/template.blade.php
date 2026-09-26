@php
    $printedAt = now();

    $dateFromFormatted = filled($dateFrom ?? null)
        ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y')
        : '—';

    $dateToFormatted = filled($dateTo ?? null)
        ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y')
        : '—';

    $summaryRows = collect($summary ?? []);

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    $formatDate = static function ($value, string $format = 'Y/m/d'): string {
        if (! filled($value)) {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return '—';
        }
    };

    $money = static function ($value): string {
        return number_format(
            (float) ($value ?? 0),
            2
        );
    };

    $enumValue = static function ($value) {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        return $value;
    };

    $statusLabel = static function ($status): string {

        if (
            is_object($status)
            && method_exists($status, 'label')
        ) {
            return (string) $status->label();
        }

        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        return filled($status)
            ? (string) $status
            : '—';
    };

    $paymentMethodLabel = static function ($row): string {

        return data_get($row, 'paymentMethod.name_ar')
            ?? data_get($row, 'paymentMethod.name')
            ?? data_get($row, 'payment_method')
            ?? '—';
    };

    /*
    |--------------------------------------------------------------------------
    | Source rows
    |--------------------------------------------------------------------------
    */

    $rawRows = collect($rows ?? []);

    /*
    |--------------------------------------------------------------------------
    | Formatted table rows
    |--------------------------------------------------------------------------
    |
    | exportPdf currently sends raw Eloquent/query rows.
    | Some tests may send $formattedRows directly.
    |--------------------------------------------------------------------------
    */

    $tableRows = isset($formattedRows)
        ? collect($formattedRows)
        : $rawRows->map(
            static function ($row) use (
                $type,
                $formatDate,
                $money,
                $enumValue,
                $statusLabel,
                $paymentMethodLabel
            ): array {

                return match ($type ?? null) {

                    /*
                    |--------------------------------------------------------------------------
                    | Orders
                    |--------------------------------------------------------------------------
                    */
                    'orders' => [
                        '#' . data_get($row, 'id'),

                        data_get(
                            $row,
                            'customer.name',
                            '—'
                        ),

                        data_get(
                            $row,
                            'location.name',
                            '—'
                        ),

                        $money(
                            data_get(
                                $row,
                                'total_amount',
                                0
                            )
                        ),

                        $statusLabel(
                            data_get(
                                $row,
                                'status'
                            )
                        ),

                        $formatDate(
                            data_get(
                                $row,
                                'created_at'
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Invoices
                    |--------------------------------------------------------------------------
                    */
                    'invoices' => [
                        '#' . data_get($row, 'id'),

                        data_get(
                            $row,
                            'customer.name',
                            '—'
                        ),

                        data_get(
                            $row,
                            'location.name',
                            '—'
                        ),

                        $money(
                            data_get(
                                $row,
                                'total_amount',
                                0
                            )
                        ),

                        $money(
                            data_get(
                                $row,
                                'paid_amount',
                                0
                            )
                        ),

                        $money(
                            data_get(
                                $row,
                                'remaining_amount',
                                0
                            )
                        ),

                        $statusLabel(
                            data_get(
                                $row,
                                'status'
                            )
                        ),

                        $formatDate(
                            data_get(
                                $row,
                                'issued_at'
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Payments
                    |--------------------------------------------------------------------------
                    |
                    | Payment does NOT use an invoice() relationship.
                    | ReportController provides:
                    |
                    | report_invoice_id
                    | report_invoice_number
                    |--------------------------------------------------------------------------
                    */
                    'payments' => [

                        '#' . data_get(
                            $row,
                            'id'
                        ),

                        data_get(
                            $row,
                            'report_invoice_number'
                        )
                        ?? (
                            data_get(
                                $row,
                                'report_invoice_id'
                            )
                                ? '#'
                                    . data_get(
                                        $row,
                                        'report_invoice_id'
                                    )
                                : '—'
                        ),

                        data_get(
                            $row,
                            'location.name',
                            '—'
                        ),

                        $money(
                            data_get(
                                $row,
                                'amount',
                                0
                            )
                        ),

                        $paymentMethodLabel(
                            $row
                        ),

                        (
                            (string) (
                                $enumValue(
                                    data_get(
                                        $row,
                                        'status'
                                    )
                                )
                                ?? ''
                            )
                        ) === 'confirmed'
                            ? 'مؤكدة'
                            : $statusLabel(
                                data_get(
                                    $row,
                                    'status'
                                )
                            ),

                        $formatDate(
                            data_get(
                                $row,
                                'paid_at'
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Collections
                    |--------------------------------------------------------------------------
                    |
                    | Columns:
                    | العميل - الفاتورة - المبلغ - الطريقة - التاريخ
                    |--------------------------------------------------------------------------
                    */
                    'collections' => [

                        data_get(
                            $row,
                            'report_customer_name'
                        )
                        ?: 'بيع سريع',

                        data_get(
                            $row,
                            'report_invoice_number'
                        )
                        ?? (
                            data_get(
                                $row,
                                'report_invoice_id'
                            )
                                ? '#'
                                    . data_get(
                                        $row,
                                        'report_invoice_id'
                                    )
                                : '—'
                        ),

                        $money(
                            data_get(
                                $row,
                                'amount',
                                0
                            )
                        ),

                        $paymentMethodLabel(
                            $row
                        ),

                        $formatDate(
                            data_get(
                                $row,
                                'paid_at'
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Daily Sales
                    |--------------------------------------------------------------------------
                    */
                    'daily-sales' => [

                        data_get(
                            $row,
                            'sale_date',
                            '—'
                        ),

                        (int) data_get(
                            $row,
                            'invoice_count',
                            0
                        ),

                        $money(
                            data_get(
                                $row,
                                'total_sales',
                                0
                            )
                        ),

                        $money(
                            data_get(
                                $row,
                                'total_paid',
                                0
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Monthly Sales
                    |--------------------------------------------------------------------------
                    */
                    'monthly-sales' => [

                        data_get(
                            $row,
                            'sale_month',
                            '—'
                        ),

                        (int) data_get(
                            $row,
                            'invoice_count',
                            0
                        ),

                        $money(
                            data_get(
                                $row,
                                'total_sales',
                                0
                            )
                        ),

                        $money(
                            data_get(
                                $row,
                                'total_paid',
                                0
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Branch Sales
                    |--------------------------------------------------------------------------
                    */
                    'branch-sales' => [

                        data_get(
                            $row,
                            'branch_name',
                            '—'
                        ),

                        (int) data_get(
                            $row,
                            'invoice_count',
                            0
                        ),

                        $money(
                            data_get(
                                $row,
                                'total_sales',
                                0
                            )
                        ),

                        $money(
                            data_get(
                                $row,
                                'total_paid',
                                0
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Product Sales
                    |--------------------------------------------------------------------------
                    */
                    'product-sales' => [

                        data_get(
                            $row,
                            'product_name',
                            '—'
                        ),

                        $money(
                            data_get(
                                $row,
                                'total_qty',
                                0
                            )
                        ),

                        $money(
                            data_get(
                                $row,
                                'total_revenue',
                                0
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Low Stock
                    |--------------------------------------------------------------------------
                    */
                    'low-stock' => [

                        data_get(
                            $row,
                            'product.name',
                            '—'
                        ),

                        data_get(
                            $row,
                            'location.name',
                            '—'
                        ),

                        data_get(
                            $row,
                            'quantity',
                            0
                        ),

                        data_get(
                            $row,
                            'minimum_stock_level',
                            '—'
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Inventory
                    |--------------------------------------------------------------------------
                    */
                    'inventory' => [

                        data_get(
                            $row,
                            'product.name',
                            '—'
                        ),

                        data_get(
                            $row,
                            'location.name',
                            '—'
                        ),

                        data_get(
                            $row,
                            'quantity',
                            0
                        ),

                        data_get(
                            $row,
                            'minimum_stock_level',
                            '—'
                        ),

                        data_get(
                            $row,
                            'maximum_stock_level',
                            '—'
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Stock Movements
                    |--------------------------------------------------------------------------
                    */
                    'stock-movements' => [

                        data_get(
                            $row,
                            'product.name',
                            '—'
                        ),

                        data_get(
                            $row,
                            'location.name',
                            '—'
                        ),

                        $statusLabel(
                            data_get(
                                $row,
                                'movement_type'
                            )
                        ),

                        $money(
                            data_get(
                                $row,
                                'quantity',
                                0
                            )
                        ),

                        $formatDate(
                            data_get(
                                $row,
                                'created_at'
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Stock Transfers
                    |--------------------------------------------------------------------------
                    */
                    'stock-transfers' => [

                        data_get(
                            $row,
                            'fromLocation.name',
                            '—'
                        ),

                        data_get(
                            $row,
                            'toLocation.name',
                            '—'
                        ),

                        $statusLabel(
                            data_get(
                                $row,
                                'status'
                            )
                        ),

                        data_get(
                            $row,
                            'dispatchedBy.employee.full_name',
                            '—'
                        ),

                        $formatDate(
                            data_get(
                                $row,
                                'created_at'
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Cash Sessions
                    |--------------------------------------------------------------------------
                    */
                    'cash-sessions' => [

                        data_get(
                            $row,
                            'location.name',
                            '—'
                        ),

                        data_get(
                            $row,
                            'employee.full_name',
                            '—'
                        ),

                        $money(
                            data_get(
                                $row,
                                'opening_balance',
                                0
                            )
                        ),

                        $money(
                            data_get(
                                $row,
                                'cash_received',
                                0
                            )
                        ),

                        $money(
                            data_get(
                                $row,
                                'actual_cash',
                                0
                            )
                        ),

                        $money(
                            data_get(
                                $row,
                                'variance',
                                0
                            )
                        ),

                        (
                            (
                                $rawStatus = data_get(
                                    $row,
                                    'status'
                                )
                            ) instanceof \BackedEnum
                                ? $rawStatus->value
                                : $rawStatus
                        ) === 'open'
                            ? 'مفتوح'
                            : 'مغلق',

                        $formatDate(
                            data_get(
                                $row,
                                'created_at'
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Outstanding
                    |--------------------------------------------------------------------------
                    */
                    'outstanding' => [

                        data_get(
                            $row,
                            'customer.name',
                            '—'
                        ),

                        data_get(
                            $row,
                            'invoice_number'
                        )
                        ?? (
                            data_get(
                                $row,
                                'id'
                            )
                                ? '#'
                                    . data_get(
                                        $row,
                                        'id'
                                    )
                                : '—'
                        ),

                        $money(
                            data_get(
                                $row,
                                'total_amount',
                                0
                            )
                        ),

                        $money(
                            data_get(
                                $row,
                                'paid_amount',
                                0
                            )
                        ),

                        $money(
                            data_get(
                                $row,
                                'remaining_amount',
                                0
                            )
                        ),

                        $formatDate(
                            data_get(
                                $row,
                                'issued_at'
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Activity Logs
                    |--------------------------------------------------------------------------
                    */
                    'activity-logs' => [

                        data_get(
                            $row,
                            'user.username'
                        )
                        ?? data_get(
                            $row,
                            'user.name'
                        )
                        ?? '—',

                        data_get(
                            $row,
                            'action',
                            '—'
                        ),

                        trim(
                            (
                                data_get(
                                    $row,
                                    'module',
                                    ''
                                )
                            )
                            . ' / '
                            . (
                                data_get(
                                    $row,
                                    'record_type',
                                    ''
                                )
                            ),
                            ' /'
                        )
                        ?: '—',

                        $formatDate(
                            data_get(
                                $row,
                                'created_at'
                            ),
                            'Y/m/d H:i'
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Cake Orders
                    |--------------------------------------------------------------------------
                    */
                    'cake-orders' => [

                        '#' . data_get(
                            $row,
                            'id'
                        ),

                        data_get(
                            $row,
                            'customer.name',
                            '—'
                        ),

                        data_get(
                            $row,
                            'originBranch.name',
                            '—'
                        ),

                        $statusLabel(
                            data_get(
                                $row,
                                'status'
                            )
                        ),

                        $formatDate(
                            data_get(
                                $row,
                                'required_date'
                            )
                        ),

                        $formatDate(
                            data_get(
                                $row,
                                'created_at'
                            )
                        ),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Unified Cake Production
                    |--------------------------------------------------------------------------
                    */
                    'cake-production' => [
                        data_get($row, 'cake_type', 'غير محدد'),
                        data_get($row, 'cake_size', 'غير محدد'),
                        data_get($row, 'shape', 'غير محدد'),
                        (int) data_get($row, 'special_quantity', 0),
                        (int) data_get($row, 'showroom_quantity', 0),
                        (int) data_get($row, 'total_quantity', 0),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Safe fallback
                    |--------------------------------------------------------------------------
                    */
                    default => is_array($row)
                        ? array_values($row)
                        : (
                            method_exists($row, 'toArray')
                                ? array_values(
                                    $row->toArray()
                                )
                                : [(string) $row]
                        ),
                };
            }
        )
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Columns
    |--------------------------------------------------------------------------
    */

    $tableColumns = array_values(
        $columns ?? []
    );

    $columnCount = max(
        count($tableColumns),
        1
    );
@endphp


<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <title>
        {{ $title ?? 'تقرير' }}
    </title>

    <style>

        @page {
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            direction: rtl;
            color: #172033;
            background: #ffffff;
            font-family: dejavusans, sans-serif;
            font-size: 9pt;
        }

        .report-header {
            border-bottom: 2px solid #c88a08;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .report-header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-header-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }

        .report-brand {
            width: 20%;
            color: #54657a;
            font-size: 8pt;
            text-align: right;
        }

        .report-title-cell {
            width: 60%;
            text-align: center;
        }

        .report-info-cell {
            width: 20%;
            color: #65748a;
            font-size: 7.5pt;
            text-align: left;
        }

        .report-title {
            margin: 0;
            color: #0d2d4d;
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
        }

        .report-period {
            margin-top: 3px;
            color: #65748a;
            font-size: 8pt;
            text-align: center;
        }

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        .summary-table,
        .data-table,
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table {
            margin: 0 0 10px;
        }

        .summary-table td {
            width: 25%;
            border: 1px solid #dde3ea;
            background: #f6f8fb;
            padding: 7px;
            text-align: center;
        }

        .summary-label {
            color: #718096;
            font-size: 7pt;
        }

        .summary-value {
            margin-top: 2px;
            color: #0d2d4d;
            font-size: 10pt;
            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | Data Table
        |--------------------------------------------------------------------------
        */

        .data-table {
            table-layout: fixed;
        }

        .data-table th {
            border: 1px solid #0d2d4d;
            background: #0d2d4d;
            color: #ffffff;
            padding: 6px 4px;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: center;
        }

        .data-table td {
            border: 1px solid #d9e0e8;
            padding: 5px 4px;
            font-size: 7.2pt;
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .data-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        /*
        |--------------------------------------------------------------------------
        | Empty
        |--------------------------------------------------------------------------
        */

        .empty-state {
            border: 1px solid #d9e0e8;
            background: #f8fafc;
            padding: 24px 10px;
            color: #65748a;
            text-align: center;
        }

        /*
        |--------------------------------------------------------------------------
        | Warning
        |--------------------------------------------------------------------------
        */

        .warning {
            margin-top: 10px;
            border: 1px solid #e4c379;
            background: #fff9e8;
            color: #805410;
            padding: 7px;
            font-size: 7.5pt;
        }

        /*
        |--------------------------------------------------------------------------
        | Signature
        |--------------------------------------------------------------------------
        */

        .signature-table {
            margin-top: 24px;
        }

        .signature-table td {
            width: 50%;
            padding-top: 22px;
            color: #4b5563;
            font-size: 8pt;
        }

        .signature-line {
            display: block;
            width: 75%;
            margin-top: 18px;
            border-top: 1px solid #9aa7b5;
            padding-top: 5px;
            text-align: center;
        }

        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        .footer {
            margin-top: 12px;
            border-top: 1px solid #d9e0e8;
            padding-top: 5px;
            color: #8995a6;
            font-size: 7pt;
            text-align: center;
        }

    </style>

</head>


<body>

<div id="report-pdf-root">

    {{-- =====================================================
         Header
    ====================================================== --}}

    <div class="report-header">

        <table
            class="report-header-table"
            cellpadding="0"
            cellspacing="0"
        >

            <tr>

                <td class="report-brand">
                    Dahab Sweets
                </td>


                <td class="report-title-cell">

                    <h1 class="report-title">
                        {{ $title ?? 'تقرير' }}
                    </h1>

                    <div class="report-period">

                        الفترة:

                        {{ $dateFromFormatted }}

                        —

                        {{ $dateToFormatted }}

                    </div>

                </td>


                <td class="report-info-cell">

                    <div>
                        التاريخ:
                        <strong>
                            {{ $printedAt->format('Y-m-d') }}
                        </strong>
                    </div>

                    <div style="margin-top:4px">
                        عدد السجلات:
                        <strong>
                            {{ number_format(
                                (int) (
                                    $total
                                    ?? $tableRows->count()
                                )
                            ) }}
                        </strong>
                    </div>

                </td>

            </tr>

        </table>

    </div>


    {{-- =====================================================
         Summary
    ====================================================== --}}

    @foreach(
        $summaryRows->chunk(4)
        as $summaryChunk
    )

        <table
            class="summary-table"
            cellpadding="0"
            cellspacing="0"
        >

            <tr>

                @foreach(
                    $summaryChunk
                    as $label => $value
                )

                    <td>

                        <div class="summary-label">
                            {{ $label }}
                        </div>

                        <div class="summary-value">
                            {{ $value }}
                        </div>

                    </td>

                @endforeach


                @for(
                    $i = $summaryChunk->count();
                    $i < 4;
                    $i++
                )

                    <td>
                        &nbsp;
                    </td>

                @endfor

            </tr>

        </table>

    @endforeach


    {{-- =====================================================
         Main table
    ====================================================== --}}

    @if($tableRows->isNotEmpty())

        <table
            class="data-table"
            cellpadding="0"
            cellspacing="0"
        >

            @if($tableColumns !== [])

                <thead>

                    <tr>

                        @foreach(
                            $tableColumns
                            as $column
                        )

                            <th>
                                {{ $column }}
                            </th>

                        @endforeach

                    </tr>

                </thead>

            @endif


            <tbody>

                @foreach(
                    $tableRows
                    as $row
                )

                    @php
                        $cells = array_values(
                            (array) $row
                        );

                        /*
                         * Always force the output row to have
                         * exactly the same number of cells
                         * as the report columns.
                         */
                        $cells = array_slice(
                            array_pad(
                                $cells,
                                $columnCount,
                                '—'
                            ),
                            0,
                            $columnCount
                        );
                    @endphp


                    <tr>

                        @foreach(
                            $cells
                            as $value
                        )

                            <td>

                                {{
                                    is_scalar($value)
                                    || $value === null

                                        ? ($value ?? '—')

                                        : '—'
                                }}

                            </td>

                        @endforeach

                    </tr>

                @endforeach

            </tbody>

        </table>


    @else

        <div class="empty-state">

            لا توجد بيانات للفترة المحددة.

        </div>

    @endif


    {{-- =====================================================
         Truncation Warning
    ====================================================== --}}

    @if($truncated ?? false)

        <div class="warning">

            <strong>
                تنبيه:
            </strong>

            يعرض ملف PDF أول

            {{ number_format(
                (int) ($cap ?? 0)
            ) }}

            سجل فقط من أصل

            {{ number_format(
                (int) ($total ?? 0)
            ) }}

            سجل.

            للحصول على جميع البيانات استخدم Excel
            أو قلّل نطاق التاريخ.

        </div>

    @endif


    {{-- =====================================================
         Signatures
    ====================================================== --}}

    <table
        class="signature-table"
        cellpadding="0"
        cellspacing="0"
    >

        <tr>

            <td>

                <span class="signature-line">
                    التوقيع المعتمد
                </span>

            </td>


            <td style="text-align:left">

                <span
                    class="signature-line"
                    style="margin-right:auto"
                >
                    اعتماد الإدارة
                </span>

            </td>

        </tr>

    </table>


    {{-- =====================================================
         Footer
    ====================================================== --}}

    <div class="footer">

        تم إنشاء هذا التقرير آليًا من النظام.

        &nbsp; | &nbsp;

        {{ $printedAt->format('d/m/Y H:i') }}

    </div>

</div>

</body>

</html>