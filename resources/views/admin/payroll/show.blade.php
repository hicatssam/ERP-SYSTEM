@extends('layouts.app')

@section('title', $period->name)

@section('content')

<style>
.pay-period-page{
    max-width:1280px;
    margin:0 auto;
}

.pay-period-header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
    margin-bottom:1rem;
}

.pay-period-title{
    margin:0;
    color:var(--text);
    font-size:1.65rem;
    font-weight:850;
}

.pay-period-meta{
    display:flex;
    align-items:center;
    gap:.55rem;
    flex-wrap:wrap;
    margin-top:.35rem;
    color:var(--text-muted);
    font-size:.8rem;
}

.pay-period-actions{
    display:flex;
    align-items:center;
    gap:.65rem;
    flex-wrap:wrap;
}

.pay-status{
    display:inline-flex;
    align-items:center;
    padding:.35rem .7rem;
    border-radius:999px;
    font-size:.72rem;
    font-weight:800;
}

.pay-status-draft{
    color:var(--theme-info);
    background:color-mix(in srgb,var(--theme-info) 12%,transparent);
}

.pay-status-calculated{
    color:var(--theme-warning);
    background:color-mix(in srgb,var(--theme-warning) 14%,transparent);
}

.pay-status-approved{
    color:var(--theme-primary);
    background:color-mix(in srgb,var(--theme-primary) 12%,transparent);
}

.pay-status-paid,
.pay-status-closed{
    color:var(--theme-success);
    background:color-mix(in srgb,var(--theme-success) 12%,transparent);
}

.pay-summary-grid{
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:.8rem;
    margin-bottom:1rem;
}

.pay-summary-card{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:15px;
    padding:.95rem 1rem;
    min-height:102px;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
}

.pay-summary-label{
    color:var(--text-muted);
    font-size:.72rem;
    font-weight:700;
}

.pay-summary-value{
    color:var(--text);
    font-size:1.25rem;
    font-weight:850;
    margin-top:.45rem;
}

.pay-summary-value.success{
    color:var(--theme-success);
}

.pay-summary-value.danger{
    color:var(--theme-danger);
}

.pay-summary-note{
    color:var(--text-muted);
    font-size:.68rem;
    margin-top:.2rem;
}

.pay-notice{
    display:flex;
    gap:.7rem;
    padding:.9rem 1rem;
    margin-bottom:1rem;
    border-radius:13px;
    border:1px solid color-mix(in srgb,var(--theme-warning) 28%,var(--border));
    background:color-mix(in srgb,var(--theme-warning) 7%,var(--surface));
}

.pay-card{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:16px;
    overflow:hidden;
}

.pay-card-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:1rem;
    padding:1rem 1.15rem;
    border-bottom:1px solid var(--border);
}

.pay-card-title{
    display:flex;
    align-items:center;
    gap:.7rem;
}

.pay-card-title h3{
    margin:0;
    font-size:.95rem;
}

.pay-card-title small{
    display:block;
    color:var(--text-muted);
    font-size:.72rem;
    margin-top:.15rem;
}

.pay-table-wrap{
    overflow:auto;
}

.pay-table{
    width:100%;
    border-collapse:collapse;
}

.pay-table th,
.pay-table td{
    padding:.85rem .75rem;
    border-bottom:1px solid var(--border);
    text-align:right;
    white-space:nowrap;
    vertical-align:middle;
}

.pay-table th{
    background:color-mix(in srgb,var(--surface) 88%,var(--background));
    color:var(--text-muted);
    font-size:.71rem;
    font-weight:800;
}

.pay-table td{
    font-size:.82rem;
}

.pay-employee{
    display:flex;
    align-items:center;
    gap:.65rem;
}

.pay-employee-avatar{
    width:36px;
    height:36px;
    border-radius:10px;
    display:grid;
    place-items:center;
    overflow:hidden;
    background:color-mix(in srgb,var(--theme-primary) 10%,transparent);
    color:var(--theme-primary);
    font-size:.76rem;
    font-weight:850;
}

.pay-employee-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.pay-employee-info a{
    color:var(--text);
    font-weight:800;
    text-decoration:none;
}

