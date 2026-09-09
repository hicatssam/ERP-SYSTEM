@extends('layouts.app')

@section('title', 'الحضور والدوام')

@section('content')

<style>
/* =========================================================
   Attendance — Premium UI
========================================================= */

.att-page{
    max-width:1380px;
    margin:0 auto;
}

/* Header */
.att-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
    margin-bottom:1rem;
}

.att-head-actions{
    display:flex;
    align-items:center;
    gap:.55rem;
    flex-wrap:wrap;
}

.att-head-actions .btn{
    min-height:40px;
}

/* Toolbar */
.att-toolbar{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:1rem;
    padding:1rem;
}

.att-toolbar-main{
    display:flex;
    align-items:flex-end;
    gap:.7rem;
    flex-wrap:wrap;
}

.att-toolbar-side{
    display:flex;
    align-items:center;
    gap:.6rem;
}

.att-date-field{
    min-width:220px;
    margin:0;
}

.att-search{
    position:relative;
    min-width:240px;
}

.att-search input{
    padding-right:2.35rem;
}

.att-search-icon{
    position:absolute;
    right:.8rem;
    top:50%;
    transform:translateY(-50%);
    pointer-events:none;
    opacity:.55;
}

/* Stats */
.att-stats{
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:.8rem;
    margin:1rem 0;
}

.att-stat{
    position:relative;
    overflow:hidden;
    min-height:106px;
    padding:1rem 1.05rem;
    border:1px solid var(--border);
    border-radius:17px;
    background:var(--surface);
}

.att-stat::after{
    content:'';
    position:absolute;
    width:84px;
    height:84px;
    border-radius:999px;
    left:-26px;
    bottom:-32px;
    background:color-mix(
        in srgb,
        var(--border) 32%,
        transparent
    );
}

.att-stat-head{
    position:relative;
    z-index:1;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.6rem;
}

.att-stat-label{
    font-size:.73rem;
    font-weight:800;
    color:var(--text-muted);
}

.att-stat-icon{
    width:34px;
    height:34px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:.95rem;
    background:color-mix(
        in srgb,
        var(--border) 40%,
        transparent
    );
}

.att-stat strong{
    position:relative;
    z-index:1;
    display:block;
    margin-top:.55rem;
    font-size:1.55rem;
    line-height:1;
}

.att-stat small{
    position:relative;
    z-index:1;
    display:block;
    margin-top:.45rem;
    color:var(--text-muted);
    font-size:.65rem;
}

/* Stat variants */
.att-stat-present .att-stat-icon{
    color:var(--theme-success);
    background:color-mix(
        in srgb,
        var(--theme-success) 10%,
        transparent
    );
}

.att-stat-absent .att-stat-icon{
    color:var(--theme-danger);
    background:color-mix(
        in srgb,
        var(--theme-danger) 10%,
        transparent
    );
}

.att-stat-leave .att-stat-icon{
    color:var(--theme-info);
    background:color-mix(
        in srgb,
        var(--theme-info) 10%,
        transparent
    );
}

/* Main card */
.att-card-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:1rem;
}

.att-card-head-meta{
    display:flex;
    flex-direction:column;
    gap:.18rem;
}

.att-card-subtitle{
    color:var(--text-muted);
    font-size:.67rem;
}

.att-count-pill{
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    padding:.32rem .65rem;
    border-radius:999px;
    font-size:.68rem;
    font-weight:800;
    color:var(--text-muted);
    background:color-mix(
        in srgb,
        var(--border) 45%,
        transparent
    );
}

/* Table */
.att-table-wrap{
    overflow:auto;
}

.att-table{
    width:100%;
    border-collapse:collapse;
    min-width:1050px;
}

.att-table th,
.att-table td{
    padding:.88rem .9rem;
    border-bottom:1px solid var(--border);
    text-align:right;
    vertical-align:middle;
    white-space:nowrap;
}

.att-table th{
    position:sticky;
    top:0;
    z-index:3;
    font-size:.68rem;
    font-weight:900;
    color:var(--text-muted);
    background:color-mix(
        in srgb,
        var(--surface) 94%,
        var(--background)
    );
}

.att-table tbody tr{
    transition:background .15s ease;
}

.att-table tbody tr:hover{
    background:color-mix(
        in srgb,
        var(--surface) 92%,
        var(--background)
    );
}

/* Employee */
.att-employee{
    display:flex;
    align-items:center;
    gap:.7rem;
    min-width:190px;
}

