@extends('layouts.app')

@section('title', 'طلبات تصحيح الحضور')
@section('page-title', 'طلبات تصحيح الحضور')

@section('content')
<div style="max-width:1280px;margin:auto">
    <div class="page-actions">
        <div>
            <h1 class="page-heading">طلبات تصحيح الحضور</h1>
            <p class="page-subheading">راجع سبب الطلب والوقت المقترح قبل اعتماده. لا تُعدل السجلات عند رفض الطلب.</p>
        </div>
        @can('attendance.view')<a class="btn btn-outline" href="{{ route('attendance.index') }}">الحضور</a>@endcan
    </div>
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif
    <div class="hr-tabs">
        @foreach(['pending' => 'بانتظار المراجعة', 'approved' => 'معتمدة', 'rejected' => 'مرفوضة'] as $key => $label)
            <a class="btn {{ $status === $key ? 'btn-gold' : 'btn-outline' }}" href="{{ route('attendance.corrections.index', ['status' => $key]) }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="card">
        <div class="card-body" style="overflow-x:auto;padding:0">
            <table class="hr-corrections">
                <thead><tr><th>الموظف</th><th>التاريخ</th><th>الحضور المطلوب</th><th>الانصراف المطلوب</th><th>السبب</th><th>الحالة والإجراء</th></tr></thead>
                <tbody>
                @forelse($requests as $correction)
                    <tr>
                        <td><strong>{{ $correction->employee?->full_name }}</strong><br><small>{{ $correction->employee?->employee_number }}</small></td>
                        <td>{{ $correction->work_date?->format('Y-m-d') }}</td>
                        <td>{{ $correction->requested_check_in_at?->format('Y-m-d H:i') ?? 'دون تغيير' }}</td>
                        <td>{{ $correction->requested_check_out_at?->format('Y-m-d H:i') ?? 'دون تغيير' }}</td>
                        <td style="min-width:170px;white-space:normal">{{ $correction->reason }}</td>
                        <td>
                            @if($correction->status === 'pending')
                                @if($correction->requested_by === auth()->id())
                                    <span>لا يمكنك مراجعة طلبك</span>
                                @else
                                    <form action="{{ route('attendance.corrections.approve', $correction) }}" method="POST" class="hr-decision">
                                        @csrf
                                        <input class="form-input" name="decision_note" placeholder="ملاحظة للمراجعة" maxlength="1000">
                                        <button class="btn btn-gold btn-sm" type="submit">اعتماد</button>
                                    </form>
                                    <form action="{{ route('attendance.corrections.reject', $correction) }}" method="POST" class="hr-decision">
                                        @csrf
                                        <input class="form-input" name="decision_note" placeholder="سبب الرفض" maxlength="1000">
                                        <button class="btn btn-outline btn-sm" type="submit">رفض</button>
                                    </form>
                                @endif
                            @else
                                <span>{{ $correction->status === 'approved' ? 'تم الاعتماد' : 'تم الرفض' }}</span>
                                @if($correction->decision_note)<small>{{ $correction->decision_note }}</small>@endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">لا توجد طلبات بهذه الحالة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div style="margin-top:1rem">{{ $requests->links() }}</div>
</div>
<style>
.hr-tabs{display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0}
.hr-corrections{width:100%;border-collapse:collapse}
.hr-corrections th,.hr-corrections td{padding:.8rem;border-bottom:1px solid var(--border);text-align:right;vertical-align:top}
.hr-corrections th{background:var(--surface);color:var(--text-muted);font-size:.8rem}
.hr-corrections small{display:block;color:var(--text-muted);margin-top:.35rem}
.hr-decision{display:flex;gap:.35rem;align-items:center;margin-bottom:.4rem}
.hr-decision .form-input{min-width:120px;max-width:210px}
</style>
@endsection