.pay-employee-info small{
    display:block;
    color:var(--text-muted);
    margin-top:.12rem;
}

.pay-money{
    font-variant-numeric:tabular-nums;
    font-weight:750;
}

.pay-money.positive{
    color:var(--theme-success);
}

.pay-money.negative{
    color:var(--theme-danger);
}

.pay-money.strong{
    font-weight:900;
}

.pay-row-actions{
    display:flex;
    align-items:center;
    gap:.4rem;
}

.pay-empty{
    padding:3rem 1rem;
    text-align:center;
    color:var(--text-muted);
}

/* Payment modal */
.salary-pay-modal{
    position:fixed;
    inset:0;
    z-index:9999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:1rem;
}

.salary-pay-modal.is-open{
    display:flex;
}

.salary-pay-backdrop{
    position:absolute;
    inset:0;
    background:rgba(15,23,42,.58);
    backdrop-filter:blur(3px);
}

.salary-pay-dialog{
    position:relative;
    z-index:2;
    width:min(520px,100%);
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:18px;
    overflow:hidden;
}

.salary-pay-header{
    display:flex;
    justify-content:space-between;
    padding:1rem 1.15rem;
    border-bottom:1px solid var(--border);
}

.salary-pay-header h3{
    margin:0;
}

.salary-pay-header p{
    margin:.25rem 0 0;
    color:var(--text-muted);
}

.salary-pay-close{
    width:36px;
    height:36px;
    border:0;
    border-radius:10px;
    cursor:pointer;
}

.salary-pay-body{
    padding:1.15rem;
}

.salary-pay-footer{
    display:flex;
    gap:.65rem;
    padding:1rem 1.15rem;
    border-top:1px solid var(--border);
}

@media(max-width:1100px){
    .pay-summary-grid{
        grid-template-columns:repeat(3,1fr);
    }
}

