@extends('layouts.print')

@section(
    'document_title',
    'سند صرف راتب'
)

@section(
    'document_number',
    $payment->document_number ?: ('PAY-' . $payment->id)
)

@section(
    'document_date',
    $payment->paid_at?->format('Y-m-d')
        ?? now()->format('Y-m-d')
)

@section(
    'document_subtitle',
    $payment->employee?->full_name ?: 'موظف'
)

@section('signature_right', 'توقيع المستلم')
@section('signature_left', 'اعتماد الصرف')

@section('print_content')
    <section class="print-section">
        <div
            style="
                padding:16px;
                margin-top:14px;
                border:1px solid var(--print-border);
                border-radius:12px;
                text-align:center;
            "
        >
            <small style="color:var(--print-muted)">
                قيمة الدفعة
            </small>

            <div
                style="
                    margin-top:4px;
                    color:var(--print-secondary);
                    font-size:25px;
                    font-weight:900;
                    direction:ltr;
                "
            >
                {{ number_format(
                    (float) $payment->amount,
                    2
                ) }}
                {{ $payment->currency?->symbol }}
            </div>
        </div>
    </section>

    <section class="print-section">
        <h2 class="print-section-title">
            تفاصيل سند الصرف
        </h2>

        <div class="print-table-wrap">
            <table class="print-table">
                <tbody>
                    <tr>
                        <th style="width:30%">الموظف</th>
                        <td>
                            {{ $payment->employee?->full_name }}
                        </td>
                    </tr>

                    <tr>
                        <th>دورة الرواتب</th>
                        <td>
                            {{ $payment->item?->period?->name ?: '—' }}
                        </td>
                    </tr>

                    <tr>
                        <th>طريقة الدفع</th>
                        <td>
                            {{ $payment->paymentMethod?->name_ar
                                ?: $payment->paymentMethod?->name
                                ?: '—' }}
                        </td>
                    </tr>

                    <tr>
                        <th>رقم المرجع</th>
                        <td>
                            {{ $payment->reference ?: '—' }}
                        </td>
                    </tr>

                    <tr>
                        <th>الفترة المالية</th>
                        <td>
                            {{ $payment->financialPeriod?->name ?: '—' }}
                        </td>
                    </tr>

                    <tr>
                        <th>الموقع</th>
                        <td>
                            {{ $payment->location?->name ?: '—' }}
                        </td>
                    </tr>

                    <tr>
                        <th>سعر الصرف</th>
                        <td class="print-money">
                            {{ number_format(
                                (float) $payment->exchange_rate,
                                4
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <th>القيمة بالعملة الأساسية</th>
                        <td class="print-money">
                            {{ number_format(
                                (float) $payment->base_amount,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <th>الحالة</th>
                        <td>
                            @statusArabic($payment->status)
                        </td>
                    </tr>

                    @if($payment->notes)
                        <tr>
                            <th>ملاحظات</th>
                            <td>
                                {{ $payment->notes }}
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>
@endsection