.att-avatar{
    width:40px;
    height:40px;
    flex:0 0 40px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:.82rem;
    font-weight:900;
    color:var(--theme-primary, #a9872f);
    background:color-mix(
        in srgb,
        var(--theme-primary, #c7a44a) 13%,
        var(--surface)
    );
    border:1px solid color-mix(
        in srgb,
        var(--theme-primary, #c7a44a) 22%,
        var(--border)
    );
}

.att-employee-info strong{
    display:block;
    font-size:.8rem;
}

.att-employee-info small{
    display:block;
    margin-top:.18rem;
    font-size:.65rem;
    color:var(--text-muted);
}

/* Status */
.att-badge{
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    padding:.31rem .58rem;
    border-radius:999px;
    font-size:.67rem;
    font-weight:900;
}

.att-badge::before{
    content:'';
    width:6px;
    height:6px;
    border-radius:999px;
    background:currentColor;
}

.att-present{
    color:var(--theme-success);
    background:color-mix(
        in srgb,
        var(--theme-success) 11%,
        transparent
    );
}

.att-absent{
    color:var(--theme-danger);
    background:color-mix(
        in srgb,
        var(--theme-danger) 10%,
        transparent
    );
}

.att-leave{
    color:var(--theme-info);
    background:color-mix(
        in srgb,
        var(--theme-info) 10%,
        transparent
    );
}

.att-holiday{
    color:#8b5cf6;
    background:rgba(139,92,246,.1);
}

.att-none{
    color:var(--text-muted);
    background:color-mix(
        in srgb,
        var(--border) 52%,
        transparent
    );
}

/* Time */
.att-time{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:62px;
    height:32px;
    border-radius:9px;
    font-size:.73rem;
    font-weight:800;
    background:color-mix(
        in srgb,
        var(--border) 32%,
        transparent
    );
}

.att-time-empty{
    color:var(--text-muted);
}

/* Minutes */
.att-minutes{
    display:inline-flex;
    align-items:center;
    gap:.22rem;
    font-size:.72rem;
    font-weight:800;
}

.att-minutes.zero{
    color:var(--text-muted);
    font-weight:600;
}

/* Approval */
.att-approved{
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    padding:.3rem .55rem;
    border-radius:999px;
    color:var(--theme-success);
    background:color-mix(
        in srgb,
        var(--theme-success) 10%,
        transparent
    );
    font-size:.66rem;
    font-weight:900;
}

.att-unapproved{
    color:var(--text-muted);
    font-size:.68rem;
}

/* Actions */
.att-row-actions{
    display:flex;
    align-items:center;
    gap:.4rem;
}

/* Empty / no search */
.att-no-results{
    display:none;
    padding:2.8rem 1rem;
    text-align:center;
    color:var(--text-muted);
}

.att-no-results strong{
    display:block;
    color:var(--text);
    margin-bottom:.3rem;
}

/* =========================================================
   Modal
========================================================= */

.att-modal{
    position:fixed;
    inset:0;
    z-index:9999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:1rem;
}

.att-modal.open{
    display:flex;
}

.att-backdrop{
    position:absolute;
    inset:0;
    background:rgba(15,23,42,.6);
    backdrop-filter:blur(4px);
}

.att-dialog{
    position:relative;
    z-index:2;
    width:min(680px,100%);
    max-height:calc(100vh - 2rem);
    overflow:auto;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:20px;
    box-shadow:0 26px 80px rgba(0,0,0,.25);
}

.att-dialog-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:1rem;
    padding:1.1rem 1.2rem;
    border-bottom:1px solid var(--border);
}

.att-dialog-title{
    margin:0;
    font-size:1rem;
}

.att-dialog-employee{
    display:block;
    margin-top:.3rem;
    font-size:.68rem;
    color:var(--text-muted);
}

.att-dialog-body{
    padding:1.2rem;
}

.att-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:.9rem;
}

.att-full{
    grid-column:1/-1;
}

.att-dialog-info{
    display:flex;
    align-items:flex-start;
    gap:.65rem;
    padding:.8rem .9rem;
    border:1px solid color-mix(
        in srgb,
        var(--theme-info) 15%,
        var(--border)
    );
    border-radius:12px;
    background:color-mix(
        in srgb,
        var(--theme-info) 6%,
        transparent
    );
    font-size:.69rem;
    line-height:1.7;
    color:var(--text-muted);
}

.att-dialog-footer{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:.6rem;
    padding:1rem 1.2rem;
    border-top:1px solid var(--border);
}

/* Responsive */
@media(max-width:1050px){
    .att-stats{
        grid-template-columns:repeat(3,1fr);
    }

    .att-toolbar{
        align-items:stretch;
        flex-direction:column;
    }

    .att-toolbar-side{
        width:100%;
    }

    .att-search{
        width:100%;
    }
}

@media(max-width:760px){
    .att-head{
        flex-direction:column;
        align-items:stretch;
    }

    .att-head-actions{
        width:100%;
    }

    .att-head-actions .btn{
        flex:1;
        justify-content:center;
    }

    .att-stats{
        grid-template-columns:repeat(2,1fr);
    }

    .att-grid{
        grid-template-columns:1fr;
    }

    .att-full{
        grid-column:auto;
    }
}

@media(max-width:480px){
    .att-stats{
        grid-template-columns:1fr 1fr;
    }

    .att-stat{
        padding:.85rem;
        min-height:94px;
    }

    .att-stat strong{
        font-size:1.3rem;
    }

    .att-toolbar-main{
        flex-direction:column;
        align-items:stretch;
    }

    .att-date-field{
        min-width:0;
        width:100%;
    }
}
</style>

@php
    $present = $records
        ->where('status', 'present')
        ->count();

    $absent = $records
        ->where('status', 'absent')
        ->count();

    $leave = $records
        ->where('status', 'leave')
        ->count();

    $approved = $records
        ->filter(fn ($record) => $record->approved_at !== null)
        ->count();

    $registered = $records->count();

    $unregistered = max(
        0,
        $employees->count() - $registered
    );
@endphp


<div class="att-page">

    {{-- =====================================================
         Header
    ====================================================== --}}
    <div class="att-head">

        <div>
            <h1 class="page-heading">
                الحضور والدوام
            </h1>

            <p class="page-subheading">
                متابعة حضور الموظفين، التأخير، الانصراف،
                الساعات الإضافية وربط السجلات بالرواتب.
            </p>
        </div>

        <div class="att-head-actions">

            @if(
                app(
                    \App\Services\AttendanceFeatureService::class
                )->biometricEnabled()
            )

                @can('attendance.devices.view')
                    <a
                        class="btn btn-outline"
                        href="{{ route('attendance.devices.index') }}"
                    >
                        أجهزة البصمة
                    </a>
                @endcan

            @endif

            <a
                class="btn btn-outline"
                href="{{ route('attendance.shifts.index') }}"
            >
                الورديات
            </a>

            <a
                class="btn btn-outline"
                href="{{ route('attendance.leaves.index') }}"
            >
                الإجازات
            </a>

        </div>
    </div>


    {{-- =====================================================
         Date + Search
    ====================================================== --}}
    <form
        method="GET"
        action="{{ route('attendance.index') }}"
        class="card"
    >
        <div class="att-toolbar">

            <div class="att-toolbar-main">

                <div class="form-group att-date-field">
                    <label class="form-label">
                        تاريخ الدوام
                    </label>

                    <input
                        class="form-input"
                        type="date"
                        name="date"
                        value="{{ $date }}"
                    >
                </div>

                <button
                    class="btn btn-gold"
                    type="submit"
                >
                    عرض السجل
                </button>

            </div>

            <div class="att-toolbar-side">

                <div class="att-search">
                    <span class="att-search-icon">
                        ⌕
                    </span>

                    <input
                        type="search"
                        class="form-input"
                        id="attendanceSearch"
                        placeholder="بحث باسم الموظف..."
                        autocomplete="off"
                    >
                </div>

            </div>

        </div>
    </form>


    {{-- =====================================================
         Statistics
    ====================================================== --}}
    <div class="att-stats">

        <div class="att-stat">
            <div class="att-stat-head">
                <span class="att-stat-label">
                    إجمالي الموظفين
                </span>

                <span class="att-stat-icon">
                    ◉
                </span>
            </div>

            <strong>
                {{ number_format($employees->count()) }}
            </strong>

            <small>
                الموظفون المسجلون في النظام
            </small>
        </div>


        <div class="att-stat att-stat-present">
            <div class="att-stat-head">
                <span class="att-stat-label">
                    حاضر
                </span>

                <span class="att-stat-icon">
                    ✓
                </span>
            </div>

            <strong>
                {{ number_format($present) }}
            </strong>

            <small>
                موظفون مسجل حضورهم
            </small>
        </div>


        <div class="att-stat att-stat-absent">
            <div class="att-stat-head">
                <span class="att-stat-label">
                    غائب
                </span>

                <span class="att-stat-icon">
                    ×
                </span>
            </div>

            <strong>
                {{ number_format($absent) }}
            </strong>

            <small>
                موظفون مسجلون كغياب
            </small>
        </div>


        <div class="att-stat att-stat-leave">
            <div class="att-stat-head">
                <span class="att-stat-label">
                    إجازة
                </span>

                <span class="att-stat-icon">
                    ◷
                </span>
            </div>

            <strong>
                {{ number_format($leave) }}
            </strong>

            <small>
                إجازات مرتبطة بهذا اليوم
            </small>
        </div>


        <div class="att-stat">
            <div class="att-stat-head">
                <span class="att-stat-label">
                    سجلات معتمدة
                </span>

                <span class="att-stat-icon">
                    ✓
                </span>
            </div>

            <strong>
                {{ number_format($approved) }}
            </strong>

            <small>
                من أصل {{ number_format($registered) }} سجل
            </small>
        </div>

    </div>


    {{-- =====================================================
         Attendance Table
    ====================================================== --}}
    <div class="card">

        <div class="card-header att-card-head">

            <div class="att-card-head-meta">

                <span class="card-title">
                    سجل الموظفين
                </span>

                <span class="att-card-subtitle">
                    {{ $date }}
                </span>

            </div>

            <span class="att-count-pill">
                {{ $registered }} مسجل
                ·
                {{ $unregistered }} غير مسجل
            </span>

        </div>


        <div
            class="card-body att-table-wrap"
            style="padding:0"
        >

            <table
                class="att-table"
                id="attendanceTable"
            >

                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>الحالة</th>
                        <th>الحضور</th>
                        <th>الانصراف</th>
                        <th>التأخير</th>
                        <th>خروج مبكر</th>
                        <th>إضافي</th>
                        <th>الاعتماد</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach($employees as $employee)

                        @php
                            $record = $records->get($employee->id);

                            $status = $record?->status;

                            if ($status instanceof \BackedEnum) {
                                $status = $status->value;
                            }

                            $initial = mb_substr(
                                trim($employee->full_name),
                                0,
                                1
                            );
                        @endphp


                        <tr
                            class="attendance-row"
                            data-search="{{ mb_strtolower(
                                $employee->full_name
                                .' '
                                .($employee->job_title ?? '')
                            ) }}"
                        >

                            {{-- Employee --}}
                            <td>
                                <div class="att-employee">

                                    <div class="att-avatar">
                                        {{ $initial }}
                                    </div>

                                    <div class="att-employee-info">

                                        <strong>
                                            {{ $employee->full_name }}
                                        </strong>

                                        <small>
                                            {{ $employee->job_title ?: 'بدون مسمى وظيفي' }}
                                        </small>

                                    </div>

                                </div>
                            </td>


                            {{-- Status --}}
                            <td>

                                @if(!$record)

                                    <span class="att-badge att-none">
                                        غير مسجل
                                    </span>

                                @elseif($status === 'present')

                                    <span class="att-badge att-present">
                                        حاضر
                                    </span>

                                @elseif($status === 'absent')

                                    <span class="att-badge att-absent">
                                        غائب
                                    </span>

                                @elseif($status === 'leave')

                                    <span class="att-badge att-leave">
                                        إجازة
                                    </span>

                                @elseif(
                                    in_array(
                                        $status,
                                        ['holiday', 'weekend'],
                                        true
                                    )
                                )

                                    <span class="att-badge att-holiday">
                                        @statusArabic($status)
                                    </span>

                                @else

                                    <span class="att-badge att-none">
                                        @statusArabic($status)
                                    </span>

                                @endif

                            </td>


                            {{-- Check in --}}
                            <td>

                                @if($record?->check_in_at)

                                    <span class="att-time">
                                        {{ $record->check_in_at->format('H:i') }}
                                    </span>

                                @else

                                    <span class="att-time att-time-empty">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- Check out --}}
                            <td>

                                @if($record?->check_out_at)

                                    <span class="att-time">
                                        {{ $record->check_out_at->format('H:i') }}
                                    </span>

                                @else

                                    <span class="att-time att-time-empty">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- Late --}}
                            <td>
                                @php
                                    $lateMinutes =
                                        (int) ($record?->late_minutes ?? 0);
                                @endphp

                                <span
                                    class="att-minutes {{ $lateMinutes === 0 ? 'zero' : '' }}"
                                >
                                    {{ $lateMinutes }} د
                                </span>
                            </td>


                            {{-- Early leave --}}
                            <td>
                                @php
                                    $earlyMinutes =
                                        (int) (
                                            $record?->early_leave_minutes
                                            ?? 0
                                        );
                                @endphp

                                <span
                                    class="att-minutes {{ $earlyMinutes === 0 ? 'zero' : '' }}"
                                >
                                    {{ $earlyMinutes }} د
                                </span>
                            </td>


                            {{-- Overtime --}}
                            <td>
                                @php
                                    $overtimeMinutes =
                                        (int) (
                                            $record?->overtime_minutes
                                            ?? 0
                                        );
                                @endphp

                                <span
                                    class="att-minutes {{ $overtimeMinutes === 0 ? 'zero' : '' }}"
                                >
                                    {{ $overtimeMinutes }} د
                                </span>
                            </td>


                            {{-- Approval --}}
                            <td>

                                @if($record?->approved_at)

                                    <span class="att-approved">
                                        ✓ معتمد
                                    </span>

                                @else

                                    <span class="att-unapproved">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- Actions --}}
                            <td>

                                <div class="att-row-actions">

                                    @can('attendance.manage')

                                        <button
                                            type="button"
                                            class="
                                                btn
                                                btn-sm
                                                btn-outline
                                                js-att-open
                                            "

                                            data-name="{{ $employee->full_name }}"

                                            data-action="{{ route(
                                                'attendance.store',
                                                $employee
                                            ) }}"

                                            data-status="{{ $status ?: 'present' }}"

                                            data-shift="{{ $record?->work_shift_id }}"

                                            data-in="{{ $record?->check_in_at?->format(
                                                'Y-m-d\TH:i'
                                            ) }}"

                                            data-out="{{ $record?->check_out_at?->format(
                                                'Y-m-d\TH:i'
                                            ) }}"

                                            data-notes="{{ $record?->notes }}"
                                        >
                                            {{ $record ? 'تعديل' : 'تسجيل' }}
                                        </button>

                                    @endcan


                                    @can('attendance.approve')

                                        @if(
                                            $record
                                            && !$record->approved_at
                                        )

                                            <form
                                                method="POST"
                                                action="{{ route(
                                                    'attendance.approve',
                                                    $record
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

                                        @endif

                                    @endcan

                                </div>

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>


            <div
                class="att-no-results"
                id="attendanceNoResults"
            >
                <strong>
                    لا توجد نتائج مطابقة
                </strong>

                <span>
                    جرّب البحث باسم موظف آخر.
                </span>
            </div>

        </div>

    </div>

</div>


{{-- =========================================================
     Attendance Modal
========================================================= --}}
@can('attendance.manage')

<div
    class="att-modal"
    id="attModal"
    aria-hidden="true"
>

    <div
        class="att-backdrop"
        data-att-close
    ></div>


    <div
        class="att-dialog"
        role="dialog"
        aria-modal="true"
    >

        <div class="att-dialog-head">

            <div>

                <h3 class="att-dialog-title">
                    تسجيل الحضور
                </h3>

                <span
                    class="att-dialog-employee"
                    id="attEmployeeName"
                >
                    —
                </span>

            </div>


            <button
                type="button"
                class="btn btn-ghost"
                data-att-close
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            id="attForm"
        >

            @csrf

            <div class="att-dialog-body">

                <input
                    type="hidden"
                    name="work_date"
                    value="{{ $date }}"
                >


                <div class="att-grid">

                    {{-- Status --}}
                    <div class="form-group">

                        <label class="form-label">
                            الحالة
                        </label>

                        <select
                            class="form-input"
                            name="status"
                            id="attStatus"
                        >
                            <option value="present">
                                حاضر
                            </option>

                            <option value="absent">
                                غائب
                            </option>

                            <option value="leave">
                                إجازة
                            </option>

                            <option value="holiday">
                                عطلة
                            </option>

                            <option value="weekend">
                                راحة أسبوعية
                            </option>
                        </select>

                    </div>


                    {{-- Shift --}}
                    <div class="form-group">

                        <label class="form-label">
                            الوردية
                        </label>

                        <select
                            class="form-input"
                            name="work_shift_id"
                            id="attShift"
                        >

                            <option value="">
                                الوردية المعينة تلقائيًا
                            </option>

                            @foreach($shifts as $shift)

                                <option value="{{ $shift->id }}">
                                    {{ $shift->name }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    {{-- Check in --}}
                    <div class="form-group">

                        <label class="form-label">
                            وقت الحضور
                        </label>

                        <input
                            class="form-input"
                            type="datetime-local"
                            name="check_in_at"
                            id="attIn"
                        >

                    </div>


                    {{-- Check out --}}
                    <div class="form-group">

                        <label class="form-label">
                            وقت الانصراف
                        </label>

                        <input
                            class="form-input"
                            type="datetime-local"
                            name="check_out_at"
                            id="attOut"
                        >

                    </div>


                    {{-- Notes --}}
                    <div class="form-group att-full">

                        <label class="form-label">
                            ملاحظات
                        </label>

                        <textarea
                            class="form-input"
                            name="notes"
                            id="attNotes"
                            rows="3"
                            placeholder="أي ملاحظات متعلقة بالدوام..."
                        ></textarea>

                    </div>


                    <div class="att-dialog-info att-full">

                        <span>
                            ⓘ
                        </span>

                        <span>
                            يتم احتساب التأخير، الخروج المبكر
                            والساعات الإضافية وفق إعدادات الوردية
                            المرتبطة بالموظف.
                        </span>

                    </div>

                </div>

            </div>


            <div class="att-dialog-footer">

                <button
                    type="button"
                    class="btn btn-ghost"
                    data-att-close
                >
                    إلغاء
                </button>

                <button
                    class="btn btn-gold"
                    type="submit"
                >
                    حفظ السجل
                </button>

            </div>

        </form>

    </div>

</div>

@endcan


@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* =====================================================
       Attendance modal
    ====================================================== */

    const modal =
        document.getElementById('attModal');

    if (modal) {

        const form =
            document.getElementById('attForm');

        const employeeName =
            document.getElementById('attEmployeeName');

        const status =
            document.getElementById('attStatus');

        const shift =
            document.getElementById('attShift');

        const checkIn =
            document.getElementById('attIn');

        const checkOut =
            document.getElementById('attOut');

        const notes =
            document.getElementById('attNotes');


        const openModal = function (button) {

            form.action =
                button.dataset.action;

            employeeName.textContent =
                button.dataset.name || '—';

            status.value =
                button.dataset.status || 'present';

            shift.value =
                button.dataset.shift || '';

            checkIn.value =
                button.dataset.in || '';

            checkOut.value =
                button.dataset.out || '';

            notes.value =
                button.dataset.notes || '';

            modal.classList.add('open');

            modal.setAttribute(
                'aria-hidden',
                'false'
            );

            document.body.style.overflow =
                'hidden';
        };


        const closeModal = function () {

            modal.classList.remove('open');

            modal.setAttribute(
                'aria-hidden',
                'true'
            );

            document.body.style.overflow =
                '';
        };


        document
            .querySelectorAll('.js-att-open')
            .forEach(function (button) {

                button.addEventListener(
                    'click',
                    function () {
                        openModal(button);
                    }
                );

            });


        modal
            .querySelectorAll('[data-att-close]')
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
    }


    /* =====================================================
       Employee search
    ====================================================== */

    const search =
        document.getElementById(
            'attendanceSearch'
        );

    const rows =
        Array.from(
            document.querySelectorAll(
                '.attendance-row'
            )
        );

    const noResults =
        document.getElementById(
            'attendanceNoResults'
        );


    if (search) {

        search.addEventListener(
            'input',
            function () {

                const value =
                    search.value
                        .trim()
                        .toLocaleLowerCase('ar');


                let visibleCount = 0;


                rows.forEach(function (row) {

                    const searchable =
                        (
                            row.dataset.search
                            || ''
                        ).toLocaleLowerCase('ar');


                    const visible =
                        !value
                        || searchable.includes(value);


                    row.style.display =
                        visible
                            ? ''
                            : 'none';


                    if (visible) {
                        visibleCount++;
                    }

                });


                if (noResults) {

                    noResults.style.display =
                        visibleCount === 0
                            ? 'block'
                            : 'none';

                }

            }
        );
    }

});
</script>

@endpush

@endsection