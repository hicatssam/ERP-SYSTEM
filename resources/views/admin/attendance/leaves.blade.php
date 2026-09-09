@extends('layouts.app')

@section('title', 'إجازات الموظفين')

@section('content')

<style>
.leave-page{
    max-width:1280px;
    margin:0 auto;
}

/* ─────────────────────────────────────────────
   Header
───────────────────────────────────────────── */
.leave-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:1rem;
    margin-bottom:1rem;
}

.leave-head-actions{
    display:flex;
    align-items:center;
    gap:.6rem;
    flex-wrap:wrap;
}

/* ─────────────────────────────────────────────
   Stats
───────────────────────────────────────────── */
.leave-stats{
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:.8rem;
    margin-bottom:1rem;
}

.leave-stat{
    position:relative;
    overflow:hidden;
    padding:1rem 1.05rem;
    border:1px solid var(--border);
    border-radius:16px;
    background:var(--surface);
    min-height:100px;
}

.leave-stat::after{
    content:'';
    position:absolute;
    inset:auto -22px -28px auto;
    width:80px;
    height:80px;
    border-radius:999px;
    background:color-mix(in srgb,var(--border) 35%,transparent);
}

.leave-stat-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.8rem;
    position:relative;
    z-index:1;
}

.leave-stat-label{
    color:var(--text-muted);
    font-size:.78rem;
    font-weight:700;
}

.leave-stat-icon{
    width:34px;
    height:34px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:color-mix(in srgb,var(--border) 40%,transparent);
    font-size:1rem;
}

.leave-stat strong{
    position:relative;
    z-index:1;
    display:block;
    margin-top:.45rem;
    font-size:1.55rem;
    line-height:1;
}

.leave-stat small{
    position:relative;
    z-index:1;
    display:block;
    margin-top:.45rem;
    color:var(--text-muted);
    font-size:.68rem;
}

/* ─────────────────────────────────────────────
   Table
───────────────────────────────────────────── */
.leave-table-wrap{
    overflow:auto;
}

.leave-table{
    width:100%;
    border-collapse:collapse;
}

.leave-table th,
.leave-table td{
    padding:.9rem 1rem;
    border-bottom:1px solid var(--border);
    text-align:right;
    vertical-align:middle;
    white-space:nowrap;
}

.leave-table th{
    font-size:.72rem;
    color:var(--text-muted);
    background:color-mix(
        in srgb,
        var(--surface) 88%,
        var(--background)
    );
    font-weight:800;
}

.leave-table tbody tr{
    transition:background .15s ease;
}

.leave-table tbody tr:hover{
    background:color-mix(
        in srgb,
        var(--surface) 93%,
        var(--background)
    );
}

/* Employee */
.leave-employee{
    display:flex;
    align-items:center;
    gap:.7rem;
    min-width:190px;
}