@media(max-width:720px){
    .pay-period-header,
    .pay-card-header{
        flex-direction:column;
        align-items:stretch;
    }

    .pay-summary-grid{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:480px){
    .pay-summary-grid{
        grid-template-columns:1fr;
    }
}
</style>

@php
    $items = $period->items ?? collect();

    $employeeCount = $items->count();
    $baseTotal = (float) $items->sum('base_salary');
    $allowancesTotal = (float) $items->sum('allowances_total');
    $bonusesTotal = (float) $items->sum('bonuses_total');
    $deductionsTotal = (float) $items->sum('deductions_total');
    $netTotal = (float) $items->sum('net_salary');
    $payableTotal = (float) $items->sum('payable_amount');

    $statusLabels = [
        'draft' => 'مسودة',
        'calculated' => 'محتسبة',
        'approved' => 'معتمدة',
        'paid' => 'مدفوعة',
        'closed' => 'مغلقة',
    ];

    $zeroSalaryCount = $items
        ->filter(fn ($item) => (float) $item->base_salary <= 0)
        ->count();
@endphp

<div class="pay-period-page">

    <div class="pay-period-header">

        <div>

            <div class="pay-period-meta" style="margin-top:0">
                <a href="{{ route('payroll.index') }}">
                    الرواتب
                </a>

                <span>‹</span>
                <span>{{ $period->code }}</span>
            </div>

            <h1 class="pay-period-title">
                {{ $period->name }}
            </h1>

            <div class="pay-period-meta">

                <span>
                    {{ $period->start_date?->format('Y-m-d') }}
                    —
                    {{ $period->end_date?->format('Y-m-d') }}
                </span>

                <span>•</span>

                <span class="pay-status pay-status-{{ $period->status }}">
                    {{ $statusLabels[$period->status] ?? $period->status }}
                </span>

            </div>

        </div>


        <div class="pay-period-actions">

            @can('payroll.manage')

                @if(in_array($period->status, ['draft', 'calculated'], true))

                    <form
                        method="POST"
                        action="{{ route('payroll.calculate', $period) }}"
                    >
                        @csrf

                        <button
                            class="btn btn-outline"
                            type="submit"
                        >
                            إعادة احتساب الرواتب
                        </button>
                    </form>

                @endif

            @endcan


            @can('payroll.approve')

                @if($period->status === 'calculated')

                    <form
                        method="POST"
                        action="{{ route('payroll.approve', $period) }}"
                        onsubmit="return confirm('اعتماد الدورة وترحيل الاستحقاقات؟')"
                    >
                        @csrf

                        <button
                            class="btn btn-gold"
                            type="submit"
                        >
                            اعتماد وترحيل
                        </button>
                    </form>

                @endif

            @endcan

        </div>

    </div>


    <div class="pay-summary-grid">

        <div class="pay-summary-card">
            <span class="pay-summary-label">عدد الموظفين</span>
            <strong class="pay-summary-value">
                {{ $employeeCount }}
            </strong>
        </div>

        <div class="pay-summary-card">
            <span class="pay-summary-label">الرواتب الأساسية</span>
            <strong class="pay-summary-value">
                {{ number_format($baseTotal, 2) }}
            </strong>
        </div>

        <div class="pay-summary-card">
            <span class="pay-summary-label">البدلات والمكافآت</span>
            <strong class="pay-summary-value success">
                {{ number_format($allowancesTotal + $bonusesTotal, 2) }}
            </strong>
        </div>

        <div class="pay-summary-card">
            <span class="pay-summary-label">الخصومات</span>
            <strong class="pay-summary-value danger">
                {{ number_format($deductionsTotal, 2) }}
            </strong>
        </div>

        <div class="pay-summary-card">
            <span class="pay-summary-label">صافي الرواتب</span>
            <strong class="pay-summary-value">
                {{ number_format($netTotal, 2) }}
            </strong>
        </div>

        <div class="pay-summary-card">
            <span class="pay-summary-label">المستحق الحالي</span>
            <strong class="pay-summary-value">
                {{ number_format($payableTotal, 2) }}
            </strong>
        </div>

    </div>


    @if($zeroSalaryCount > 0)

        <div class="pay-notice">

            <strong>
                يوجد {{ $zeroSalaryCount }} موظف بدون راتب أساسي محدد.
            </strong>

        </div>

    @endif


    <div class="pay-card">

        <div class="pay-card-header">

            <div class="pay-card-title">

                <div>
                    <h3>
                        تفاصيل رواتب الموظفين
                    </h3>

                    <small>
                        جميع الموظفين ضمن دورة الرواتب الحالية
                    </small>
                </div>

            </div>


            <span class="pay-status pay-status-{{ $period->status }}">
                {{ $statusLabels[$period->status] ?? $period->status }}
            </span>

        </div>


        <div class="pay-table-wrap">

            @if($items->count())

                <table class="pay-table">

                    <thead>
                        <tr>
                            <th>الموظف</th>
                            <th>الأساسي</th>
                            <th>البدلات</th>
                            <th>المكافآت</th>
                            <th>الخصومات</th>
                            <th>الصافي</th>
                            <th>المستحق</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>


                    <tbody>

                        @foreach($items as $item)

                            @php
                                $employeeName =
                                    $item->employee?->full_name
                                    ?? 'موظف';

                                $initials =
                                    mb_substr(
                                        trim($employeeName),
                                        0,
                                        2
                                    );
                            @endphp


                            <tr>

                                <td>

                                    <div class="pay-employee">

                                        <div class="pay-employee-avatar">
                                            {{ $initials }}
                                        </div>


                                        <div class="pay-employee-info">

                                            <a
                                                href="{{ route(
                                                    'payroll.employees.show',
                                                    $item->employee
                                                ) }}"
                                            >
                                                {{ $employeeName }}
                                            </a>


                                            <small>
                                                {{ $item->employee?->job_title ?: 'بدون مسمى وظيفي' }}
                                            </small>

                                        </div>

                                    </div>

                                </td>


                                <td class="pay-money">
                                    {{ number_format((float) $item->base_salary, 2) }}
                                </td>

                                <td class="pay-money positive">
                                    {{ number_format((float) $item->allowances_total, 2) }}
                                </td>

                                <td class="pay-money positive">
                                    {{ number_format((float) $item->bonuses_total, 2) }}
                                </td>

                                <td class="pay-money negative">
                                    {{ number_format((float) $item->deductions_total, 2) }}
                                </td>

                                <td class="pay-money strong">
                                    {{ number_format((float) $item->net_salary, 2) }}
                                </td>

                                <td class="pay-money strong">
                                    {{ number_format((float) $item->payable_amount, 2) }}
                                </td>


                                <td>

                                    <span class="pay-status pay-status-{{ $item->status }}">
                                        {{ $statusLabels[$item->status] ?? $item->status }}
                                    </span>

                                </td>


                                <td>

                                    <div class="pay-row-actions">

                                        <a
                                            class="btn btn-sm btn-outline"
                                            href="{{ route(
                                                'payroll.employees.show',
                                                $item->employee
                                            ) }}"
                                        >
                                            الملف المالي
                                        </a>


                                        @can('payroll.pay')

                                            @if(
                                                in_array(
                                                    $item->status,
                                                    ['approved', 'paid'],
                                                    true
                                                )
                                                && (float) $item->payable_amount > 0
                                            )

                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-gold js-open-pay-modal"

                                                    data-action="{{ route(
                                                        'payroll.pay',
                                                        $item
                                                    ) }}"

                                                    data-employee="{{ $employeeName }}"

                                                    data-amount="{{ (float) $item->payable_amount }}"
                                                >
                                                    صرف
                                                </button>

                                            @endif

                                        @endcan

                                    </div>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            @else

                <div class="pay-empty">

                    <strong>
                        لم يتم احتساب الموظفين بعد
                    </strong>

                    <p>
                        اضغط إعادة احتساب الرواتب لإنشاء تفاصيل الدورة.
                    </p>

                </div>

            @endif

        </div>

    </div>

