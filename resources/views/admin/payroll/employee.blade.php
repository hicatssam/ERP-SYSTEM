@extends('layouts.app')

@section('title', 'كشف حساب ' . $employee->full_name)

@section('content')

@php
    $salaryBasisLabels = [
        'monthly' => 'شهري',
        'daily' => 'يومي',
        'hourly' => 'بالساعة',
    ];

    $currentSalary = (float) ($compensation?->base_salary ?? 0);

    $activeAdvancesCount = collect($advances)
        ->filter(fn ($advance) => in_array($advance->status, ['active', 'open', 'partial'], true))
        ->count();

    $advancesOutstanding = (float) collect($advances)
        ->sum(fn ($advance) => (float) ($advance->outstanding_amount ?? 0));

    $ledgerPageCollection = method_exists($ledgerEntries, 'getCollection')
        ? $ledgerEntries->getCollection()
        : collect($ledgerEntries);

    $pageCredits = (float) $ledgerPageCollection
        ->where('direction', 'credit')
        ->sum('amount');

    $pageDebits = (float) $ledgerPageCollection
        ->where('direction', 'debit')
        ->sum('amount');
@endphp

<style>
    .employee-payroll-page {
        max-width: 1280px;
        margin: 0 auto;
    }

    .employee-payroll-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .employee-payroll-title {
        margin: 0;
        font-size: 1.65rem;
        font-weight: 850;
        color: var(--text);
        line-height: 1.35;
    }

    .employee-payroll-meta {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-wrap: wrap;
        margin-top: .35rem;
        color: var(--text-muted);
        font-size: .78rem;
    }

    .employee-payroll-actions {
        display: flex;
        align-items: center;
        gap: .6rem;
        flex-wrap: wrap;
    }

    .pay-summary-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: .8rem;
        margin-bottom: 1rem;
    }

    .pay-summary-card {
        min-height: 106px;
        padding: 1rem;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 15px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .pay-summary-label {
        color: var(--text-muted);
        font-size: .7rem;
        font-weight: 700;
    }

    .pay-summary-value {
        margin-top: .45rem;
        font-size: 1.3rem;
        font-weight: 900;
        color: var(--text);
        font-variant-numeric: tabular-nums;
    }

    .pay-summary-note {
        margin-top: .2rem;
        color: var(--text-muted);
        font-size: .67rem;
    }

    .pay-credit {
        color: var(--theme-success);
    }

    .pay-debit {
        color: var(--theme-danger);
    }

    .pay-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
    }

    .pay-card + .pay-card {
        margin-top: 1rem;
    }

    .pay-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.1rem;
        border-bottom: 1px solid var(--border);
    }

    .pay-card-title-wrap {
        display: flex;
        align-items: center;
        gap: .7rem;
    }

    .pay-card-icon {
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        border-radius: 11px;
        display: grid;
        place-items: center;
        color: var(--theme-primary);
        background: color-mix(in srgb, var(--theme-primary) 10%, transparent);
    }

    .pay-card-title {
        margin: 0;
        font-size: .95rem;
        font-weight: 850;
        color: var(--text);
    }

    .pay-card-subtitle {
        display: block;
        margin-top: .15rem;
        color: var(--text-muted);
        font-size: .7rem;
    }

    .pay-card-actions {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-wrap: wrap;
    }

    .advance-table-wrap,
    .ledger-table-wrap {
        overflow-x: auto;
    }

    .pay-table {
        width: 100%;
        border-collapse: collapse;
    }

    .pay-table th,
    .pay-table td {
        padding: .82rem .75rem;
        border-bottom: 1px solid var(--border);
        text-align: right;
        white-space: nowrap;
        vertical-align: middle;
    }

    .pay-table th {
        background: color-mix(in srgb, var(--surface) 88%, var(--background));
        color: var(--text-muted);
        font-size: .7rem;
        font-weight: 800;
    }

    .pay-table td {
        color: var(--text);
        font-size: .8rem;
    }

    .pay-table tbody tr:hover {
        background: color-mix(in srgb, var(--theme-primary) 3%, transparent);
    }

    .pay-money {
        font-variant-numeric: tabular-nums;
        font-weight: 750;
    }

    .pay-badge {
        display: inline-flex;
        align-items: center;
        padding: .3rem .58rem;
        border-radius: 999px;
        font-size: .68rem;
        font-weight: 800;
        background: color-mix(in srgb, var(--border) 55%, transparent);
        color: var(--text-muted);
    }

    .pay-badge-active {
        color: var(--theme-warning);
        background: color-mix(in srgb, var(--theme-warning) 12%, transparent);
    }

    .pay-badge-settled,
    .pay-badge-paid {
        color: var(--theme-success);
        background: color-mix(in srgb, var(--theme-success) 12%, transparent);
    }

    .pay-empty {
        padding: 2.5rem 1rem;
        text-align: center;
        color: var(--text-muted);
    }

    .pay-empty-icon {
        width: 58px;
        height: 58px;
        margin: 0 auto .75rem;
        border-radius: 16px;
        display: grid;
        place-items: center;
        color: var(--theme-primary);
        background: color-mix(in srgb, var(--theme-primary) 9%, transparent);
    }

    .pay-current-compensation {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-wrap: wrap;
        color: var(--text-muted);
        font-size: .72rem;
    }

    .pay-current-compensation strong {
        color: var(--text);
    }

    .pay-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .pay-modal.is-open {
        display: flex;
    }

    .pay-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, .58);
        backdrop-filter: blur(3px);
    }

    .pay-modal-dialog {
        position: relative;
        z-index: 2;
        width: min(620px, 100%);
        max-height: calc(100vh - 2rem);
        overflow-y: auto;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: 0 24px 70px rgba(0, 0, 0, .24);
    }

    .pay-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--border);
    }

    .pay-modal-header h3 {
        margin: 0;
        color: var(--text);
        font-size: 1rem;
        font-weight: 850;
    }

    .pay-modal-header p {
        margin: .2rem 0 0;
        color: var(--text-muted);
        font-size: .72rem;
    }

    .pay-modal-close {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        border: 0;
        border-radius: 10px;
        cursor: pointer;
        background: color-mix(in srgb, var(--border) 55%, transparent);
        color: var(--text-muted);
        font-size: 1.1rem;
    }

    .pay-modal-body {
        padding: 1.15rem;
    }

    .pay-modal-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .85rem;
    }

    .pay-modal-full {
        grid-column: 1 / -1;
    }

    .pay-modal-footer {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: 1rem 1.15rem 1.15rem;
        border-top: 1px solid var(--border);
    }

    @media (max-width: 1100px) {
        .pay-summary-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .employee-payroll-header,
        .pay-card-header {
            align-items: stretch;
            flex-direction: column;
        }

        .employee-payroll-actions .btn,
        .pay-card-actions .btn {
            flex: 1 1 auto;
        }

        .pay-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pay-modal-grid {
            grid-template-columns: 1fr;
        }

        .pay-modal-full {
            grid-column: auto;
        }
    }

    @media (max-width: 480px) {
        .pay-summary-grid {
            grid-template-columns: 1fr;
        }

        .employee-payroll-actions {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>

<div class="employee-payroll-page">
    <div class="employee-payroll-header">
        <div>
            <div class="employee-payroll-meta" style="margin-top:0;margin-bottom:.35rem">
                <a
                    href="{{ route('payroll.index') }}"
                    style="color:var(--theme-primary);text-decoration:none;font-weight:750"
                >
                    الرواتب
                </a>
                <span>‹</span>
                <span>الملف المالي للموظف</span>
            </div>

            <h1 class="employee-payroll-title">
                {{ $employee->full_name }}
            </h1>

            <div class="employee-payroll-meta">
                <span>{{ $employee->employee_number }}</span>
                <span>•</span>
                <span>{{ $employee->job_title ?: 'بدون مسمى وظيفي' }}</span>

                @if($compensation)
                    <span>•</span>
                    <span>
                        {{ $salaryBasisLabels[$compensation->salary_basis] ?? $compensation->salary_basis }}
                    </span>
                @endif
            </div>
        </div>

        <div class="employee-payroll-actions">
            @can('payroll.manage')
                <button
                    class="btn btn-outline js-pay-modal"
                    type="button"
                    data-modal="compensationModal"
                >
                    تعديل الراتب
                </button>
            @endcan

            @can('payroll.adjustments.manage')
                <button
                    class="btn btn-outline js-pay-modal"
                    type="button"
                    data-modal="adjustmentModal"
                >
                    إضافة حركة
                </button>
            @endcan

            @can('payroll.advances.manage')
                <button
                    class="btn btn-outline js-pay-modal"
                    type="button"
                    data-modal="advanceModal"
                >
                    تسجيل سلفة
                </button>
            @endcan

            @can('payroll.documents.print')
                <a
                    class="btn btn-gold"
                    href="{{ route('payroll.employees.statement.print', [
                        'employee' => $employee,
                        'from' => request('from'),
                        'to' => request('to'),
                    ]) }}"
                    target="_blank"
                >
                    طباعة كشف الحساب
                </a>
            @endcan
        </div>
    </div>

    <div class="pay-summary-grid">
        <div class="pay-summary-card">
            <span class="pay-summary-label">الرصيد المستحق للموظف</span>
            <strong class="pay-summary-value {{ $ledgerBalance >= 0 ? 'pay-credit' : 'pay-debit' }}">
                {{ number_format((float) $ledgerBalance, 2) }}
            </strong>
            <span class="pay-summary-note">
                الدائن للموظف ناقص المدفوعات والسلف
            </span>
        </div>

        <div class="pay-summary-card">
            <span class="pay-summary-label">الراتب الأساسي الحالي</span>
            <strong class="pay-summary-value">
                {{ number_format($currentSalary, 2) }}
            </strong>
            <span class="pay-summary-note">
                @if($compensation)
                    {{ $salaryBasisLabels[$compensation->salary_basis] ?? $compensation->salary_basis }}
                    ·
                    {{ $currencies->firstWhere('id', $compensation->currency_id)?->symbol ?? '' }}
                @else
                    لم يتم إعداد راتب بعد
                @endif
            </span>
        </div>

        <div class="pay-summary-card">
            <span class="pay-summary-label">السلف القائمة</span>
            <strong class="pay-summary-value pay-debit">
                {{ number_format($advancesOutstanding, 2) }}
            </strong>
            <span class="pay-summary-note">
                {{ $activeAdvancesCount }} سلفة نشطة
            </span>
        </div>

        <div class="pay-summary-card">
            <span class="pay-summary-label">دائن في الصفحة الحالية</span>
            <strong class="pay-summary-value pay-credit">
                {{ number_format($pageCredits, 2) }}
            </strong>
            <span class="pay-summary-note">
                إجمالي الحركات الدائنة المعروضة
            </span>
        </div>

        <div class="pay-summary-card">
            <span class="pay-summary-label">مدين / مدفوع في الصفحة</span>
            <strong class="pay-summary-value pay-debit">
                {{ number_format($pageDebits, 2) }}
            </strong>
            <span class="pay-summary-note">
                إجمالي الحركات المدينة المعروضة
            </span>
        </div>
    </div>

    <div class="pay-card">
        <div class="pay-card-header">
            <div class="pay-card-title-wrap">
                <div class="pay-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <path d="M7 9h10M7 13h4M15 13h2"/>
                    </svg>
                </div>

                <div>
                    <h3 class="pay-card-title">إعداد الراتب الحالي</h3>
                    <span class="pay-card-subtitle">
                        آخر إعداد راتب فعال لهذا الموظف.
                    </span>
                </div>
            </div>

            @can('payroll.manage')
                <button
                    class="btn btn-sm btn-outline js-pay-modal"
                    type="button"
                    data-modal="compensationModal"
                >
                    {{ $compensation ? 'تعديل الإعداد' : 'إعداد الراتب' }}
                </button>
            @endcan
        </div>

        <div class="card-body">
            @if($compensation)
                <div class="pay-current-compensation">
                    <span>
                        أساس الراتب:
                        <strong>{{ $salaryBasisLabels[$compensation->salary_basis] ?? $compensation->salary_basis }}</strong>
                    </span>
                    <span>•</span>
                    <span>
                        الأساسي:
                        <strong>{{ number_format((float) $compensation->base_salary, 2) }}</strong>
                    </span>
                    <span>•</span>
                    <span>
                        العملة:
                        <strong>
                            {{ $currencies->firstWhere('id', $compensation->currency_id)?->code ?? '—' }}
                        </strong>
                    </span>
                    <span>•</span>
                    <span>
                        ساري من:
                        <strong>{{ $compensation->effective_from?->format('Y-m-d') }}</strong>
                    </span>
                </div>
            @else
                <div class="pay-empty" style="padding:1.5rem 1rem">
                    لم يتم إعداد راتب لهذا الموظف حتى الآن.
                </div>
            @endif
        </div>
    </div>

    <div class="pay-card">
        <div class="pay-card-header">
            <div class="pay-card-title-wrap">
                <div class="pay-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>

                <div>
                    <h3 class="pay-card-title">السلف الحالية</h3>
                    <span class="pay-card-subtitle">
                        السلف المسجلة والمتبقي على الموظف.
                    </span>
                </div>
            </div>

            @can('payroll.advances.manage')
                <button
                    class="btn btn-sm btn-outline js-pay-modal"
                    type="button"
                    data-modal="advanceModal"
                >
                    تسجيل سلفة
                </button>
            @endcan
        </div>

        <div class="advance-table-wrap">
            @if(collect($advances)->count())
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>القيمة</th>
                            <th>المسترد</th>
                            <th>المتبقي</th>
                            <th>المرجع</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($advances as $advance)
                            <tr>
                                <td>{{ $advance->issued_at?->format('Y-m-d') }}</td>

                                <td class="pay-money">
                                    {{ number_format((float) $advance->amount, 2) }}
                                </td>

                                <td class="pay-money pay-credit">
                                    {{ number_format((float) ($advance->recovered_amount ?? 0), 2) }}
                                </td>

                                <td class="pay-money pay-debit">
                                    {{ number_format((float) $advance->outstanding_amount, 2) }}
                                </td>

                                <td>{{ $advance->reference ?: '—' }}</td>

                                <td>
                                    <span class="pay-badge pay-badge-{{ $advance->status }}">
                                        @statusArabic($advance->status)
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="pay-empty">
                    <div class="pay-empty-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="M7 9h10M7 13h5"/>
                        </svg>
                    </div>
                    <strong>لا توجد سلف مسجلة</strong>
                    <div style="margin-top:.3rem;font-size:.72rem">
                        استخدم زر «تسجيل سلفة» عند الحاجة.
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="pay-card">
        <div class="pay-card-header">
            <div class="pay-card-title-wrap">
                <div class="pay-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19V5M4 19h16"/>
                        <path d="m7 15 4-4 3 3 5-6"/>
                    </svg>
                </div>

                <div>
                    <h3 class="pay-card-title">كشف حساب الموظف</h3>
                    <span class="pay-card-subtitle">
                        الاستحقاقات، السلف، الخصومات والمدفوعات.
                    </span>
                </div>
            </div>

            <div class="pay-card-actions">
                @can('payroll.adjustments.manage')
                    <button
                        class="btn btn-sm btn-outline js-pay-modal"
                        type="button"
                        data-modal="adjustmentModal"
                    >
                        إضافة حركة
                    </button>
                @endcan

                @can('payroll.documents.print')
                    <a
                        class="btn btn-sm btn-gold"
                        href="{{ route('payroll.employees.statement.print', [
                            'employee' => $employee,
                            'from' => request('from'),
                            'to' => request('to'),
                        ]) }}"
                        target="_blank"
                    >
                        طباعة
                    </a>
                @endcan
            </div>
        </div>

        <div class="ledger-table-wrap">
            @if($ledgerEntries->count())
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>البيان</th>
                            <th>النوع</th>
                            <th>دائن للموظف</th>
                            <th>مدين / مدفوع</th>
                            <th>المرجع</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($ledgerEntries as $entry)
                            <tr>
                                <td>{{ $entry->entry_date?->format('Y-m-d') }}</td>

                                <td>
                                    <strong style="font-weight:750">
                                        {{ $entry->description }}
                                    </strong>
                                </td>

                                <td>
                                    <span class="pay-badge">
                                        {{ $entry->entry_type }}
                                    </span>
                                </td>

                                <td class="pay-money pay-credit">
                                    {{ $entry->direction === 'credit'
                                        ? number_format((float) $entry->amount, 2)
                                        : '—' }}
                                </td>

                                <td class="pay-money pay-debit">
                                    {{ $entry->direction === 'debit'
                                        ? number_format((float) $entry->amount, 2)
                                        : '—' }}
                                </td>

                                <td>{{ $entry->reference ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="pay-empty">
                    <div class="pay-empty-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 3v18h18"/>
                            <path d="m7 15 4-4 3 3 5-6"/>
                        </svg>
                    </div>

                    <strong>لا توجد حركات مالية لهذا الموظف</strong>

                    <div style="margin-top:.3rem;font-size:.72rem">
                        ستظهر هنا الاستحقاقات والمدفوعات والسلف بعد ترحيلها.
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if(method_exists($ledgerEntries, 'links'))
        <div style="margin-top:1rem">
            {{ $ledgerEntries->links() }}
        </div>
    @endif
</div>

@can('payroll.manage')
<div class="pay-modal" id="compensationModal" aria-hidden="true">
    <div class="pay-modal-backdrop" data-pay-close></div>

    <div class="pay-modal-dialog" role="dialog" aria-modal="true">
        <div class="pay-modal-header">
            <div>
                <h3>{{ $compensation ? 'تعديل إعداد الراتب' : 'إعداد راتب الموظف' }}</h3>
                <p>{{ $employee->full_name }}</p>
            </div>

            <button class="pay-modal-close" type="button" data-pay-close>×</button>
        </div>

        <form
            method="POST"
            action="{{ route('payroll.employees.compensation', $employee) }}"
        >
            @csrf

            <div class="pay-modal-body">
                <div class="pay-modal-grid">
                    <div class="form-group">
                        <label class="form-label">أساس الراتب *</label>

                        <select class="form-input" name="salary_basis" required>
                            @foreach($salaryBasisLabels as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected(
                                        old(
                                            'salary_basis',
                                            $compensation?->salary_basis ?? 'monthly'
                                        ) === $value
                                    )
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الراتب الأساسي *</label>

                        <input
                            class="form-input"
                            type="number"
                            step="0.01"
                            min="0"
                            name="base_salary"
                            value="{{ old('base_salary', $compensation?->base_salary) }}"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">العملة *</label>

                        <select class="form-input" name="currency_id" required>
                            @foreach($currencies as $currency)
                                <option
                                    value="{{ $currency->id }}"
                                    @selected(
                                        (int) old(
                                            'currency_id',
                                            $compensation?->currency_id
                                        ) === $currency->id
                                    )
                                >
                                    {{ $currency->code }}
                                    —
                                    {{ $currency->symbol }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ساري من *</label>

                        <input
                            class="form-input"
                            type="date"
                            name="effective_from"
                            value="{{ old(
                                'effective_from',
                                $compensation?->effective_from?->format('Y-m-d')
                                    ?? now()->toDateString()
                            ) }}"
                            required
                        >
                    </div>
                </div>
            </div>

            <div class="pay-modal-footer">
                <button class="btn btn-gold" type="submit">
                    حفظ إعداد الراتب
                </button>

                <button class="btn btn-ghost" type="button" data-pay-close>
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

@can('payroll.adjustments.manage')
<div class="pay-modal" id="adjustmentModal" aria-hidden="true">
    <div class="pay-modal-backdrop" data-pay-close></div>

    <div class="pay-modal-dialog" role="dialog" aria-modal="true">
        <div class="pay-modal-header">
            <div>
                <h3>إضافة حركة راتب</h3>
                <p>بدل أو مكافأة أو خصم للموظف {{ $employee->full_name }}</p>
            </div>

            <button class="pay-modal-close" type="button" data-pay-close>×</button>
        </div>

        <form
            method="POST"
            action="{{ route('payroll.employees.adjustments', $employee) }}"
        >
            @csrf

            <div class="pay-modal-body">
                <div class="pay-modal-grid">
                    <div class="form-group">
                        <label class="form-label">نوع الحركة *</label>

                        <select class="form-input" name="kind" required>
                            <option value="allowance">بدل</option>
                            <option value="bonus">مكافأة</option>
                            <option value="deduction">خصم</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">القيمة *</label>

                        <input
                            class="form-input"
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="amount"
                            required
                        >
                    </div>

                    <div class="form-group pay-modal-full">
                        <label class="form-label">البيان *</label>

                        <input
                            class="form-input"
                            name="name"
                            maxlength="190"
                            required
                        >
                    </div>

                    <div class="form-group pay-modal-full">
                        <label class="form-label">دورة الرواتب</label>

                        <select class="form-input" name="payroll_period_id">
                            <option value="">بدون دورة محددة</option>

                            @foreach($periods as $period)
                                <option value="{{ $period->id }}">
                                    {{ $period->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group pay-modal-full">
                        <label style="display:flex;gap:.55rem;align-items:center;cursor:pointer">
                            <input
                                type="checkbox"
                                name="is_recurring"
                                value="1"
                            >
                            <span>متكرر في كل دورة ضمن فترة السريان</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="pay-modal-footer">
                <button class="btn btn-gold" type="submit">
                    إضافة الحركة
                </button>

                <button class="btn btn-ghost" type="button" data-pay-close>
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

@can('payroll.advances.manage')
<div class="pay-modal" id="advanceModal" aria-hidden="true">
    <div class="pay-modal-backdrop" data-pay-close></div>

    <div class="pay-modal-dialog" role="dialog" aria-modal="true">
        <div class="pay-modal-header">
            <div>
                <h3>تسجيل سلفة</h3>
                <p>{{ $employee->full_name }}</p>
            </div>

            <button class="pay-modal-close" type="button" data-pay-close>×</button>
        </div>

        <form
            method="POST"
            action="{{ route('payroll.employees.advances', $employee) }}"
        >
            @csrf

            <div class="pay-modal-body">
                <div class="pay-modal-grid">
                    <div class="form-group">
                        <label class="form-label">قيمة السلفة *</label>

                        <input
                            class="form-input"
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="amount"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">التاريخ *</label>

                        <input
                            class="form-input"
                            type="date"
                            name="issued_at"
                            value="{{ now()->toDateString() }}"
                            required
                        >
                    </div>

                    <div class="form-group pay-modal-full">
                        <label class="form-label">المرجع</label>

                        <input
                            class="form-input"
                            name="reference"
                            maxlength="120"
                        >
                    </div>
                </div>
            </div>

            <div class="pay-modal-footer">
                <button class="btn btn-gold" type="submit">
                    تسجيل السلفة
                </button>

                <button class="btn btn-ghost" type="button" data-pay-close>
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modals = document.querySelectorAll('.pay-modal');

    const openModal = (id) => {
        const modal = document.getElementById(id);

        if (!modal) {
            return;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        const firstInput = modal.querySelector(
            'input:not([type="hidden"]), select, textarea'
        );

        setTimeout(() => firstInput?.focus(), 60);
    };

    const closeModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.pay-modal.is-open')) {
            document.body.style.overflow = '';
        }
    };

    document.querySelectorAll('.js-pay-modal').forEach((button) => {
        button.addEventListener('click', () => {
            openModal(button.dataset.modal);
        });
    });

    modals.forEach((modal) => {
        modal.querySelectorAll('[data-pay-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(modal));
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('.pay-modal.is-open').forEach((modal) => {
            closeModal(modal);
        });
    });

    @if($errors->any())
        @if(old('salary_basis') !== null || old('base_salary') !== null)
            openModal('compensationModal');
        @elseif(old('kind') !== null || old('payroll_period_id') !== null)
            openModal('adjustmentModal');
        @elseif(old('issued_at') !== null)
            openModal('advanceModal');
        @endif
    @endif
});
</script>

@endsection