.leave-avatar{
    width:38px;
    height:38px;
    flex:0 0 38px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:900;
    font-size:.82rem;
    background:color-mix(
        in srgb,
        var(--theme-primary, #c7a44a) 14%,
        var(--surface)
    );
    color:var(--theme-primary, #a9872f);
    border:1px solid color-mix(
        in srgb,
        var(--theme-primary, #c7a44a) 22%,
        var(--border)
    );
}

.leave-employee-info strong{
    display:block;
    font-size:.82rem;
}

.leave-employee-info small{
    display:block;
    color:var(--text-muted);
    margin-top:.18rem;
    font-size:.67rem;
}

/* Type */
.leave-type{
    display:inline-flex;
    align-items:center;
    gap:.4rem;
    padding:.33rem .6rem;
    border-radius:999px;
    background:color-mix(in srgb,var(--theme-info) 8%,transparent);
    color:var(--theme-info);
    font-size:.7rem;
    font-weight:800;
}

/* Dates */
.leave-period{
    display:flex;
    align-items:center;
    gap:.55rem;
}

.leave-date{
    display:flex;
    flex-direction:column;
    gap:.15rem;
}

.leave-date small{
    color:var(--text-muted);
    font-size:.62rem;
}

.leave-date strong{
    font-size:.75rem;
    font-weight:800;
}

.leave-period-arrow{
    color:var(--text-muted);
}

/* Days */
.leave-days{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:46px;
    height:34px;
    border-radius:10px;
    font-weight:900;
    background:color-mix(in srgb,var(--border) 38%,transparent);
}

/* Status */
.leave-badge{
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    padding:.32rem .6rem;
    border-radius:999px;
    font-size:.69rem;
    font-weight:900;
}

.leave-badge::before{
    content:'';
    width:6px;
    height:6px;
    border-radius:999px;
    background:currentColor;
}

.leave-pending{
    color:#b7791f;
    background:rgba(245,158,11,.11);
}

.leave-approved{
    color:var(--theme-success);
    background:color-mix(
        in srgb,
        var(--theme-success) 11%,
        transparent
    );
}

.leave-rejected{
    color:var(--theme-danger);
    background:color-mix(
        in srgb,
        var(--theme-danger) 10%,
        transparent
    );
}

.leave-other{
    color:var(--text-muted);
    background:color-mix(
        in srgb,
        var(--border) 50%,
        transparent
    );
}

/* Reason */
.leave-reason{
    max-width:230px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    color:var(--text-muted);
    font-size:.73rem;
}

/* Actions */
.leave-actions{
    display:flex;
    align-items:center;
    gap:.4rem;
}

/* Empty */
.leave-empty{
    padding:3rem 1rem !important;
    text-align:center !important;
}

.leave-empty-icon{
    width:56px;
    height:56px;
    border-radius:16px;
    margin:0 auto .8rem;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.35rem;
    background:color-mix(in srgb,var(--border) 40%,transparent);
}

.leave-empty strong{
    display:block;
    font-size:.9rem;
}

.leave-empty span{
    display:block;
    margin-top:.35rem;
    color:var(--text-muted);
    font-size:.73rem;
}

/* ─────────────────────────────────────────────
   Modal
───────────────────────────────────────────── */
.leave-modal{
    position:fixed;
    inset:0;
    z-index:9999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:1rem;
}

.leave-modal.open{
    display:flex;
}

.leave-backdrop{
    position:absolute;
    inset:0;
    background:rgba(15,23,42,.58);
    backdrop-filter:blur(4px);
}

.leave-dialog{
    position:relative;
    z-index:2;
    width:min(720px,100%);
    max-height:calc(100vh - 2rem);
    overflow:auto;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:20px;
    box-shadow:0 24px 70px rgba(0,0,0,.22);
}

.leave-dialog-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
    padding:1.15rem 1.25rem;
    border-bottom:1px solid var(--border);
}

.leave-dialog-head h3{
    margin:0;
    font-size:1rem;
}

.leave-dialog-head p{
    margin:.3rem 0 0;
    color:var(--text-muted);
    font-size:.7rem;
}

.leave-dialog-body{
    padding:1.25rem;
}

.leave-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:.9rem;
}

.leave-form-full{
    grid-column:1/-1;
}

.leave-dialog-footer{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:.6rem;
    padding:1rem 1.25rem;
    border-top:1px solid var(--border);
}

/* Info block inside modal */
.leave-info-box{
    display:flex;
    gap:.7rem;
    align-items:flex-start;
    padding:.8rem .9rem;
    border-radius:13px;
    background:color-mix(
        in srgb,
        var(--theme-info) 7%,
        transparent
    );
    border:1px solid color-mix(
        in srgb,
        var(--theme-info) 15%,
        var(--border)
    );
    color:var(--text-muted);
    font-size:.7rem;
    line-height:1.7;
}

/* Pagination */
.leave-pagination{
    margin-top:1rem;
}

/* Responsive */
@media(max-width:1050px){
    .leave-stats{
        grid-template-columns:repeat(3,1fr);
    }
}

@media(max-width:760px){
    .leave-head{
        flex-direction:column;
        align-items:stretch;
    }

    .leave-head-actions{
        width:100%;
    }

    .leave-head-actions .btn{
        flex:1;
        justify-content:center;
    }

    .leave-stats{
        grid-template-columns:repeat(2,1fr);
    }

    .leave-form-grid{
        grid-template-columns:1fr;
    }

    .leave-form-full{
        grid-column:auto;
    }
}

@media(max-width:480px){
    .leave-stats{
        grid-template-columns:1fr 1fr;
    }

    .leave-stat{
        padding:.85rem;
        min-height:90px;
    }

    .leave-stat strong{
        font-size:1.3rem;
    }
}
</style>

@php
    /*
     * Stats are based on the currently loaded result set/page,
     * so this view does not require controller changes.
     */
    $leaveRows = method_exists($requests, 'getCollection')
        ? $requests->getCollection()
        : collect($requests);

    $pendingCount = $leaveRows
        ->where('status', 'pending')
        ->count();

    $approvedCount = $leaveRows
        ->where('status', 'approved')
        ->count();

    $rejectedCount = $leaveRows
        ->where('status', 'rejected')
        ->count();

    $totalLeaveDays = $leaveRows
        ->sum(fn ($leave) => (float) ($leave->total_days ?? 0));

    $totalRequests = method_exists($requests, 'total')
        ? $requests->total()
        : $leaveRows->count();
@endphp

<div class="leave-page">

    {{-- Header --}}
    <div class="leave-head">
        <div>
            <h1 class="page-heading">إجازات الموظفين</h1>
            <p class="page-subheading">
                إدارة طلبات الإجازة، الاعتماد والرفض وربطها بسجل حضور الموظفين.
            </p>
        </div>

        <div class="leave-head-actions">
            <a
                class="btn btn-outline"
                href="{{ route('attendance.index') }}"
            >
                العودة للحضور
            </a>

            @can('attendance.leaves.manage')
                <button
                    type="button"
                    class="btn btn-gold"
                    id="openLeaveModal"
                >
                    + طلب إجازة جديد
                </button>
            @endcan
        </div>
    </div>

    {{-- Stats --}}
    <div class="leave-stats">

        <div class="leave-stat">
            <div class="leave-stat-head">
                <span class="leave-stat-label">
                    إجمالي الطلبات
                </span>
                <span class="leave-stat-icon">▤</span>
            </div>

            <strong>{{ number_format($totalRequests) }}</strong>
            <small>جميع طلبات الإجازة</small>
        </div>

        <div class="leave-stat">
            <div class="leave-stat-head">
                <span class="leave-stat-label">
                    بانتظار الاعتماد
                </span>
                <span class="leave-stat-icon">◷</span>
            </div>

            <strong>{{ number_format($pendingCount) }}</strong>
            <small>طلبات تحتاج مراجعة</small>
        </div>

        <div class="leave-stat">
            <div class="leave-stat-head">
                <span class="leave-stat-label">
                    معتمدة
                </span>
                <span class="leave-stat-icon">✓</span>
            </div>

            <strong>{{ number_format($approvedCount) }}</strong>
            <small>طلبات تمت الموافقة عليها</small>
        </div>

        <div class="leave-stat">
            <div class="leave-stat-head">
                <span class="leave-stat-label">
                    مرفوضة
                </span>
                <span class="leave-stat-icon">×</span>
            </div>

            <strong>{{ number_format($rejectedCount) }}</strong>
            <small>طلبات لم يتم اعتمادها</small>
        </div>

        <div class="leave-stat">
            <div class="leave-stat-head">
                <span class="leave-stat-label">
                    أيام الإجازة
                </span>
                <span class="leave-stat-icon">⌁</span>
            </div>

            <strong>
                {{ number_format($totalLeaveDays, 0) }}
            </strong>

            <small>ضمن السجلات المعروضة</small>
        </div>

    </div>

    {{-- Requests --}}
    <div class="card">

        <div class="card-header">
            <div>
                <span class="card-title">
                    طلبات الإجازات
                </span>

                <div
                    style="
                        margin-top:.2rem;
                        color:var(--text-muted);
                        font-size:.68rem
                    "
                >
                    مراجعة حالة وفترة كل طلب إجازة
                </div>
            </div>
        </div>

        <div
            class="card-body leave-table-wrap"
            style="padding:0"
        >
            <table class="leave-table">

                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>نوع الإجازة</th>
                        <th>الفترة</th>
                        <th>الأيام</th>
                        <th>السبب</th>
                        <th>الحالة</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($requests as $leave)

                        @php
                            $employeeName =
                                $leave->employee?->full_name
                                ?: 'موظف غير معروف';

                            $initial =
                                mb_substr(
                                    trim($employeeName),
                                    0,
                                    1
                                );

                            $status =
                                $leave->status instanceof \BackedEnum
                                    ? $leave->status->value
                                    : (string) $leave->status;
                        @endphp

                        <tr>

                            {{-- Employee --}}
                            <td>
                                <div class="leave-employee">

                                    <div class="leave-avatar">
                                        {{ $initial }}
                                    </div>

                                    <div class="leave-employee-info">
                                        <strong>
                                            {{ $employeeName }}
                                        </strong>

                                        <small>
                                            {{ $leave->employee?->job_title ?: 'موظف' }}
                                        </small>
                                    </div>

                                </div>
                            </td>

                            {{-- Type --}}
                            <td>
                                <span class="leave-type">
                                    {{ $leave->leaveType?->name ?: '—' }}
                                </span>
                            </td>

                            {{-- Period --}}
                            <td>
                                <div class="leave-period">

                                    <div class="leave-date">
                                        <small>من</small>
                                        <strong>
                                            {{ $leave->start_date?->format('Y-m-d') ?? '—' }}
                                        </strong>
                                    </div>

                                    <span class="leave-period-arrow">
                                        ←
                                    </span>

                                    <div class="leave-date">
                                        <small>إلى</small>
                                        <strong>
                                            {{ $leave->end_date?->format('Y-m-d') ?? '—' }}
                                        </strong>
                                    </div>

                                </div>
                            </td>

                            {{-- Days --}}
                            <td>
                                <span class="leave-days">
                                    {{ $leave->total_days ?? 0 }}
                                </span>
                            </td>

                            {{-- Reason --}}
                            <td>
                                <div
                                    class="leave-reason"
                                    title="{{ $leave->reason }}"
                                >
                                    {{ $leave->reason ?: 'بدون سبب مسجل' }}
                                </div>
                            </td>

                            {{-- Status --}}
                            <td>
                                @if($status === 'pending')

                                    <span
                                        class="leave-badge leave-pending"
                                    >
                                        قيد الانتظار
                                    </span>

                                @elseif($status === 'approved')

                                    <span
                                        class="leave-badge leave-approved"
                                    >
                                        معتمدة
                                    </span>

                                @elseif($status === 'rejected')

                                    <span
                                        class="leave-badge leave-rejected"
                                    >
                                        مرفوضة
                                    </span>

                                @else

                                    <span
                                        class="leave-badge leave-other"
                                    >
                                        @statusArabic($status)
                                    </span>

                                @endif
                            </td>

                            {{-- Actions --}}
                            <td>
                                <div class="leave-actions">

                                    @can('attendance.leaves.approve')

                                        @if($status === 'pending')

                                            <form
                                                method="POST"
                                                action="{{ route(
                                                    'attendance.leaves.approve',
                                                    $leave
                                                ) }}"
                                            >
                                                @csrf

                                                <button
                                                    class="btn btn-sm btn-gold"
                                                    type="submit"
                                                >
                                                    اعتماد
                                                </button>
                                            </form>

                                            <form
                                                method="POST"
                                                action="{{ route(
                                                    'attendance.leaves.reject',
                                                    $leave
                                                ) }}"
                                                class="js-leave-reject"
                                            >
                                                @csrf

                                                <button
                                                    class="btn btn-sm btn-outline"
                                                    type="submit"
                                                >
                                                    رفض
                                                </button>
                                            </form>

                                        @else

                                            <span
                                                style="
                                                    color:var(--text-muted);
                                                    font-size:.7rem
                                                "
                                            >
                                                تمت المعالجة
                                            </span>

                                        @endif

                                    @else

                                        <span
                                            style="
                                                color:var(--text-muted);
                                                font-size:.7rem
                                            "
                                        >
                                            —
                                        </span>

                                    @endcan

                                </div>
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="7"
                                class="leave-empty"
                            >
                                <div class="leave-empty-icon">
                                    ◫
                                </div>

                                <strong>
                                    لا توجد طلبات إجازة
                                </strong>

                                <span>
                                    عند إضافة أول طلب سيظهر هنا.
                                </span>
                            </td>
                        </tr>

                    @endforelse
                </tbody>

            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if(method_exists($requests, 'links'))
        <div class="leave-pagination">
            {{ $requests->links() }}
        </div>
    @endif

