<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تقرير الحوالات البنكية</title>
    <style>
        body {
            font-family: dejavusans;
            direction: rtl;
            color: #1f2937;
            font-size: 9px;
        }

        h1 {
            margin: 0 0 4px;
            font-size: 18px;
        }

        .muted {
            color: #6b7280;
        }

        .header {
            border-bottom: 2px solid #b68b32;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .filters,
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .filters td {
            border: 1px solid #e5e7eb;
            padding: 5px 7px;
            background: #fafafa;
        }

        .filters strong {
            color: #7c5b19;
        }

        .summary td {
            width: 20%;
            border: 1px solid #e5e7eb;
            padding: 7px;
            text-align: center;
        }

        .summary .value {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-top: 3px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .table th {
            background: #2f3338;
            color: #ffffff;
            font-size: 8px;
            padding: 6px 4px;
            border: 1px solid #444a51;
        }

        .table td {
            border: 1px solid #dfe3e8;
            padding: 5px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .table tr:nth-child(even) td {
            background: #fafafa;
        }

        .amount {
            font-weight: bold;
            white-space: nowrap;
        }

        .status {
            font-weight: bold;
        }

        .footer {
            margin-top: 8px;
            color: #6b7280;
            font-size: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>تقرير الحوالات البنكية / الواردة</h1>
        <div class="muted">
            تم إنشاء التقرير بتاريخ {{ now()->format('Y-m-d H:i') }}
            — عدد النتائج {{ (int) ($summary->transfers_count ?? 0) }}
        </div>
    </div>

    <table class="filters">
        <tr>
            @foreach($filters as $label => $value)
                @if(filled($value))
                    <td>
                        <strong>{{ $label }}</strong>
                        <br>
                        {{ $value }}
                    </td>
                @endif
            @endforeach
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>
                عدد الحوالات
                <span class="value">
                    {{ (int) ($summary->transfers_count ?? 0) }}
                </span>
            </td>
            <td>
                إجمالي الحوالات
                <span class="value">
                    ₪{{ number_format((float) ($summary->total_amount ?? 0), 2) }}
                </span>
            </td>
            <td>
                المعتمد
                <span class="value">
                    {{ (int) ($summary->confirmed_count ?? 0) }}
                    ·
                    ₪{{ number_format((float) ($summary->confirmed_total ?? 0), 2) }}
                </span>
            </td>
            <td>
                بانتظار التحقق
                <span class="value">
                    {{ (int) ($summary->pending_count ?? 0) }}
                    ·
                    ₪{{ number_format((float) ($summary->pending_total ?? 0), 2) }}
                </span>
            </td>
            <td>
                المرفوض
                <span class="value">
                    {{ (int) ($summary->rejected_count ?? 0) }}
                    ·
                    ₪{{ number_format((float) ($summary->rejected_total ?? 0), 2) }}
                </span>
            </td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th style="width:7%">التاريخ</th>
                <th style="width:7%">الفرع</th>
                <th style="width:8%">طريقة الدفع</th>
                <th style="width:10%">حساب الاستلام</th>
                <th style="width:9%">المحوّل</th>
                <th style="width:8%">الجوال / الحساب</th>
                <th style="width:9%">رقم الحوالة</th>
                <th style="width:7%">المبلغ</th>
                <th style="width:7%">الحالة</th>
                <th style="width:8%">سجلها</th>
                <th style="width:10%">التحقق</th>
                <th style="width:10%">ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transfers as $transfer)
                @php
                    $account = $transfer->locationPaymentAccount;

                    $accountLabel = $account
                        ? collect([
                            $account->provider_name,
                            $account->account_holder_name ?: $account->name,
                            $account->iban
                                ?: ($account->account_number ?: $account->phone_number),
                        ])->filter()->implode(' — ')
                        : '—';
                @endphp
                <tr>
                    <td>
                        {{ $transfer->received_at?->format('Y-m-d H:i') ?? '—' }}
                    </td>
                    <td>
                        {{ $transfer->location?->name ?? '—' }}
                    </td>
                    <td>
                        {{ $transfer->paymentMethod?->name_ar
                            ?: ($transfer->paymentMethod?->name ?? '—') }}
                    </td>
                    <td>{{ $accountLabel }}</td>
                    <td>
                        {{ $transfer->sender_name }}
                    </td>
                    <td>
                        {{ $transfer->sender_phone ?: '—' }}
                        @if($transfer->sender_account_number)
                            <br>
                            {{ $transfer->sender_account_number }}
                        @endif
                    </td>
                    <td>{{ $transfer->reference_number }}</td>
                    <td class="amount">
                        {{ $transfer->currency_code === 'ILS' ? '₪' : $transfer->currency_code }}
                        {{ number_format((float) $transfer->amount, 2) }}
                    </td>
                    <td class="status">
                        {{ $transfer->status?->label() ?? $transfer->statusValue() }}
                    </td>
                    <td>
                        {{ $transfer->createdBy?->display_name ?? '—' }}
                    </td>
                    <td>
                        {{ $transfer->verifiedBy?->display_name ?? '—' }}
                        @if($transfer->verified_at)
                            <br>
                            {{ $transfer->verified_at->format('Y-m-d H:i') }}
                        @endif

                        @if($transfer->rejection_reason)
                            <br>
                            سبب الرفض:
                            {{ $transfer->rejection_reason }}
                        @endif
                    </td>
                    <td>{{ $transfer->notes ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" style="text-align:center;padding:16px;">
                        لا توجد حوالات تطابق الفلاتر الحالية.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        هذا التقرير يعكس نفس صلاحيات وفلاتر شاشة الحوالات البنكية وقت التصدير.
    </div>
</body>
</html>
