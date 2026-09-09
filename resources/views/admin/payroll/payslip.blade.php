@extends('layouts.print')

@section(
    'document_title',
    'قسيمة راتب'
)

@section(
    'document_number',
    ($item->period?->code ?: 'PAY')
    . '-'
    . ($item->employee?->employee_number ?: $item->employee_id)
)

@section(
    'document_date',
    $item->period?->end_date?->format('Y-m-d')
        ?? now()->format('Y-m-d')
)

@section(
    'document_subtitle',
    ($item->employee?->full_name ?: 'موظف')
    . ' · '
    . ($item->employee?->employee_number ?: '')
)

@section('signature_right', 'توقيع الموظف')
@section('signature_left', 'اعتماد الإدارة')

@section('print_content')
    <section class="print-section">
        <h2 class="print-section-title">
            بيانات الموظف
        </h2>

        <div class="print-summary">
            <div class="print-summary-card">
                <small>الموظف</small>

                <strong style="font-size:12px">
                    {{ $item->employee?->full_name }}
                </strong>
            </div>

            <div class="print-summary-card">
                <small>المسمى الوظيفي</small>

                <strong style="font-size:12px">
                    {{ $item->employee?->job_title ?: '—' }}
                </strong>
            </div>

            <div class="print-summary-card">
                <small>دورة الرواتب</small>

                <strong style="font-size:12px">
                    {{ $item->period?->name }}
                </strong>
            </div>

            <div class="print-summary-card">
                <small>الحالة</small>

                <strong style="font-size:12px">
                    @statusArabic($item->status)
                </strong>
            </div>
        </div>
    </section>

    <section class="print-section">
        <h2 class="print-section-title">
            مكونات الراتب
        </h2>

        <div class="print-table-wrap">
            <table class="print-table">
                <thead>
                    <tr>
                        <th>البيان</th>
                        <th>النوع</th>
                        <th>إضافة</th>
                        <th>خصم</th>
                    </tr>
                </thead>

                <tbody>
                @forelse($item->components as $component)
                    <tr>
                        <td>
                            {{ $component->label }}
                        </td>

                        <td>
                            {{ $component->kind }}
                        </td>

                        <td class="print-money print-credit">
                            {{ $component->direction === 'credit'
                                ? number_format(
                                    (float) $component->amount,
                                    2
                                )
                                : '—' }}
                        </td>

                        <td class="print-money print-debit">
                            {{ $component->direction === 'debit'
                                ? number_format(
                                    (float) $component->amount,
                                    2
                                )
                                : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="4"
                            class="print-empty"
                        >
                            لا توجد مكونات راتب.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="print-total-box">
        <div class="print-total-row">
            <span>الراتب الأساسي</span>

            <strong>
                {{ number_format(
                    (float) $item->base_salary,
                    2
                ) }}
            </strong>
        </div>

        <div class="print-total-row">
            <span>البدلات</span>

            <strong>
                {{ number_format(
                    (float) $item->allowances_total,
                    2
                ) }}
            </strong>
        </div>

        <div class="print-total-row">
            <span>المكافآت</span>

            <strong>
                {{ number_format(
                    (float) $item->bonuses_total,
                    2
                ) }}
            </strong>
        </div>

        <div class="print-total-row">
            <span>الخصومات</span>

            <strong class="print-debit">
                {{ number_format(
                    (float) $item->deductions_total,
                    2
                ) }}
            </strong>
        </div>

        <div class="print-total-row grand">
            <span>صافي الراتب</span>

            <strong>
                {{ number_format(
                    (float) $item->net_salary,
                    2
                ) }}
            </strong>
        </div>

        <div class="print-total-row">
            <span>المدفوع</span>

            <strong class="print-credit">
                {{ number_format(
                    (float) $paidTotal,
                    2
                ) }}
            </strong>
        </div>

        <div class="print-total-row">
            <span>المتبقي</span>

            <strong>
                {{ number_format(
                    (float) $item->payable_amount,
                    2
                ) }}
            </strong>
        </div>
    </div>
@endsection