</div>


{{-- =========================================================
     New Leave Modal
========================================================= --}}
@can('attendance.leaves.manage')

<div
    class="leave-modal"
    id="leaveModal"
    aria-hidden="true"
>
    <div
        class="leave-backdrop"
        data-leave-close
    ></div>

    <div
        class="leave-dialog"
        role="dialog"
        aria-modal="true"
    >

        <div class="leave-dialog-head">
            <div>
                <h3>تسجيل طلب إجازة</h3>
                <p>
                    حدد الموظف، نوع الإجازة والفترة المطلوبة.
                </p>
            </div>

            <button
                type="button"
                class="btn btn-ghost"
                data-leave-close
            >
                ×
            </button>
        </div>

        <form
            method="POST"
            action="{{ route('attendance.leaves.store') }}"
            id="leaveForm"
        >
            @csrf

            <div class="leave-dialog-body">

                <div class="leave-form-grid">

                    {{-- Employee --}}
                    <div class="form-group">
                        <label
                            class="form-label"
                            for="leaveEmployee"
                        >
                            الموظف
                        </label>

                        <select
                            class="form-input"
                            name="employee_id"
                            id="leaveEmployee"
                            required
                        >
                            <option value="">
                                اختر الموظف
                            </option>

                            @foreach($employees as $employee)
                                <option
                                    value="{{ $employee->id }}"
                                    @selected(
                                        old('employee_id')
                                        == $employee->id
                                    )
                                >
                                    {{ $employee->full_name }}

                                    @if($employee->job_title)
                                        — {{ $employee->job_title }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Type --}}
                    <div class="form-group">
                        <label
                            class="form-label"
                            for="leaveType"
                        >
                            نوع الإجازة
                        </label>

                        <select
                            class="form-input"
                            name="leave_type_id"
                            id="leaveType"
                            required
                        >
                            <option value="">
                                اختر نوع الإجازة
                            </option>

                            @foreach($leaveTypes as $type)
                                <option
                                    value="{{ $type->id }}"
                                    @selected(
                                        old('leave_type_id')
                                        == $type->id
                                    )
                                >
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Start --}}
                    <div class="form-group">
                        <label
                            class="form-label"
                            for="leaveStartDate"
                        >
                            بداية الإجازة
                        </label>

                        <input
                            class="form-input"
                            type="date"
                            name="start_date"
                            id="leaveStartDate"
                            value="{{ old('start_date') }}"
                            required
                        >
                    </div>

                    {{-- End --}}
                    <div class="form-group">
                        <label
                            class="form-label"
                            for="leaveEndDate"
                        >
                            نهاية الإجازة
                        </label>

                        <input
                            class="form-input"
                            type="date"
                            name="end_date"
                            id="leaveEndDate"
                            value="{{ old('end_date') }}"
                            required
                        >
                    </div>

                    {{-- Reason --}}
                    <div class="form-group leave-form-full">
                        <label
                            class="form-label"
                            for="leaveReason"
                        >
                            سبب الإجازة
                        </label>

                        <textarea
                            class="form-input"
                            name="reason"
                            id="leaveReason"
                            rows="4"
                            placeholder="أدخل سبب طلب الإجازة..."
                        >{{ old('reason') }}</textarea>
                    </div>

                    {{-- Helper --}}
                    <div class="leave-info-box leave-form-full">
                        <span>ⓘ</span>

                        <span>
                            بعد تسجيل الطلب سيبقى بحالة
                            <strong>قيد الانتظار</strong>
                            حتى تتم مراجعته واعتماده من المستخدم
                            المخول بصلاحية اعتماد الإجازات.
                        </span>
                    </div>

                </div>
            </div>

            <div class="leave-dialog-footer">

                <button
                    type="button"
                    class="btn btn-ghost"
                    data-leave-close
                >
                    إلغاء
                </button>

                <button
                    class="btn btn-gold"
                    type="submit"
                >
                    تسجيل الطلب
                </button>

            </div>
        </form>
    </div>
