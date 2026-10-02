@extends('layouts.app')

@section('title', 'الموارد البشرية')
@section('page-title', 'الموارد البشرية')

@section('content')
<div style="max-width:1260px;margin:auto">
    <div class="page-actions">
        <div><h1 class="page-heading">متابعة الموارد البشرية</h1><p class="page-subheading">أرقام ومهام الموظفين ضمن نطاق الفروع المسموح لك بعرضها.</p></div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            @can('employees.manage')<a class="btn btn-outline" href="{{ route('employees.index') }}">الموظفون</a>@endcan
            @can('hr.organization.view')<a class="btn btn-outline" href="{{ route('hr.organization.index') }}">الهيكل الوظيفي</a>@endcan
            @can('attendance.approve')<a class="btn btn-outline" href="{{ route('attendance.self-requests.index') }}">مراجعة تسجيل الدوام</a>@endcan
        </div>
    </div>
    @if($errors->any())
        <div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif
    <form class="card card-body" method="GET" action="{{ route('hr.report.csv') }}" style="display:flex;gap:.8rem;align-items:end;flex-wrap:wrap">
        <div><label class="form-label" for="hrReportFrom">تقرير الحضور من</label><input class="form-input" id="hrReportFrom" type="date" name="from" value="{{ now()->subDays(29)->toDateString() }}" required></div>
        <div><label class="form-label" for="hrReportTo">إلى</label><input class="form-input" id="hrReportTo" type="date" name="to" value="{{ now()->toDateString() }}" required></div>
        <button class="btn btn-outline" type="submit">تنزيل تقرير CSV</button>
    </form>
    <div class="hr-summary">
        <div class="card card-body"><small>الموظفون</small><strong>{{ $employeeCount }}</strong></div>
        <div class="card card-body"><small>إجازات معلقة</small><strong>{{ $leaveCount }}</strong></div>
        <div class="card card-body"><small>تصحيحات حضور معلقة</small><strong>{{ $correctionCount }}</strong></div>
        <div class="card card-body"><small>غياب مسجل اليوم</small><strong>{{ $absenceCount }}</strong></div>
        <div class="card card-body"><small>على رأس العمل الآن</small><strong>{{ $onDutyCount }}</strong></div>
        <div class="card card-body"><small>بدون سجل اليوم</small><strong>{{ $notRegisteredCount }}</strong></div>
        <div class="card card-body"><small>دوام ذاتي بانتظار المراجعة</small><strong>{{ $selfAttendanceCount }}</strong></div>
    </div>
    <div class="hr-panels">
        <section class="card">
            <div class="card-header"><span class="card-title">طلبات الإجازة</span>@can('attendance.leaves.view')<a href="{{ route('attendance.leaves.index', ['status' => 'pending']) }}">عرض الجميع</a>@endcan</div>
            <div class="card-body">
                @forelse($leaves as $leave)
                    <div class="hr-row"><span>{{ $leave->employee?->full_name }} · {{ $leave->start_date?->format('Y-m-d') }} — {{ $leave->end_date?->format('Y-m-d') }}</span><strong>{{ $leave->total_days }} يوم</strong></div>
                @empty <p>لا توجد طلبات معلقة.</p> @endforelse
            </div>
        </section>
        <section class="card">
            <div class="card-header"><span class="card-title">تصحيحات الحضور</span>@can('attendance.approve')<a href="{{ route('attendance.corrections.index') }}">عرض الجميع</a>@endcan</div>
            <div class="card-body">
                @forelse($corrections as $correction)
                    <div class="hr-row"><span>{{ $correction->employee?->full_name }}</span><strong>{{ $correction->work_date?->format('Y-m-d') }}</strong></div>
                @empty <p>لا توجد تصحيحات معلقة.</p> @endforelse
            </div>
        </section>
        <section class="card">
            <div class="card-header"><span class="card-title">تسجيلات الدوام الذاتية</span>@can('attendance.approve')<a href="{{ route('attendance.self-requests.index') }}">مراجعة الطلبات</a>@endcan</div>
            <div class="card-body">
                @forelse($selfAttendanceRequests as $submission)
                    <div class="hr-row"><span>{{ $submission->employee?->full_name }} · {{ $submission->work_date?->format('Y-m-d') }}</span><strong>{{ $submission->check_out_at ? 'جاهز للمراجعة' : 'على رأس العمل' }}</strong></div>
                @empty <p>لا توجد تسجيلات ذاتية معلقة.</p> @endforelse
            </div>
        </section>
        <section class="card">
            <div class="card-header"><span class="card-title">على رأس العمل الآن</span></div>
            <div class="card-body">
                @forelse($onDutyEmployees as $person)
                    <div class="hr-row"><span>{{ $person->full_name }}</span><small>{{ $person->job_title ?: 'موظف' }}</small></div>
                @empty <p>لا يوجد حضور مفتوح حاليًا.</p> @endforelse
            </div>
        </section>
        <section class="card">
            <div class="card-header"><span class="card-title">لا يوجد لهم سجل اليوم</span></div>
            <div class="card-body">
                <p class="page-subheading">هذا تنبيه متابعة؛ عدم وجود سجل وحده لا يعني الغياب.</p>
                @forelse($withoutTodayRecordEmployees as $person)
                    <div class="hr-row"><span>{{ $person->full_name }}</span><small>{{ $person->job_title ?: 'موظف' }}</small></div>
                @empty <p>كل الموظفين ضمن نطاقك لديهم سجل أو تسجيل مبدئي اليوم.</p> @endforelse
            </div>
        </section>
        @can('hr.documents.view')
            <section class="card">
                <div class="card-header"><span class="card-title">مستندات تنتهي خلال 30 يومًا أو انتهت</span></div>
                <div class="card-body">
                    @forelse($documents as $document)
                        <div class="hr-row"><a href="{{ route('hr.employees.file', $document->employee) }}">{{ $document->employee?->full_name }} · {{ $document->title }}</a><strong>{{ $document->expires_on?->format('Y-m-d') }}</strong></div>
                    @empty <p>لا توجد مستندات بحاجة متابعة.</p> @endforelse
                </div>
            </section>
            <section class="card">
                <div class="card-header"><span class="card-title">عقود تنتهي خلال 30 يومًا أو انتهت</span></div>
                <div class="card-body">
                    @forelse($contracts as $profile)
                        <div class="hr-row"><a href="{{ route('hr.employees.file', $profile->employee) }}">{{ $profile->employee?->full_name }}</a><strong>{{ $profile->contract_ends_on?->format('Y-m-d') }}</strong></div>
                    @empty <p>لا توجد عقود بحاجة متابعة.</p> @endforelse
                </div>
            </section>
        @endcan
    </div>
</div>
<style>
.hr-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:.8rem;margin:1rem 0}
.hr-summary .card{display:flex;flex-direction:column;gap:.3rem}.hr-summary strong{font-size:1.5rem}
.hr-panels{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
.hr-row{display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;padding:.65rem 0;border-bottom:1px solid var(--border)}
@media(max-width:850px){.hr-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.hr-panels{grid-template-columns:1fr}}
</style>
@endsection