</div>


@can('payroll.pay')

<div
    class="salary-pay-modal"
    id="salaryPayModal"
    aria-hidden="true"
>

    <div
        class="salary-pay-backdrop"
        data-pay-close
    ></div>


    <div class="salary-pay-dialog">

        <div class="salary-pay-header">

            <div>

                <h3>
                    صرف راتب
                </h3>

                <p id="salaryPayEmployee">
                    —
                </p>

            </div>


            <button
                class="salary-pay-close"
                type="button"
                data-pay-close
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            id="salaryPayForm"
        >

            @csrf


            <div class="salary-pay-body">

                <div class="form-group">

                    <label class="form-label">
                        قيمة الدفعة
                    </label>

                    <input
                        class="form-input"
                        id="salaryPayAmount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        name="amount"
                        required
                    >

                </div>


                <div class="form-group">

                    <label class="form-label">
                        رقم المرجع
                    </label>

                    <input
                        class="form-input"
                        name="reference"
                        maxlength="120"
                    >

                </div>


                <div class="form-group">

                    <label class="form-label">
                        ملاحظات
                    </label>

                    <textarea
                        class="form-input"
                        name="notes"
                        rows="3"
                    ></textarea>

                </div>

            </div>


            <div class="salary-pay-footer">

                <button
                    class="btn btn-gold"
                    type="submit"
                >
                    تأكيد الصرف
                </button>


                <button
                    class="btn btn-ghost"
                    type="button"
                    data-pay-close
                >
                    إلغاء
                </button>

            </div>

        </form>

    </div>

</div>

@endcan


@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {

    const modal =
        document.getElementById('salaryPayModal');

    if (!modal) {
        return;
    }

    const form =
        document.getElementById('salaryPayForm');

    const employee =
        document.getElementById('salaryPayEmployee');

    const amount =
        document.getElementById('salaryPayAmount');


    const openModal = function (button) {

        form.action =
            button.dataset.action;

        employee.textContent =
            button.dataset.employee || '—';

        const payable =
            button.dataset.amount || '';

        amount.value =
            payable;

        amount.max =
            payable;

        modal.classList.add('is-open');

        document.body.style.overflow =
            'hidden';
    };


    const closeModal = function () {

        modal.classList.remove('is-open');

        document.body.style.overflow =
            '';
    };


    document
        .querySelectorAll('.js-open-pay-modal')
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {
                    openModal(button);
                }
            );

        });


    modal
        .querySelectorAll('[data-pay-close]')
        .forEach(function (button) {

            button.addEventListener(
                'click',
                closeModal
            );

        });


    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Escape') {
                closeModal();
            }

        }
    );

});
</script>

@endpush

@endsection