</div>

@endcan


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const modal = document.getElementById('leaveModal');
    const openButton = document.getElementById('openLeaveModal');

    if (modal) {

        const openModal = () => {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');

            document.body.style.overflow = 'hidden';
        };

        const closeModal = () => {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');

            document.body.style.overflow = '';
        };

        if (openButton) {
            openButton.addEventListener('click', openModal);
        }

        modal
            .querySelectorAll('[data-leave-close]')
            .forEach(function (button) {
                button.addEventListener(
                    'click',
                    closeModal
                );
            });

        document.addEventListener(
            'keydown',
            function (event) {
                if (
                    event.key === 'Escape'
                    && modal.classList.contains('open')
                ) {
                    closeModal();
                }
            }
        );

        /*
         * If Laravel returns validation errors,
         * reopen the form automatically.
         */
        @if($errors->any())
            openModal();
        @endif
    }


    /*
     * Simple protection before rejecting a leave request.
     */
    document
        .querySelectorAll('.js-leave-reject')
        .forEach(function (form) {

            form.addEventListener(
                'submit',
                function (event) {

                    if (
                        ! confirm(
                            'هل أنت متأكد من رفض طلب الإجازة؟'
                        )
                    ) {
                        event.preventDefault();
                    }

                }
            );

        });


    /*
     * End date cannot be before start date.
     */
    const startDate =
        document.getElementById('leaveStartDate');

    const endDate =
        document.getElementById('leaveEndDate');

    if (startDate && endDate) {

        const syncDates = () => {

            if (!startDate.value) {
                endDate.removeAttribute('min');
                return;
            }

            endDate.min = startDate.value;

            if (
                endDate.value
                && endDate.value < startDate.value
            ) {
                endDate.value = startDate.value;
            }
        };

        startDate.addEventListener(
            'change',
            syncDates
        );

        syncDates();
    }

});
</script>
@endpush

@endsection