@extends('layouts.app')

@section('title', 'مراجعة دوام الموظفين')
@section('page-title', 'طلبات تسجيل الدوام')

@section('content')
<div class="self-review">
    <div class="page-actions">
        <div>
            <h1 class="page-heading">طلبات تسجيل الدوام</h1>
            <p class="page-subheading">أوقات سجلها الموظفون من بوابتهم. راجع الطلب المكتمل قبل إضافته إلى سجل الحضور المعتمد.</p>
        </div>
        @can('attendance.view')<a class="btn btn-outline" href="{{ route('attendance.index') }}">سجل الحضور</a>@endcan
    </div>

    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <nav class="self-review-tabs" aria-label="حالة طلبات الدوام">
        @foreach(['pending' => 'بانتظار المراجعة', 'approved' => 'معتمدة', 'rejected' => 'مرفوضة'] as $key => $label)
            <a class="btn {{ $status === $key ? 'btn-gold' : 'btn-outline' }}" href="{{ route('attendance.self-requests.index', ['status' => $key]) }}" @if($status === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="self-review-list">
        @forelse($requests as $submission)
            <article class="card self-review-item">
                <div class="card-body">
                    <div class="self-review-heading">
                        <div>
                            <strong>{{ $submission->employee?->full_name ?? 'موظف محذوف' }}</strong>
                            <small>{{ $submission->employee?->employee_number }} · {{ $submission->location?->name ?? 'فرع غير متاح' }} · {{ $submission->work_date->format('Y-m-d') }}</small>
                        </div>
                        <span class="self-review-state">{{ match($submission->status) {'approved' => 'معتمد', 'rejected' => 'مرفوض', default => ($submission->check_out_at ? 'جاهز للمراجعة' : 'على رأس العمل')} }}</span>
                    </div>
                    <div class="self-review-times">
                        <span>الحضور <strong>{{ $submission->check_in_at->format('Y-m-d H:i') }}</strong></span>
                        <span>الانصراف <strong>{{ $submission->check_out_at?->format('Y-m-d H:i') ?? 'لم يسجل بعد' }}</strong></span>
                    </div>
                    @if($submission->decision_note)<p class="self-review-note">ملاحظة المراجع: {{ $submission->decision_note }}</p>@endif
                    @if($submission->reviewed_at)<small>راجع الطلب: {{ $submission->reviewer?->username ?? 'مستخدم سابق' }} · {{ $submission->reviewed_at->format('Y-m-d H:i') }}</small>@endif

                    @if($submission->status === 'pending')
                        <div class="self-review-actions">
                            @if($submission->check_out_at)
                                <form method="POST" action="{{ route('attendance.self-requests.approve', $submission) }}" onsubmit="return confirm('هل راجعت أوقات الموظف وتريد اعتماد هذا الدوام؟')">
                                    @csrf
                                    <button class="btn btn-gold" type="submit">اعتماد الدوام</button>
                                </form>
                            @else
                                <small>الاعتماد متاح بعد تسجيل الانصراف.</small>
                            @endif
                            <form class="self-review-reject" method="POST" action="{{ route('attendance.self-requests.reject', $submission) }}">
                                @csrf
                                <label class="form-label" for="reject-{{ $submission->id }}">سبب الرفض</label>
                                <input class="form-input" id="reject-{{ $submission->id }}" name="decision_note" maxlength="1000" required placeholder="سبب واضح يظهر للموظف">
                                <button class="btn btn-outline" type="submit">رفض الطلب</button>
                            </form>
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <div class="card card-body">لا توجد طلبات بهذه الحالة ضمن فروعك.</div>
        @endforelse
    </div>
    <div style="margin-top:1rem">{{ $requests->links() }}</div>
</div>
<style>
.self-review{max-width:1150px;margin:auto}.self-review-tabs{display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0}.self-review-list{display:grid;gap:.8rem}.self-review-item .card-body{display:grid;gap:.75rem}.self-review-heading{display:flex;justify-content:space-between;gap:.75rem;align-items:start}.self-review-heading strong{display:block}.self-review-heading small{display:block;color:var(--text-muted);margin-top:.25rem}.self-review-state{padding:.35rem .65rem;background:var(--off-white);border-radius:99px;white-space:nowrap}.self-review-times{display:flex;gap:1.5rem;flex-wrap:wrap}.self-review-times span{display:grid;gap:.25rem;color:var(--text-muted)}.self-review-times strong{color:var(--text)}.self-review-note{margin:0}.self-review-actions{display:flex;gap:.8rem;align-items:end;flex-wrap:wrap;border-top:1px solid var(--border);padding-top:.8rem}.self-review-reject{display:flex;gap:.5rem;align-items:end;flex:1;flex-wrap:wrap}.self-review-reject label{width:100%}.self-review-reject input{flex:1;min-width:210px}@media(max-width:600px){.self-review-heading{flex-direction:column}}
</style>
@endsection
