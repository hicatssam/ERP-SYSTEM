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
        <div class="myhr-header-actions">
            <span class="myhr-tag">{{ $employee->employee_number }} · {{ $shift?->name ?? 'لا توجد وردية محددة اليوم' }}</span>
            <button class="btn btn-gold" type="button" data-myhr-open="myLeaveDialog" @disabled($leaveTypes->isEmpty())>+ طلب إجازة</button>
            <button class="btn btn-outline" type="button" data-myhr-open="myCorrectionDialog">+ تصحيح حضور</button>
        </div>
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

        <dialog class="myhr-dialog" id="myLeaveDialog" aria-labelledby="myLeaveTitle">
            <div class="myhr-dialog-head"><h2 id="myLeaveTitle">طلب إجازة</h2><button type="button" data-myhr-close aria-label="إغلاق">×</button></div>
            <form class="card-body myhr-form" action="{{ route('my-hr.leaves.store') }}" method="POST">
                @csrf
                <input type="hidden" name="_form" value="leave">
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
                <div><label class="form-label" for="myLeaveFraction">المدة</label><select class="form-input" id="myLeaveFraction" name="day_fraction"><option value="1">يوم كامل أو الفترة المحددة</option><option value="0.5" @selected(old('day_fraction') == '0.5')>نصف يوم (تاريخ واحد)</option></select></div>
                <div><label class="form-label" for="myLeaveSlot">أي نصف؟ (لنصف اليوم فقط)</label><select class="form-input" id="myLeaveSlot" name="half_day_slot"><option value="">اختر عند طلب نصف يوم</option><option value="first_half" @selected(old('half_day_slot') === 'first_half')>النصف الأول</option><option value="second_half" @selected(old('half_day_slot') === 'second_half')>النصف الثاني</option></select></div>
                <div><label class="form-label" for="myLeaveReason">السبب</label><textarea class="form-input" id="myLeaveReason" name="reason" rows="2" maxlength="1500">{{ old('reason') }}</textarea></div>
                <div class="myhr-dialog-actions"><button class="btn btn-ghost" type="button" data-myhr-close>إلغاء</button><button class="btn btn-gold" type="submit" @disabled($leaveTypes->isEmpty())>إرسال الطلب</button></div>
            </form>
        </dialog>

        <dialog class="myhr-dialog" id="myCorrectionDialog" aria-labelledby="myCorrectionTitle">
            <div class="myhr-dialog-head"><h2 id="myCorrectionTitle">طلب تصحيح حضور</h2><button type="button" data-myhr-close aria-label="إغلاق">×</button></div>
            <form class="card-body myhr-form" action="{{ route('my-hr.corrections.store') }}" method="POST">
                @csrf
                <input type="hidden" name="_form" value="correction">
                <p class="page-subheading">اختر يومًا مضى أو اليوم، وأدخل وقت الحضور أو الانصراف الصحيح مع السبب (مثل عطل جهاز البصمة). يظهر الطلب للمسؤول المخوّل بمراجعة الحضور في فرعك؛ لن يتغير السجل إلا بعد اعتماده، ويظهر القرار ضمن «طلباتي الأخيرة».</p>
                <div><label class="form-label" for="myWorkDate">يوم الدوام</label><input class="form-input" id="myWorkDate" name="work_date" type="date" max="{{ now()->toDateString() }}" value="{{ old('work_date') }}" required></div>
                <div class="myhr-dates">
                    <div><label class="form-label" for="myCheckIn">الحضور المطلوب</label><input class="form-input" id="myCheckIn" name="check_in_at" type="datetime-local" value="{{ old('check_in_at') }}"></div>
                    <div><label class="form-label" for="myCheckOut">الانصراف المطلوب</label><input class="form-input" id="myCheckOut" name="check_out_at" type="datetime-local" value="{{ old('check_out_at') }}"></div>
                </div>
                <div><label class="form-label" for="myCorrectionReason">سبب التصحيح</label><textarea class="form-input" id="myCorrectionReason" name="reason" rows="2" maxlength="1000" required>{{ old('reason') }}</textarea></div>
                <div class="myhr-dialog-actions"><button class="btn btn-ghost" type="button" data-myhr-close>إلغاء</button><button class="btn btn-gold" type="submit">إرسال التصحيح للمراجعة</button></div>
            </form>
        </dialog>

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
        <section class="card" id="myHrAttendance">
            <div class="card-header"><span class="card-title">سجل الحضور الأخير</span></div>
            <div class="card-body myhr-list">
                @forelse($records as $record)
                    <div class="myhr-row">
                        <span>{{ $record->work_date?->format('Y-m-d') }}</span>
                        <strong>{{ match($record->status) {'present' => 'حاضر', 'absent' => 'غائب', 'leave' => 'إجازة', 'holiday' => 'عطلة', default => $record->status} }}</strong>
                        <small>حضور: {{ $record->check_in_at?->format('H:i') ?? '—' }} · انصراف: {{ $record->check_out_at?->format('H:i') ?? '—' }}</small>
                        <button class="btn btn-outline btn-sm" type="button" data-myhr-open="myCorrectionDialog" data-myhr-date="{{ $record->work_date?->toDateString() }}">طلب تصحيح</button>
                    </div>
                @empty
                    <p>لا توجد سجلات حضور.</p>
                @endforelse
            </div>
        </section>

        <section class="card" id="myHrRequests">
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
.myhr-header-actions{display:flex;gap:.55rem;flex-wrap:wrap;align-items:center;justify-content:flex-end}
.myhr-dialog{width:min(570px,calc(100vw - 24px));max-height:min(88vh,800px);overflow:auto;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:16px;padding:0;box-shadow:0 22px 60px #0006}
.myhr-dialog::backdrop{background:#0009}
.myhr-dialog-head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border)}
.myhr-dialog-head h2{margin:0;font-size:1.2rem}.myhr-dialog-head button{font-size:1.6rem;background:transparent;border:0;color:inherit;cursor:pointer}
.myhr-dialog-actions{display:flex;justify-content:flex-end;gap:.5rem}
@media(max-width:850px){.myhr-grid{grid-template-columns:1fr}}
@media(max-width:530px){.myhr-dates{grid-template-columns:1fr}.myhr-header-actions{justify-content:flex-start}}
</style>
<script>
document.querySelectorAll('[data-myhr-open]').forEach(button => button.addEventListener('click', () => {
    const dialog = document.getElementById(button.dataset.myhrOpen);
    if (!dialog) return;
    if (button.dataset.myhrDate) document.getElementById('myWorkDate').value = button.dataset.myhrDate;
    dialog.showModal();
    dialog.querySelector('select, input:not([type="hidden"])')?.focus();
}));
document.querySelectorAll('dialog.myhr-dialog').forEach(dialog => {
    dialog.querySelectorAll('[data-myhr-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
});
@if($errors->any() && in_array(old('_form'), ['leave', 'correction'], true))
document.getElementById(@json(old('_form') === 'leave' ? 'myLeaveDialog' : 'myCorrectionDialog'))?.showModal();
@endif
</script>
@endsection
