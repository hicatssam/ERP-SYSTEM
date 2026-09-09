<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">

    <title>كشف حساب - {{ $customer->name }}</title>

    <style>
        body {
            direction: rtl;
            font-family: dejavusans, sans-serif;
            font-size: 10.5pt;
            color: #252525;
            line-height: 1.65;
        }

        .header-table,
        .info-table,
        .summary-table,
        .statement-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table {
            margin-bottom: 14px;
            border-bottom: 2px solid #c99a24;
        }

        .header-table td {
            padding: 4px 0 10px;
            vertical-align: top;
        }

        .title {
            color: #8b681b;
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .subtitle {
            color: #777;
            font-size: 9pt;
        }

        .header-meta {
            text-align: left;
            color: #666;
            font-size: 8.5pt;
        }

        .section-title {
            margin-top: 15px;
            margin-bottom: 7px;
            padding: 6px 8px;
            background: #f6f1e5;
            border-right: 3px solid #c99a24;
            color: #5d4717;
            font-size: 11pt;
            font-weight: bold;
        }

        .info-table {
            margin-bottom: 12px;
        }

        .info-table td {
            width: 25%;
            border: 1px solid #e3dac7;
            padding: 7px 8px;
            vertical-align: top;
        }

        .label {
            display: block;
            color: #777;
            font-size: 8pt;
            margin-bottom: 2px;
        }

        .value {
            color: #222;
            font-weight: bold;
        }

        .summary-table {
            margin-bottom: 14px;
        }

        .summary-table td {
            width: 16.66%;
            border: 1px solid #e2d8c1;
            padding: 8px 6px;
            text-align: center;
            vertical-align: middle;
        }

        .summary-label {
            color: #777;
            font-size: 7.5pt;
            margin-bottom: 3px;
        }

        .summary-value {
            color: #222;
            font-size: 10.5pt;
            font-weight: bold;
        }

        .success {
            color: #16845b;
        }

        .danger {
            color: #b42318;
        }

        .statement-table {
            margin-top: 4px;
        }

        .statement-table th {
            background: #eee6d5;
            color: #4d3c18;
            border: 1px solid #d9ceb4;
            padding: 6px 5px;
            font-size: 8pt;
            font-weight: bold;
            text-align: right;
        }

        .statement-table td {
            border: 1px solid #e1d8c4;
            padding: 5px;
            font-size: 8pt;
            vertical-align: top;
            text-align: right;
        }

        .opening-row td {
            background: #faf7ef;
            font-weight: bold;
        }

        .movement {
            font-weight: bold;
        }

        .nowrap {
            white-space: nowrap;
        }

        .footer {
            margin-top: 18px;
            padding-top: 7px;
            border-top: 1px solid #ddd2bb;
            color: #888;
            text-align: center;
            font-size: 7.5pt;
        }
    </style>
</head>

<body>

@php
    $locationLabel = $selectedLocation?->name ?? 'كل الفروع';

    $dateFrom = request('date_from');
    $dateTo = request('date_to');

    $rows = $statement['rows'] ?? collect();

    if (is_array($rows)) {
        $rows = collect($rows);
    }
@endphp


<table class="header-table">
    <tr>
        <td>
            <div class="title">
                كشف حساب العميل
            </div>

            <div class="subtitle">
                {{ $customer->name }}
                -
                {{ $locationLabel }}
            </div>
        </td>

        <td class="header-meta">
            تاريخ الطباعة:
            {{ now()->format('Y-m-d H:i') }}

            @if($dateFrom || $dateTo)
                <br>
                الفترة:
                {{ $dateFrom ?: 'البداية' }}
                -
                {{ $dateTo ?: 'اليوم' }}
            @endif
        </td>
    </tr>
</table>


<div class="section-title">
    بيانات العميل
</div>

<table class="info-table">
    <tr>
        <td>
            <span class="label">اسم العميل</span>
            <span class="value">{{ $customer->name }}</span>
        </td>

        <td>
            <span class="label">نوع العميل</span>
            <span class="value">
                {{ method_exists($customer, 'typeLabel') ? $customer->typeLabel() : '—' }}
            </span>
        </td>

        <td>
            <span class="label">الهاتف</span>
            <span class="value">{{ $customer->phone ?? '—' }}</span>
        </td>

        <td>
            <span class="label">الهاتف البديل</span>
            <span class="value">{{ $customer->secondary_phone ?? '—' }}</span>
        </td>
    </tr>

    <tr>
        <td>
            <span class="label">الفرع / النطاق</span>
            <span class="value">{{ $locationLabel }}</span>
        </td>

        <td>
            <span class="label">جهة الاتصال</span>
            <span class="value">{{ $customer->contact_person ?? '—' }}</span>
        </td>

        <td>
            <span class="label">الرقم الضريبي</span>
            <span class="value">{{ $customer->tax_number ?? '—' }}</span>
        </td>

        <td>
            <span class="label">العنوان</span>
            <span class="value">{{ $customer->address ?? '—' }}</span>
        </td>
    </tr>
</table>


<div class="section-title">
    ملخص الحساب
</div>

<table class="summary-table">
    <tr>
        <td>
            <div class="summary-label">الرصيد السابق</div>
            <div class="summary-value">
                ₪{{ number_format((float) ($statement['opening_balance'] ?? 0), 2) }}
            </div>
        </td>

        <td>
            <div class="summary-label">حركات مدينة بالفترة</div>
            <div class="summary-value">
                ₪{{ number_format((float) ($statement['period_debit'] ?? 0), 2) }}
            </div>
        </td>

        <td>
            <div class="summary-label">دفعات / حركات دائنة</div>
            <div class="summary-value success">
                ₪{{ number_format((float) ($statement['period_credit'] ?? 0), 2) }}
            </div>
        </td>

        <td>
            <div class="summary-label">الرصيد الختامي للفترة</div>
            <div class="summary-value">
                ₪{{ number_format((float) ($statement['closing_balance'] ?? 0), 2) }}
            </div>
        </td>

        <td>
            <div class="summary-label">المستحق الحالي</div>
            <div class="summary-value danger">
                ₪{{ number_format((float) ($summary['balance'] ?? 0), 2) }}
            </div>
        </td>

        <td>
            <div class="summary-label">المتأخر الحالي</div>
            <div class="summary-value danger">
                ₪{{ number_format((float) ($summary['overdue'] ?? 0), 2) }}
            </div>
        </td>
    </tr>
</table>


<div class="section-title">
    حركات كشف الحساب
</div>

<table class="statement-table">
    <thead>
        <tr>
            <th>التاريخ</th>
            <th>الفرع</th>
            <th>الحركة</th>
            <th>المرجع</th>
            <th>البيان</th>
            <th>مدين</th>
            <th>دائن</th>
            <th>الرصيد</th>
        </tr>
    </thead>

    <tbody>

        @if(abs((float) ($statement['opening_balance'] ?? 0)) > 0.0001)
            <tr class="opening-row">
                <td colspan="5">
                    الرصيد الافتتاحي قبل الفترة
                </td>

                <td>—</td>
                <td>—</td>

                <td class="nowrap">
                    ₪{{ number_format((float) $statement['opening_balance'], 2) }}
                </td>
            </tr>
        @endif


        @forelse($rows as $row)

            @php
                $rowDate = $row['date'] ?? null;

                if ($rowDate && ! $rowDate instanceof \Carbon\CarbonInterface) {
                    try {
                        $rowDate = \Illuminate\Support\Carbon::parse($rowDate);
                    } catch (\Throwable $e) {
                        $rowDate = null;
                    }
                }
            @endphp

            <tr>

                <td class="nowrap">
                    {{ $rowDate?->format('Y-m-d H:i') ?? '—' }}
                </td>

                <td>
                    {{ $row['location'] ?? '—' }}
                </td>

                <td class="movement">
                    {{ $row['type_label'] ?? 'حركة' }}
                </td>

                <td>
                    {{ $row['reference'] ?? '—' }}
                </td>

                <td>
                    {{ $row['description'] ?? '—' }}
                </td>

                <td class="nowrap">
                    @if((float) ($row['debit'] ?? 0) > 0)
                        ₪{{ number_format((float) $row['debit'], 2) }}
                    @else
                        —
                    @endif
                </td>

                <td class="nowrap success">
                    @if((float) ($row['credit'] ?? 0) > 0)
                        ₪{{ number_format((float) $row['credit'], 2) }}
                    @else
                        —
                    @endif
                </td>

                <td class="nowrap">
                    <strong>
                        ₪{{ number_format((float) ($row['balance'] ?? 0), 2) }}
                    </strong>
                </td>

            </tr>

        @empty

            <tr>
                <td colspan="8" style="text-align:center;padding:18px">
                    لا توجد حركات ضمن الفترة المحددة.
                </td>
            </tr>

        @endforelse

    </tbody>
</table>


<div class="footer">
    كشف حساب العميل {{ $customer->name }}
    -
    تم الإنشاء آليًا من النظام بتاريخ {{ now()->format('Y-m-d H:i') }}
</div>

</body>
</html>