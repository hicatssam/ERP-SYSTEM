@extends('layouts.print')

@section('document_title', 'كشف حساب موظف')

@section(
    'document_number',
    'STMT-' . $employee->employee_number
)

@section(
    'document_date',
    now()->format('Y-m-d')
)

@section(
    'document_subtitle',
    $employee->full_name
    . ' · '
    . $employee->employee_number
    . (
        $employee->job_title
            ? ' · ' . $employee->job_title
            : ''
    )
)

@section('signature_mode', 'admin_only')
@section('signature_left', 'اعتماد الإدارة')

@push('print_styles')
<style>
    .statement-summary {
        margin-top: 0;
    }

    .statement-period {
        font-size: 8pt;
        line-height: 1.45;
    }

    .statement-table td,
    .statement-table th {
        text-align: center;
    }

    .statement-table td.statement-description {
        text-align: right;
    }

    .statement-table td.statement-reference {
        direction: ltr;
    }
</style>
@endpush

@section('print_content')
    @php
        $currencySymbol = $printTheme['currency_symbol'] ?? '₪';

        $entryTypeLabels = [
            'basic_salary' => 'راتب أساسي',
            'allowance' => 'بدل',
            'bonus' => 'مكافأة',
            'deduction' => 'خصم',
            'salary_payment' => 'دفعة راتب',
            'salary_payment_void' => 'عكس دفعة راتب',
            'advance' => 'سلفة موظف',
            'advance_repayment' => 'سداد سلفة',
        ];
    @endphp

    <section class="print-section statement-summary">
        <table
            class="print-summary"
            cellpadding="0"
            cellspacing="0"
        >
            <tr>
                <td>
                    <div class="print-summary-label">
                        الرصيد الافتتاحي
                    </div>

                    <strong
                        class="print-summary-value {{ $opening >= 0 ? 'print-credit' : 'print-debit' }}"
                    >
                        {{ number_format((float) $opening, 2) }} {{ $currencySymbol }}
                    </strong>
                </td>

                <td>
                    <div class="print-summary-label">
                        عدد الحركات
                    </div>

                    <strong class="print-summary-value">
                        {{ $rows->count() }}
                    </strong>
                </td>

                <td>
                    <div class="print-summary-label">
                        الفترة
                    </div>

                    <strong class="print-summary-value statement-period">
                        {{ $from?->format('Y-m-d') ?? 'من البداية' }}
                        —
                        {{ $to?->format('Y-m-d') ?? 'حتى اليوم' }}
                    </strong>
                </td>

                <td>
                    <div class="print-summary-label">
                        الرصيد الختامي
                    </div>

                    <strong
                        class="print-summary-value {{ $closing >= 0 ? 'print-credit' : 'print-debit' }}"
                    >
                        {{ number_format((float) $closing, 2) }} {{ $currencySymbol }}
                    </strong>
                </td>
            </tr>
        </table>
    </section>

    <section class="print-section">
        <div class="print-section-title">
            الحركات المالية
        </div>

        <div class="print-table-wrap">
            <table
                class="print-table statement-table"
                cellpadding="0"
                cellspacing="0"
            >
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>البيان</th>
                        <th>النوع</th>
                        <th>دائن للموظف</th>
                        <th>مدين / مدفوع</th>
                        <th>الرصيد</th>
                        <th>المرجع</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rows as $row)
                        @php
                            $entry = $row['entry'];
                            $runningBalance = (float) $row['balance'];
                        @endphp

                        <tr>
                            <td dir="ltr">
                                {{ $entry->entry_date?->format('Y-m-d') ?? '—' }}
                            </td>

                            <td class="statement-description">
                                {{ $entry->description ?: '—' }}
                            </td>

                            <td>
                                {{ $entryTypeLabels[$entry->entry_type] ?? $entry->entry_type ?? '—' }}
                            </td>

                            <td class="print-money print-credit">
                                {{ $entry->direction === 'credit'
                                    ? number_format(
                                        (float) $entry->amount,
                                        2
                                    ) . ' ' . $currencySymbol
                                    : '—' }}
                            </td>

                            <td class="print-money print-debit">
                                {{ $entry->direction === 'debit'
                                    ? number_format(
                                        (float) $entry->amount,
                                        2
                                    ) . ' ' . $currencySymbol
                                    : '—' }}
                            </td>

                            <td
                                class="print-money {{ $runningBalance >= 0 ? 'print-credit' : 'print-debit' }}"
                            >
                                {{ number_format(
                                    $runningBalance,
                                    2
                                ) }} {{ $currencySymbol }}
                            </td>

                            <td class="statement-reference">
                                {{ $entry->reference ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="print-empty"
                            >
                                لا توجد حركات مالية لهذا الموظف ضمن الفترة المحددة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection