@extends('layouts.app')

@section('title', 'الورديات')

@section('content')

<style>
.shift-page{
    max-width:1380px;
    margin:0 auto;
}

/* =========================================================
   Header
========================================================= */
.shift-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
    margin-bottom:1rem;
}

.shift-head-actions{
    display:flex;
    align-items:center;
    gap:.55rem;
    flex-wrap:wrap;
}

/* =========================================================
   Stats
========================================================= */
.shift-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:.8rem;
    margin-bottom:1rem;
}

.shift-stat{
    position:relative;
    overflow:hidden;
    min-height:105px;
    padding:1rem 1.05rem;
    border:1px solid var(--border);
    border-radius:17px;
    background:var(--surface);
}

.shift-stat::after{
    content:'';
    position:absolute;
    width:90px;
    height:90px;
    border-radius:999px;
    left:-32px;
    bottom:-38px;
    background:color-mix(
        in srgb,
        var(--border) 30%,
        transparent
    );
}

.shift-stat-head{
    position:relative;
    z-index:1;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:.7rem;
}

.shift-stat-label{
    color:var(--text-muted);
    font-size:.74rem;
    font-weight:800;
}

.shift-stat-icon{
    width:35px;
    height:35px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:color-mix(
        in srgb,
        var(--border) 40%,
        transparent
    );
}

.shift-stat strong{
    position:relative;
    z-index:1;
    display:block;
    margin-top:.55rem;
    font-size:1.5rem;
}

.shift-stat small{
    position:relative;
    z-index:1;
    display:block;
    margin-top:.35rem;
    color:var(--text-muted);
    font-size:.65rem;
}

/* =========================================================
   Shift cards
========================================================= */
.shift-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:.9rem;
}

.shift-card{
    position:relative;
    overflow:hidden;
    border:1px solid var(--border);
    border-radius:17px;
    background:var(--surface);
    padding:1rem;
}

.shift-card-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:.8rem;
}

.shift-code{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:56px;
    padding:.3rem .55rem;
    border-radius:9px;
    background:color-mix(
        in srgb,
        var(--theme-primary, #c7a44a) 12%,
        transparent
    );
    color:var(--theme-primary, #a9872f);
    font-size:.7rem;
    font-weight:900;
}

.shift-name{
    margin:.65rem 0 0;
    font-size:.95rem;
    font-weight:900;
}

.shift-status{
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    padding:.3rem .58rem;
    border-radius:999px;
    font-size:.66rem;
    font-weight:900;
}

.shift-status::before{
    content:'';
    width:6px;
    height:6px;
    border-radius:999px;
    background:currentColor;
}

.shift-active{
    color:var(--theme-success);
    background:color-mix(
        in srgb,
        var(--theme-success) 10%,
        transparent
    );
}

.shift-inactive{
    color:var(--text-muted);
    background:color-mix(
        in srgb,
        var(--border) 45%,
        transparent
    );
}

.shift-time{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.8rem;
    margin-top:1rem;
    padding:.8rem;
    border-radius:13px;
    background:color-mix(
        in srgb,
        var(--surface) 85%,
        var(--background)
    );
    border:1px solid var(--border);
}

.shift-time-point{
    display:flex;
    flex-direction:column;
    gap:.16rem;
}

.shift-time-point small{
    color:var(--text-muted);
    font-size:.62rem;
}

.shift-time-point strong{
    font-size:.9rem;
}

.shift-time-arrow{
    color:var(--text-muted);
    font-size:1rem;
}

.shift-details{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:.5rem;
    margin-top:.75rem;
}

.shift-detail{
    padding:.55rem;
    border:1px solid var(--border);
    border-radius:10px;
    text-align:center;
}

.shift-detail small{
    display:block;
    color:var(--text-muted);
    font-size:.58rem;
}

.shift-detail strong{
    display:block;
    margin-top:.2rem;
    font-size:.7rem;
}

.shift-location{
    margin-top:.75rem;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:.8rem;
    padding-top:.75rem;
    border-top:1px solid var(--border);
    font-size:.68rem;
}

.shift-location span:first-child{
    color:var(--text-muted);
}

/* =========================================================
   Table
========================================================= */
.shift-table-wrap{
    overflow:auto;
}

.shift-table{
    width:100%;
    border-collapse:collapse;
}

.shift-table th,
.shift-table td{
    padding:.86rem .95rem;
    border-bottom:1px solid var(--border);
    text-align:right;
    vertical-align:middle;
    white-space:nowrap;
}

.shift-table th{
    font-size:.68rem;
    font-weight:900;
    color:var(--text-muted);
    background:color-mix(
        in srgb,
        var(--surface) 90%,
        var(--background)
    );
}

.shift-table tbody tr{
    transition:background .15s ease;
}

.shift-table tbody tr:hover{
    background:color-mix(
        in srgb,
        var(--surface) 92%,
        var(--background)
    );
}

.shift-employee{
    display:flex;
    align-items:center;
    gap:.65rem;
}

.shift-avatar{
    width:38px;
    height:38px;
    flex:0 0 38px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:11px;
    background:color-mix(
        in srgb,
        var(--theme-primary, #c7a44a) 12%,
        var(--surface)
    );
    color:var(--theme-primary, #a9872f);
    font-weight:900;
}

.shift-employee strong{
    display:block;
    font-size:.78rem;
}

.shift-employee small{
    display:block;
    margin-top:.16rem;
    font-size:.63rem;
    color:var(--text-muted);
}

.shift-assignment-name{
    display:inline-flex;
    padding:.32rem .58rem;
    border-radius:999px;
    background:color-mix(
        in srgb,
        var(--theme-info) 8%,
        transparent
    );
    color:var(--theme-info);
    font-size:.69rem;
    font-weight:800;
}

/* =========================================================
   Card header
========================================================= */
.shift-card-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:1rem;
}

.shift-card-title-meta{
    display:flex;
    flex-direction:column;
    gap:.18rem;
}

.shift-card-subtitle{
    color:var(--text-muted);
    font-size:.66rem;
}

.shift-search{
    width:min(260px,100%);
}

/* =========================================================
   Empty
========================================================= */
.shift-empty{
    text-align:center !important;
    padding:2.8rem 1rem !important;
}

.shift-empty-icon{
    width:56px;
    height:56px;
    margin:0 auto .8rem;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:16px;
    background:color-mix(
        in srgb,
        var(--border) 40%,
        transparent
    );
    font-size:1.25rem;
}

.shift-empty strong{
    display:block;
}

.shift-empty span{
    display:block;
    margin-top:.3rem;
    color:var(--text-muted);
    font-size:.7rem;
}

/* =========================================================
   Modal
========================================================= */
.shift-modal{
    position:fixed;
    inset:0;
    z-index:9999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:1rem;
}

.shift-modal.open{
    display:flex;
}

.shift-backdrop{
    position:absolute;
    inset:0;
    background:rgba(15,23,42,.6);
    backdrop-filter:blur(4px);
}

.shift-dialog{
    position:relative;
    z-index:2;
    width:min(760px,100%);
    max-height:calc(100vh - 2rem);
    overflow:auto;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:20px;
    box-shadow:0 25px 80px rgba(0,0,0,.25);
}

.shift-dialog-sm{
    width:min(590px,100%);
}

.shift-dialog-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
    padding:1.1rem 1.2rem;
    border-bottom:1px solid var(--border);
}

.shift-dialog-head h3{
    margin:0;
    font-size:1rem;
}

.shift-dialog-head p{
    margin:.3rem 0 0;
    color:var(--text-muted);
    font-size:.68rem;
}

.shift-dialog-body{
    padding:1.2rem;
}

.shift-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:.9rem;
}

.shift-full{
    grid-column:1/-1;
}

.shift-dialog-footer{
    display:flex;
    justify-content:flex-end;
    align-items:center;
    gap:.6rem;
    padding:1rem 1.2rem;
    border-top:1px solid var(--border);
}

/* Work days */
.shift-days{
    display:grid;
    grid-template-columns:repeat(7,1fr);
    gap:.45rem;
}

.shift-day{
    cursor:pointer;
}

.shift-day input{
    display:none;
}

.shift-day span{
    min-height:42px;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:.4rem;
    border-radius:10px;
    border:1px solid var(--border);
    background:var(--surface);
    color:var(--text-muted);
    font-size:.65rem;
    font-weight:800;
    transition:.15s ease;
}

.shift-day input:checked + span{
    color:var(--theme-primary, #a9872f);
    border-color:color-mix(
        in srgb,
        var(--theme-primary, #c7a44a) 45%,
        var(--border)
    );
    background:color-mix(
        in srgb,
        var(--theme-primary, #c7a44a) 11%,
        transparent
    );
}

/* Info */
.shift-info{
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
    color:var(--text-muted);
    font-size:.68rem;
    line-height:1.7;
}

/* Search no results */
.shift-no-results{
    display:none;
    padding:2rem;
    text-align:center;
    color:var(--text-muted);
}

/* =========================================================
   Responsive
========================================================= */
@media(max-width:1050px){
    .shift-grid{
        grid-template-columns:repeat(2,1fr);
    }

    .shift-stats{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:760px){
    .shift-head{
        flex-direction:column;
        align-items:stretch;
    }

    .shift-head-actions{
        width:100%;
    }

    .shift-head-actions .btn{
        flex:1;
        justify-content:center;
    }

    .shift-grid{
        grid-template-columns:1fr;
    }

    .shift-form-grid{
        grid-template-columns:1fr;
    }

    .shift-full{
        grid-column:auto;
    }

    .shift-days{
        grid-template-columns:repeat(4,1fr);
    }

    .shift-card-header{
        align-items:stretch;
        flex-direction:column;
    }

    .shift-search{
        width:100%;
    }
}

@media(max-width:480px){
    .shift-stats{
        grid-template-columns:1fr 1fr;
    }

    .shift-days{
        grid-template-columns:repeat(2,1fr);
    }
}
</style>
@php
    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    | $shifts / $employees / $assignments are already location-scoped
    | inside WorkShiftController.
    */
    $activeShifts = $shifts
        ->where('is_active', true)
        ->count();

    $inactiveShifts = $shifts
        ->where('is_active', false)
        ->count();

    $activeAssignments = $assignments
        ->filter(
            fn ($assignment) =>
                ! $assignment->effective_to
                || $assignment->effective_to->isToday()
                || $assignment->effective_to->isFuture()
        )
        ->count();

    $assignedEmployeeIds = $assignments
        ->pluck('employee_id')
        ->unique()
        ->filter();

    $assignedEmployeesCount =
        $assignedEmployeeIds->count();

    $unassignedEmployeesCount = max(
        0,
        $employees->count() - $assignedEmployeesCount
    );

    /*
    |--------------------------------------------------------------------------
    | Current visible scope
    |--------------------------------------------------------------------------
    */
    $scopeLabel = $canViewAllLocations
        ? 'كل الفروع'
        : ($currentLocation?->name ?? 'الفرع الحالي');
@endphp


<div class="shift-page">

    {{-- =====================================================
         Header
    ====================================================== --}}
    <div class="shift-head">

        <div>

            <h1 class="page-heading">
                الورديات
            </h1>

            <p class="page-subheading">
                إدارة ساعات العمل، فترات السماح،
                الاستراحات وربط الموظفين بالورديات التشغيلية.
            </p>

            <div
                style="
                    display:inline-flex;
                    align-items:center;
                    gap:.4rem;
                    margin-top:.55rem;
                    padding:.3rem .6rem;
                    border-radius:999px;
                    border:1px solid var(--border);
                    background:var(--surface);
                    color:var(--text-muted);
                    font-size:.67rem;
                    font-weight:800;
                "
            >
                <span>نطاق العرض:</span>

                <strong style="color:var(--text)">
                    {{ $scopeLabel }}
                </strong>
            </div>

        </div>


        <div class="shift-head-actions">

            <a
                class="btn btn-outline"
                href="{{ route('attendance.index') }}"
            >
                العودة للحضور
            </a>


            @can('attendance.shifts.manage')

                <button
                    type="button"
                    class="btn btn-outline"
                    id="openAssignmentModal"
                >
                    ربط موظف
                </button>


                <button
                    type="button"
                    class="btn btn-gold"
                    id="openShiftModal"
                >
                    + وردية جديدة
                </button>

            @endcan

        </div>

    </div>


    {{-- =====================================================
         Stats
    ====================================================== --}}
    <div class="shift-stats">

        <div class="shift-stat">

            <div class="shift-stat-head">

                <span class="shift-stat-label">
                    إجمالي الورديات
                </span>

                <span class="shift-stat-icon">
                    ◷
                </span>

            </div>

            <strong>
                {{ number_format($shifts->count()) }}
            </strong>

            <small>
                ضمن {{ $scopeLabel }}
            </small>

        </div>


        <div class="shift-stat">

            <div class="shift-stat-head">

                <span class="shift-stat-label">
                    ورديات فعالة
                </span>

                <span class="shift-stat-icon">
                    ✓
                </span>

            </div>

            <strong>
                {{ number_format($activeShifts) }}
            </strong>

            <small>
                {{ $inactiveShifts }} وردية موقوفة
            </small>

        </div>


        <div class="shift-stat">

            <div class="shift-stat-head">

                <span class="shift-stat-label">
                    موظفون مربوطون
                </span>

                <span class="shift-stat-icon">
                    ◉
                </span>

            </div>

            <strong>
                {{ number_format($assignedEmployeesCount) }}
            </strong>

            <small>
                من أصل {{ $employees->count() }} موظف
            </small>

        </div>


        <div class="shift-stat">

            <div class="shift-stat-head">

                <span class="shift-stat-label">
                    بدون وردية
                </span>

                <span class="shift-stat-icon">
                    !
                </span>

            </div>

            <strong>
                {{ number_format($unassignedEmployeesCount) }}
            </strong>

            <small>
                يحتاجون تعيين وردية
            </small>

        </div>

    </div>


    {{-- =====================================================
         Current shifts
    ====================================================== --}}
    <div class="card">

        <div class="card-header shift-card-header">

            <div class="shift-card-title-meta">

                <span class="card-title">
                    الورديات الحالية
                </span>

                <span class="shift-card-subtitle">
                    @if($canViewAllLocations)
                        جميع الورديات التابعة لكافة الفروع
                    @else
                        ورديات {{ $currentLocation?->name }}
                        فقط
                    @endif
                </span>

            </div>

        </div>


        <div class="card-body">

            @if($shifts->isNotEmpty())

                <div class="shift-grid">

                    @foreach($shifts as $shift)

                        @php
                            $locationName =
                                $shift->location?->name
                                ?? 'كل المواقع';
                        @endphp


                        <div class="shift-card">

                            <div class="shift-card-top">

                                <div>

                                    <span class="shift-code">
                                        {{ $shift->code }}
                                    </span>

                                    <div class="shift-name">
                                        {{ $shift->name }}
                                    </div>

                                </div>


                                @if($shift->is_active)

                                    <span class="shift-status shift-active">
                                        فعال
                                    </span>

                                @else

                                    <span class="shift-status shift-inactive">
                                        موقوف
                                    </span>

                                @endif

                            </div>


                            <div class="shift-time">

                                <div class="shift-time-point">

                                    <small>
                                        بداية الوردية
                                    </small>

                                    <strong>
                                        {{ $shift->start_time }}
                                    </strong>

                                </div>


                                <span class="shift-time-arrow">
                                    ←
                                </span>


                                <div class="shift-time-point">

                                    <small>
                                        نهاية الوردية
                                    </small>

                                    <strong>
                                        {{ $shift->end_time }}
                                    </strong>

                                </div>

                            </div>


                            <div class="shift-details">

                                <div class="shift-detail">

                                    <small>
                                        سماح التأخير
                                    </small>

                                    <strong>
                                        {{ $shift->grace_minutes }} د
                                    </strong>

                                </div>


                                <div class="shift-detail">

                                    <small>
                                        الاستراحة
                                    </small>

                                    <strong>
                                        {{ $shift->break_minutes }} د
                                    </strong>

                                </div>


                                <div class="shift-detail">

                                    <small>
                                        بدء الإضافي
                                    </small>

                                    <strong>
                                        {{ $shift->overtime_after_minutes ?? 0 }} د
                                    </strong>

                                </div>

                            </div>


                            <div class="shift-location">

                                <span>
                                    الفرع
                                </span>

                                <strong>
                                    {{ $locationName }}
                                </strong>

                            </div>

                        </div>

                    @endforeach

                </div>

            @else

                <div class="shift-empty">

                    <div class="shift-empty-icon">
                        ◷
                    </div>

                    <strong>
                        لا توجد ورديات
                    </strong>

                    <span>
                        @if($canViewAllLocations)
                            لم يتم إنشاء ورديات حتى الآن.
                        @else
                            لا توجد ورديات مرتبطة بفرع
                            {{ $currentLocation?->name }}.
                        @endif
                    </span>

                </div>

            @endif

        </div>

    </div>


    {{-- =====================================================
         Employee assignments
    ====================================================== --}}
    <div
        class="card"
        style="margin-top:1rem"
    >

        <div class="card-header shift-card-header">

            <div class="shift-card-title-meta">

                <span class="card-title">
                    ربط الموظفين الحالي
                </span>

                <span class="shift-card-subtitle">

                    {{ $activeAssignments }}
                    تعيين فعال حاليًا

                    @unless($canViewAllLocations)
                        · {{ $currentLocation?->name }}
                    @endunless

                </span>

            </div>


            <input
                type="search"
                class="form-input shift-search"
                id="shiftAssignmentSearch"
                placeholder="بحث باسم الموظف أو الفرع..."
                autocomplete="off"
            >

        </div>


        <div
            class="card-body shift-table-wrap"
            style="padding:0"
        >

            <table
                class="shift-table"
                id="shiftAssignmentTable"
            >

                <thead>

                    <tr>
                        <th>الموظف</th>
                        <th>الفرع</th>
                        <th>الوردية</th>
                        <th>ساري من</th>
                        <th>ساري إلى</th>
                        <th>الحالة</th>
                    </tr>

                </thead>


                <tbody>

                    @forelse($assignments as $assignment)

                        @php
                            $employeeName =
                                $assignment->employee?->full_name
                                ?: 'موظف غير معروف';

                            $initial = mb_substr(
                                trim($employeeName),
                                0,
                                1
                            );

                            $assignmentActive =
                                ! $assignment->effective_to
                                || $assignment->effective_to->isToday()
                                || $assignment->effective_to->isFuture();

                            $employeePrimaryLocation =
                                $assignment
                                    ->employee
                                    ?->employeeLocations
                                    ?->firstWhere(
                                        'is_primary',
                                        true
                                    )
                                    ?->location;

                            if (! $employeePrimaryLocation) {
                                $employeePrimaryLocation =
                                    $assignment
                                        ->employee
                                        ?->employeeLocations
                                        ?->first()
                                        ?->location;
                            }

                            $employeeLocationName =
                                $employeePrimaryLocation?->name
                                ?? $assignment->shift?->location?->name
                                ?? 'بدون فرع';
                        @endphp


                        <tr
                            class="shift-assignment-row"

                            data-search="{{ mb_strtolower(
                                $employeeName
                                .' '
                                .($assignment->employee?->job_title ?? '')
                                .' '
                                .($assignment->shift?->name ?? '')
                                .' '
                                .$employeeLocationName
                            ) }}"
                        >

                            {{-- Employee --}}
                            <td>

                                <div class="shift-employee">

                                    <div class="shift-avatar">
                                        {{ $initial }}
                                    </div>


                                    <div>

                                        <strong>
                                            {{ $employeeName }}
                                        </strong>

                                        <small>
                                            {{ $assignment->employee?->job_title ?: 'موظف' }}
                                        </small>

                                    </div>

                                </div>

                            </td>


                            {{-- Location --}}
                            <td>

                                <span
                                    style="
                                        display:inline-flex;
                                        padding:.32rem .58rem;
                                        border-radius:999px;
                                        background:color-mix(
                                            in srgb,
                                            var(--border) 42%,
                                            transparent
                                        );
                                        font-size:.67rem;
                                        font-weight:800;
                                    "
                                >
                                    {{ $employeeLocationName }}
                                </span>

                            </td>


                            {{-- Shift --}}
                            <td>

                                <span class="shift-assignment-name">
                                    {{ $assignment->shift?->name ?: '—' }}
                                </span>

                            </td>


                            {{-- From --}}
                            <td>
                                {{ $assignment->effective_from?->format('Y-m-d') ?? '—' }}
                            </td>


                            {{-- To --}}
                            <td>

                                @if($assignment->effective_to)

                                    {{ $assignment->effective_to->format('Y-m-d') }}

                                @else

                                    <span
                                        style="color:var(--text-muted)"
                                    >
                                        مستمرة
                                    </span>

                                @endif

                            </td>


                            {{-- Status --}}
                            <td>

                                @if($assignmentActive)

                                    <span class="shift-status shift-active">
                                        فعال
                                    </span>

                                @else

                                    <span class="shift-status shift-inactive">
                                        منتهي
                                    </span>

                                @endif

                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="6"
                                class="shift-empty"
                            >

                                <div class="shift-empty-icon">
                                    ◉
                                </div>

                                <strong>
                                    لا توجد تعيينات
                                </strong>

                                <span>
                                    لم يتم ربط أي موظف بورديّة حتى الآن.
                                </span>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>


            <div
                class="shift-no-results"
                id="shiftNoResults"
            >
                لا توجد نتائج مطابقة للبحث.
            </div>

        </div>

    </div>

</div>


{{-- =========================================================
     Create shift modal
========================================================= --}}
@can('attendance.shifts.manage')

<div
    class="shift-modal"
    id="shiftModal"
    aria-hidden="true"
>

    <div
        class="shift-backdrop"
        data-shift-close
    ></div>


    <div
        class="shift-dialog"
        role="dialog"
        aria-modal="true"
    >

        <div class="shift-dialog-head">

            <div>

                <h3>
                    إنشاء وردية جديدة
                </h3>

                <p>
                    @if($canViewAllLocations)
                        حدد ساعات العمل والفرع وإعدادات الوردية.
                    @else
                        سيتم إنشاء الوردية لفرع
                        {{ $currentLocation?->name }}
                        فقط.
                    @endif
                </p>

            </div>


            <button
                type="button"
                class="btn btn-ghost"
                data-shift-close
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            action="{{ route('attendance.shifts.store') }}"
        >

            @csrf


            <div class="shift-dialog-body">

                <div class="shift-form-grid">

                    {{-- Code --}}
                    <div class="form-group">

                        <label class="form-label">
                            كود الوردية
                        </label>

                        <input
                            class="form-input"
                            name="code"
                            value="{{ old('code') }}"
                            placeholder="مثال: MORNING"
                            required
                        >

                    </div>


                    {{-- Name --}}
                    <div class="form-group">

                        <label class="form-label">
                            اسم الوردية
                        </label>

                        <input
                            class="form-input"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="مثال: الوردية الصباحية"
                            required
                        >

                    </div>


                    {{-- Start --}}
                    <div class="form-group">

                        <label class="form-label">
                            بداية الوردية
                        </label>

                        <input
                            class="form-input"
                            type="time"
                            name="start_time"
                            value="{{ old('start_time') }}"
                            required
                        >

                    </div>


                    {{-- End --}}
                    <div class="form-group">

                        <label class="form-label">
                            نهاية الوردية
                        </label>

                        <input
                            class="form-input"
                            type="time"
                            name="end_time"
                            value="{{ old('end_time') }}"
                            required
                        >

                    </div>


                    {{-- Break --}}
                    <div class="form-group">

                        <label class="form-label">
                            مدة الاستراحة / دقيقة
                        </label>

                        <input
                            class="form-input"
                            type="number"
                            min="0"
                            name="break_minutes"
                            value="{{ old('break_minutes', 0) }}"
                            required
                        >

                    </div>


                    {{-- Grace --}}
                    <div class="form-group">

                        <label class="form-label">
                            سماح التأخير / دقيقة
                        </label>

                        <input
                            class="form-input"
                            type="number"
                            min="0"
                            name="grace_minutes"
                            value="{{ old('grace_minutes', 10) }}"
                            required
                        >

                    </div>


                    {{-- Overtime --}}
                    <div class="form-group">

                        <label class="form-label">
                            يبدأ الإضافي بعد / دقيقة
                        </label>

                        <input
                            class="form-input"
                            type="number"
                            min="0"
                            name="overtime_after_minutes"
                            value="{{ old(
                                'overtime_after_minutes',
                                0
                            ) }}"
                            required
                        >

                    </div>


                    {{-- Location --}}
                    <div class="form-group">

                        <label class="form-label">
                            الفرع
                        </label>


                        @if($canViewAllLocations)

                            <select
                                class="form-input"
                                name="location_id"
                            >

                                <option value="">
                                    كل المواقع
                                </option>


                                @foreach($locations as $location)

                                    <option
                                        value="{{ $location->id }}"

                                        @selected(
                                            old('location_id')
                                            == $location->id
                                        )
                                    >
                                        {{ $location->name }}
                                    </option>

                                @endforeach

                            </select>


                        @else

                            <input
                                type="hidden"
                                name="location_id"
                                value="{{ $currentLocation?->id }}"
                            >

                            <input
                                type="text"
                                class="form-input"
                                value="{{ $currentLocation?->name }}"
                                readonly
                            >

                        @endif

                    </div>


                    {{-- Work days --}}
                    <div class="form-group shift-full">

                        <label class="form-label">
                            أيام العمل
                        </label>


                        <div class="shift-days">

                            @foreach([
                                1 => 'الإثنين',
                                2 => 'الثلاثاء',
                                3 => 'الأربعاء',
                                4 => 'الخميس',
                                5 => 'الجمعة',
                                6 => 'السبت',
                                7 => 'الأحد'
                            ] as $day => $label)

                                <label class="shift-day">

                                    <input
                                        type="checkbox"
                                        name="work_days[]"
                                        value="{{ $day }}"

                                        @checked(
                                            in_array(
                                                $day,
                                                old(
                                                    'work_days',
                                                    [1,2,3,4,5,6]
                                                )
                                            )
                                        )
                                    >

                                    <span>
                                        {{ $label }}
                                    </span>

                                </label>

                            @endforeach

                        </div>

                    </div>


                    <div class="shift-info shift-full">

                        <span>
                            ⓘ
                        </span>

                        <span>
                            @if($canViewAllLocations)

                                الورديات المرتبطة بفرع محدد
                                ستظهر فقط لذلك الفرع، بينما
                                الـAdmin يستطيع مشاهدة جميع الورديات.

                            @else

                                هذه الوردية ستُربط تلقائيًا بفرع
                                <strong>
                                    {{ $currentLocation?->name }}
                                </strong>
                                ولن يستطيع مستخدم فرع آخر رؤيتها
                                أو استخدامها.

                            @endif
                        </span>

                    </div>

                </div>

            </div>


            <div class="shift-dialog-footer">

                <button
                    type="button"
                    class="btn btn-ghost"
                    data-shift-close
                >
                    إلغاء
                </button>


                <button
                    class="btn btn-gold"
                    type="submit"
                >
                    إنشاء الوردية
                </button>

            </div>

        </form>

    </div>

</div>


{{-- =========================================================
     Employee assignment modal
========================================================= --}}
<div
    class="shift-modal"
    id="assignmentModal"
    aria-hidden="true"
>

    <div
        class="shift-backdrop"
        data-assignment-close
    ></div>


    <div
        class="shift-dialog shift-dialog-sm"
        role="dialog"
        aria-modal="true"
    >

        <div class="shift-dialog-head">

            <div>

                <h3>
                    ربط موظف بالوردية
                </h3>

                <p>
                    @if($canViewAllLocations)

                        اختر الموظف والوردية المناسبة
                        حسب الفرع.

                    @else

                        يظهر هنا فقط موظفو وورديات
                        فرع {{ $currentLocation?->name }}.

                    @endif
                </p>

            </div>


            <button
                type="button"
                class="btn btn-ghost"
                data-assignment-close
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            action="{{ route('attendance.shifts.assign') }}"
        >

            @csrf


            <div class="shift-dialog-body">

                <div class="shift-form-grid">

                    {{-- Employee --}}
                    <div class="form-group shift-full">

                        <label class="form-label">
                            الموظف
                        </label>


                        <select
                            class="form-input"
                            name="employee_id"
                            required
                        >

                            <option value="">
                                اختر الموظف
                            </option>


                            @foreach($employees as $employee)

                                @php
                                    $employeeLocation =
                                        $employee
                                            ->employeeLocations
                                            ->first()
                                            ?->location;

                                    $employeeLocationName =
                                        $employeeLocation?->name
                                        ?? 'بدون فرع';
                                @endphp


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

                                    — {{ $employeeLocationName }}

                                </option>

                            @endforeach

                        </select>

                    </div>


                    {{-- Shift --}}
                    <div class="form-group shift-full">

                        <label class="form-label">
                            الوردية
                        </label>


                        <select
                            class="form-input"
                            name="work_shift_id"
                            required
                        >

                            <option value="">
                                اختر الوردية
                            </option>


                            @foreach($shifts as $shift)

                                <option
                                    value="{{ $shift->id }}"

                                    @selected(
                                        old('work_shift_id')
                                        == $shift->id
                                    )
                                >

                                    {{ $shift->name }}

                                    — {{ $shift->start_time }}
                                    / {{ $shift->end_time }}

                                    @if($canViewAllLocations)
                                        — {{ $shift->location?->name ?? 'كل المواقع' }}
                                    @endif

                                </option>

                            @endforeach

                        </select>

                    </div>


                    {{-- Effective from --}}
                    <div class="form-group shift-full">

                        <label class="form-label">
                            ساري من
                        </label>

                        <input
                            class="form-input"
                            type="date"
                            name="effective_from"
                            value="{{ old(
                                'effective_from',
                                now()->toDateString()
                            ) }}"
                            required
                        >

                    </div>


                    <div class="shift-info shift-full">

                        <span>
                            ⓘ
                        </span>

                        <span>
                            يتم التحقق من الفرع مرة أخرى
                            في الـBackend قبل حفظ الربط،
                            لذلك لا يمكن ربط موظف بورديّة
                            تابعة لفرع مختلف.
                        </span>

                    </div>

                </div>

            </div>


            <div class="shift-dialog-footer">

                <button
                    type="button"
                    class="btn btn-ghost"
                    data-assignment-close
                >
                    إلغاء
                </button>


                <button
                    class="btn btn-gold"
                    type="submit"
                >
                    حفظ الربط
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
       Create shift modal
    ====================================================== */

    const shiftModal =
        document.getElementById('shiftModal');

    const openShiftButton =
        document.getElementById('openShiftModal');


    const openShiftModal = function () {

        if (!shiftModal) return;

        shiftModal.classList.add('open');

        shiftModal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.style.overflow =
            'hidden';
    };


    const closeShiftModal = function () {

        if (!shiftModal) return;

        shiftModal.classList.remove('open');

        shiftModal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.style.overflow =
            '';
    };


    if (openShiftButton) {

        openShiftButton.addEventListener(
            'click',
            openShiftModal
        );

    }


    if (shiftModal) {

        shiftModal
            .querySelectorAll('[data-shift-close]')
            .forEach(function (button) {

                button.addEventListener(
                    'click',
                    closeShiftModal
                );

            });

    }


    /* =====================================================
       Assignment modal
    ====================================================== */

    const assignmentModal =
        document.getElementById(
            'assignmentModal'
        );

    const openAssignmentButton =
        document.getElementById(
            'openAssignmentModal'
        );


    const openAssignmentModal = function () {

        if (!assignmentModal) return;

        assignmentModal.classList.add('open');

        assignmentModal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.style.overflow =
            'hidden';
    };


    const closeAssignmentModal = function () {

        if (!assignmentModal) return;

        assignmentModal.classList.remove('open');

        assignmentModal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.style.overflow =
            '';
    };


    if (openAssignmentButton) {

        openAssignmentButton.addEventListener(
            'click',
            openAssignmentModal
        );

    }


    if (assignmentModal) {

        assignmentModal
            .querySelectorAll(
                '[data-assignment-close]'
            )
            .forEach(function (button) {

                button.addEventListener(
                    'click',
                    closeAssignmentModal
                );

            });

    }


    /* =====================================================
       Escape
    ====================================================== */

    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key !== 'Escape') {
                return;
            }

            closeShiftModal();
            closeAssignmentModal();

        }
    );


    /* =====================================================
       Assignment search
    ====================================================== */

    const search =
        document.getElementById(
            'shiftAssignmentSearch'
        );

    const rows =
        Array.from(
            document.querySelectorAll(
                '.shift-assignment-row'
            )
        );

    const noResults =
        document.getElementById(
            'shiftNoResults'
        );


    if (search) {

        search.addEventListener(
            'input',
            function () {

                const value =
                    search.value
                        .trim()
                        .toLocaleLowerCase('ar');


                let visible = 0;


                rows.forEach(function (row) {

                    const searchable =
                        (
                            row.dataset.search
                            || ''
                        ).toLocaleLowerCase('ar');


                    const match =
                        !value
                        || searchable.includes(value);


                    row.style.display =
                        match
                            ? ''
                            : 'none';


                    if (match) {
                        visible++;
                    }

                });


                if (noResults) {

                    noResults.style.display =
                        visible === 0
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