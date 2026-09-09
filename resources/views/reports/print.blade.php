@php
    $reportPrintTheme = app(
        \App\Services\PrintThemeService::class
    )->settings();

    $printedAt = now();

    $dateFromFormatted = ! empty($dateFrom)
        ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y')
        : '—';

    $dateToFormatted = ! empty($dateTo)
        ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y')
        : '—';

    $reportRows = collect($rows ?? []);
    $summaryRows = collect($summary ?? []);

    $enumValue = static function ($value) {
        return $value instanceof \BackedEnum ? $value->value : $value;
    };

    $enumLabel = static function ($value): string {
        if (is_object($value) && method_exists($value, 'label')) {
            return (string) $value->label();
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) ($value ?? '—');
    };

    $dateValue = static function ($value, string $format = 'd/m/Y'): string {
        if (empty($value)) {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    };

    $money = static function ($value) use ($reportPrintTheme): string {
        return number_format((float) ($value ?? 0), 2)
            . ' '
            . ($reportPrintTheme['currency_symbol'] ?? '₪');
    };

    $formattedRows = $reportRows
        ->map(function ($row) use (
            $type,
            $enumValue,
            $enumLabel,
            $dateValue,
            $money
        ) {
            return match ($type) {
                'orders' => [
                    '#' . ($row->id ?? ''),
                    $row->customer?->name ?? '—',
                    $row->location?->name ?? '—',
                    $money($row->total_amount ?? 0),
                    $enumLabel($row->status ?? null),
                    $dateValue($row->created_at ?? null),
                ],

                'invoices' => [
                    '#' . ($row->id ?? ''),
                    $row->customer?->name ?? '—',
                    $row->location?->name ?? '—',
                    $money($row->total_amount ?? 0),
                    $money($row->paid_amount ?? 0),
                    $money($row->remaining_amount ?? 0),
                    ((string) ($enumValue($row->status ?? '') ?? '')) === 'active'
                        ? 'نشطة'
                        : $enumLabel($row->status ?? null),
                    $dateValue($row->issued_at ?? null),
                ],

                'payments' => [
                    '#' . ($row->id ?? ''),
                    $row->invoice?->invoice_number
                        ?? ($row->invoice_id ? '#' . $row->invoice_id : '—'),
                    $row->location?->name ?? '—',
                    $money($row->amount ?? 0),
                    $row->payment_method
                        ?? $row->paymentMethod?->name_ar
                        ?? $row->paymentMethod?->name
                        ?? '—',
                    ((string) ($enumValue($row->status ?? '') ?? '')) === 'confirmed'
                        ? 'مؤكدة'
                        : $enumLabel($row->status ?? null),
                    $dateValue($row->paid_at ?? null),
                ],

                'daily-sales' => [
                    $dateValue($row->sale_date ?? null),
                    number_format((int) ($row->invoice_count ?? 0)),
                    $money($row->total_sales ?? 0),
                    $money($row->total_paid ?? 0),
                ],

                'monthly-sales' => [
                    $row->sale_month ?? '—',
                    number_format((int) ($row->invoice_count ?? 0)),
                    $money($row->total_sales ?? 0),
                    $money($row->total_paid ?? 0),
                ],

                'branch-sales' => [
                    $row->branch_name ?? '—',
                    number_format((int) ($row->invoice_count ?? 0)),
                    $money($row->total_sales ?? 0),
                    $money($row->total_paid ?? 0),
                ],

                'product-sales' => [
                    $row->product_name ?? '—',
                    number_format((float) ($row->total_qty ?? 0), 3),
                    $money($row->total_revenue ?? 0),
                ],

                'low-stock' => [
                    $row->product?->name_ar ?? $row->product?->name ?? '—',
                    $row->location?->name ?? '—',
                    number_format((float) ($row->quantity ?? 0), 3),
                    number_format((float) ($row->minimum_stock_level ?? 0), 3),
                ],

                'stock-movements' => [
                    $row->product?->name_ar ?? $row->product?->name ?? '—',
                    $row->location?->name ?? '—',
                    $enumLabel($row->movement_type ?? $row->type ?? null),
                    number_format((float) ($row->quantity ?? 0), 3),
                    $dateValue($row->created_at ?? null),
                ],

                'stock-transfers' => [
                    $row->fromLocation?->name ?? '—',
                    $row->toLocation?->name ?? '—',
                    $enumLabel($row->status ?? null),
                    $row->dispatchedBy?->employee?->full_name
                        ?? $row->dispatchedBy?->name
                        ?? $row->dispatchedBy?->username
                        ?? '—',
                    $dateValue($row->created_at ?? null),
                ],

                'cash-sessions' => [
                    $row->location?->name ?? '—',
                    $row->employee?->full_name ?? '—',
                    $money($row->opening_balance ?? 0),
                    $money($row->cash_received ?? 0),
                    $money($row->actual_cash ?? 0),
                    $money($row->variance ?? 0),
                    ((string) ($enumValue($row->status ?? '') ?? '')) === 'open'
                        ? 'مفتوح'
                        : $enumLabel($row->status ?? null),
                    $dateValue($row->created_at ?? null),
                ],

                'collections' => [
                    $row->invoice?->customer?->name ?? '—',
                    $row->invoice?->invoice_number
                        ?? ($row->invoice_id ? '#' . $row->invoice_id : '—'),
                    $money($row->amount ?? 0),
                    $row->payment_method
                        ?? $row->paymentMethod?->name_ar
                        ?? $row->paymentMethod?->name
                        ?? '—',
                    $dateValue($row->paid_at ?? null),
                ],

                'outstanding' => [
                    $row->customer?->name ?? '—',
                    $row->invoice_number ?? '#' . ($row->id ?? ''),
                    $money($row->total_amount ?? 0),
                    $money($row->paid_amount ?? 0),
                    $money($row->remaining_amount ?? 0),
                    $dateValue($row->issued_at ?? null),
                ],

                'activity-logs' => [
                    $row->user?->username ?? $row->user?->name ?? '—',
                    $row->action ?? '—',
                    trim(
                        (string) ($row->module ?? '')
                        . ' / '
                        . (string) ($row->record_type ?? ''),
                        ' /'
                    ) ?: '—',
                    $dateValue($row->created_at ?? null, 'd/m/Y H:i'),
                ],

                'cake-orders' => [
                    '#' . ($row->id ?? ''),
                    $row->customer?->name ?? '—',
                    $row->originBranch?->name ?? '—',
                    $enumLabel($row->status ?? null),
                    $dateValue($row->required_date ?? null),
                    $dateValue($row->created_at ?? null),
                ],

                'inventory' => [
                    $row->product?->name_ar ?? $row->product?->name ?? '—',
                    $row->location?->name ?? '—',
                    number_format((float) ($row->quantity ?? 0), 3),
                    number_format((float) ($row->minimum_stock_level ?? 0), 3),
                    number_format((float) ($row->maximum_stock_level ?? 0), 3),
                ],

                default => is_array($row)
                    ? array_map(
                        static fn ($value) =>
                            is_scalar($value) || $value === null
                                ? (string) ($value ?? '')
                                : '',
                        array_values($row)
                    )
                    : [
                        is_scalar($row)
                            ? (string) $row
                            : '—',
                    ],
            };
        })
        ->values();

    $columnCount = count($columns ?? []);

    if ($columnCount > 0) {
        $formattedRows = $formattedRows->map(
            function (array $row) use ($columnCount) {
                $row = array_values($row);

                if (count($row) > $columnCount) {
                    $row = array_slice($row, 0, $columnCount);
                }

                while (count($row) < $columnCount) {
                    $row[] = '—';
                }

                return $row;
            }
        );
    }

    $recordCount = (int) ($total ?? $reportRows->count());
@endphp

@extends('layouts.print')

@section('pdf_mode', '1')

@section('document_title', $title ?? 'تقرير')
@section('document_number', '')
@section('document_date', $printedAt->format('Y-m-d'))

@section(
    'document_subtitle',
    'الفترة: ' . $dateFromFormatted . ' — ' . $dateToFormatted
)

@section(
    'document_meta',
    'عدد السجلات: ' . number_format($recordCount)
)

@section('signature_right', 'التوقيع المعتمد')
@section('signature_left', 'اعتماد الإدارة')

@push('print_styles')
<style>
    .report-warning {
        margin-top: 10px;
        padding: 7px 8px;
        border: 1px solid #e4c379;
        background: #fff9e8;
        color: #805410;
        font-size: 7.5pt;
    }

    .report-warning strong {
        font-weight: 900;
    }

    .report-summary-table {
        width: 100%;
        margin-bottom: 9px;
        table-layout: fixed;
    }

    .report-summary-table td {
        width: 25%;
        padding: 7px 8px;
        border: 1px solid #dfe4e9;
        text-align: center;
        vertical-align: middle;
        background: #f8fafc;
    }

    .report-summary-label {
        color: #6b7280;
        font-size: 7.2pt;
    }

    .report-summary-value {
        margin-top: 2px;
        color: {{ $reportPrintTheme['secondary_color'] ?? '#111827' }};
        font-size: 10.5pt;
        font-weight: bold;
    }
</style>
@endpush

@section('print_content')
    @if($summaryRows->isNotEmpty())
        <div class="print-section">
            @foreach($summaryRows->chunk(4) as $summaryChunk)
                <table
                    class="report-summary-table"
                    cellpadding="0"
                    cellspacing="0"
                >
                    <tr>
                        @foreach($summaryChunk as $label => $value)
                            <td>
                                <div class="report-summary-label">
                                    {{ $label }}
                                </div>

                                <div class="report-summary-value">
                                    {{ $value }}
                                </div>
                            </td>
                        @endforeach

                        @for($i = $summaryChunk->count(); $i < 4; $i++)
                            <td>&nbsp;</td>
                        @endfor
                    </tr>
                </table>
            @endforeach
        </div>
    @endif

    <div class="print-section">
        @if($formattedRows->isNotEmpty())
            <table
                class="print-table"
                cellpadding="0"
                cellspacing="0"
            >
                @if(! empty($columns))
                    <thead>
                        <tr>
                            @foreach($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                @endif

                <tbody>
                    @foreach($formattedRows as $row)
                        <tr>
                            @foreach($row as $value)
                                <td style="text-align:center">
                                    {{ $value }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="print-soft-box print-empty">
                لا توجد بيانات للفترة المحددة.
            </div>
        @endif
    </div>

    @if($truncated ?? false)
        <div class="report-warning">
            <strong>تنبيه:</strong>
            يعرض ملف PDF أول
            {{ number_format((int) ($cap ?? 0)) }}
            سجل فقط من أصل
            {{ number_format((int) ($total ?? 0)) }}
            سجل.
            للحصول على كامل البيانات استخدم تصدير Excel أو قلّل نطاق التاريخ.
        </div>
    @endif
@endsection
