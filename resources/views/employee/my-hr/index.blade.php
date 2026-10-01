@extends('layouts.app')

@section('title', 'بوابة الموظف')
@section('page-title', 'ملفي الوظيفي')

@section('content')
<div class="myhr">
    <div class="page-actions">
        <div>
            <h1 class="page-heading">مرحبًا، {{ $employee->full_name }}</h1>
            <p class="page-subheading">ملفك الوظيفي وحضورك وطلباتك وكشوف الراتب المعتمدة.</p>
        </div>
        <div class="myhr-tag">{{ $employee->employee_number }} · {{ $shift?->name ?? 'لا توجد وردية محددة اليوم' }}</div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="myhr-grid">
        <section class="card">
            <div class="card-header"><span class="card-title">رصيد الإجازات لسنة {{ now()->year }}</span></div>
            <div class="card-body">
                @forelse($leaveTypes as $type)
                    <div class="myhr-row">
                        <span>{{ $type->name }}</span>
                        <strong>{{ $balances[$type->id]['available'] === null ? 'حسب سياسة الشركة' : number_format($balances[$type->id]['available'], 2).' يوم' }}</strong>
                        <small>معتمد: {{ $balances[$type->id]['approved'] }} · بانتظار الموافقة: {{ $balances[$type->id]['pending'] }}</small>
                    </div>
                @empty
                    <p>لم تُضف أنواع إجازات بعد.</p>
                @endforelse
            </div>
        </section>

        <section class="card">
            <div class="card-header"><span class="card-title">طلب إجازة</span></div>
            <form class="card-body myhr-form" action="{{ route('my-hr.leaves.store') }}" method="POST">
                @csrf
                <div><label class="form-label" for="myLeaveType">نوع الإجازة</label>
                    <select class="form-input" id="myLeaveType" name="leave_type_id" required>
                        <option value="">اختر النوع</option>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="myhr-dates">
                    <div><label class="form-label" for="myLeaveStart">من</label><input class="form-input" type="date" id="myLeaveStart" name="start_date" value="{{ old('start_date') }}" required></div>
                    <div><label class="form-label" for="myLeaveEnd">إلى</label><input class="form-input" type="date" id="myLeaveEnd" name="end_date" value="{{ old('end_date') }}" required></div>
                </div>
                <div><label class="form-label" for="myLeaveReason">السبب</label><textarea class="form-input" id="myLeaveReason" name="reason" rows="2" maxlength="1500">{{ old('reason') }}</textarea></div>
                <button class="btn btn-gold" type="submit" @disabled($leaveTypes->isEmpty())>إرسال الطلب</button>
            </form>
        </section>

        <section class="card">
            <div class="card-header"><span class="card-title">طلب تصحيح حضور</span></div>
            <form class="card-body myhr-form" action="{{ route('my-hr.corrections.store') }}" method="POST">
                @csrf
                <p class="page-subheading">أدخل الوقت الذي تريد تصحيحه وسبب الطلب. لن يتغير السجل إلا بعد موافقة المدير.</p>
                <div><label class="form-label" for="myWorkDate">يوم الدوام</label><input class="form-input" id="myWorkDate" name="work_date" type="date" max="{{ now()->toDateString() }}" value="{{ old('work_date') }}" required></div>
                <div class="myhr-dates">
                    <div><label class="form-label" for="myCheckIn">الحضور المطلوب</label><input class="form-input" id="myCheckIn" name="check_in_at" type="datetime-local" value="{{ old('check_in_at') }}"></div>
                    <div><label class="form-label" for="myCheckOut">الانصراف المطلوب</label><input class="form-input" id="myCheckOut" name="check_out_at" type="datetime-local" value="{{ old('check_out_at') }}"></div>
                </div>
                <div><label class="form-label" for="myCorrectionReason">سبب التصحيح</label><textarea class="form-input" id="myCorrectionReason" name="reason" rows="2" maxlength="1000" required>{{ old('reason') }}</textarea></div>
                <button class="btn btn-outline" type="submit">إرسال التصحيح للمراجعة</button>
            </form>
        </section>

        <section class="card">
            <div class="card-header"><span class="card-title">كشوف الرواتب المعتمدة</span></div>
            <div class="card-body">
                @forelse($payslips as $item)
                    <div class="myhr-row">
                        <span>{{ $item->period?->name }}</span>
                        <strong>{{ number_format((float) $item->net_salary, 2) }}</strong>
                        <a href="{{ route('my-hr.payslips.show', $item) }}" target="_blank" rel="noopener">عرض قسيمة الراتب</a>
                    </div>
                @empty
                    <p>لا توجد كشوف راتب معتمدة للعرض.</p>
                @endforelse
            </div>
        </section>
        <section class="card">
            <div class="card-header"><span class="card-title">سلفي وآخر أرصدتها</span></div>
            <div class="card-body">
                @forelse($advances as $advance)
                    <div class="myhr-row">
                        <span>{{ $advance->issued_at?->format('Y-m-d') ?? '—' }} · {{ $advance->status }}</span>
                        <strong>المتبقي: {{ number_format((float) $advance->outstanding_amount, 2) }}</strong>
                        <small>الأصل: {{ number_format((float) $advance->amount, 2) }} · المسترد: {{ number_format((float) $advance->recovered_amount, 2) }}</small>
                    </div>
                @empty
                    <p>لا توجد سلف مسجلة.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="myhr-grid">
        <section class="card">
            <div class="card-header"><span class="card-title">سجل الحضور الأخير</span></div>
            <div class="card-body myhr-list">
                @forelse($records as $record)
                    <div class="myhr-row">
                        <span>{{ $record->work_date?->format('Y-m-d') }}</span>
                        <strong>{{ match($record->status) {'present' => 'حاضر', 'absent' => 'غائب', 'leave' => 'إجازة', 'holiday' => 'عطلة', default => $record->status} }}</strong>
                        <small>حضور: {{ $record->check_in_at?->format('H:i') ?? '—' }} · انصراف: {{ $record->check_out_at?->format('H:i') ?? '—' }}</small>
                    </div>
                @empty
                    <p>لا توجد سجلات حضور.</p>
                @endforelse
            </div>
        </section>

        <section class="card">
            <div class="card-header"><span class="card-title">طلباتي الأخيرة</span></div>
            <div class="card-body myhr-list">
                @foreach($leaveRequests as $leave)
                    <div class="myhr-row">
                        <span>إجازة {{ $leave->leaveType?->name }} · {{ $leave->start_date?->format('Y-m-d') }}</span>
                        <strong>{{ match($leave->status) {'approved' => 'معتمد', 'rejected' => 'مرفوض', default => 'قيد المراجعة'} }}</strong>
                    </div>
                @endforeach
                @foreach($corrections as $correction)
                    <div class="myhr-row">
                        <span>تصحيح حضور · {{ $correction->work_date?->format('Y-m-d') }}</span>
                        <strong>{{ match($correction->status) {'approved' => 'معتمد', 'rejected' => 'مرفوض', default => 'قيد المراجعة'} }}</strong>
                        @if($correction->decision_note)<small>{{ $correction->decision_note }}</small>@endif
                    </div>
                @endforeach
                @if($leaveRequests->isEmpty() && $corrections->isEmpty())<p>لم تقدّم طلبات بعد.</p>@endif
            </div>
        </section>
    </div>
</div>
<style>
.myhr{max-width:1340px;margin:auto}
.myhr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-bottom:1rem}
.myhr-grid .card{min-width:0}
.myhr-form{display:grid;gap:.8rem}
.myhr-form .form-input{width:100%}
.myhr-dates{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}
.myhr-row{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.35rem;padding:.65rem 0;border-bottom:1px solid var(--border)}
.myhr-row:last-child{border-bottom:0}
.myhr-row small{width:100%;color:var(--text-muted)}
.myhr-tag{padding:.65rem 1rem;border:1px solid var(--border);border-radius:12px}
.myhr-list{max-height:450px;overflow:auto}
@media(max-width:850px){.myhr-grid{grid-template-columns:1fr}}
@media(max-width:530px){.myhr-dates{grid-template-columns:1fr}}
</style>
@endsection
