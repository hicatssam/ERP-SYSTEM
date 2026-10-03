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

    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <section class="card myhr-punch-card" id="myHrPunch">
        <div class="card-header"><span class="card-title">دوامي اليوم · {{ now()->format('Y-m-d') }}</span></div>
        <div class="card-body">
            <div class="myhr-punch-times">
                <div><small>الحضور</small><strong>{{ $todayRecord?->check_in_at?->format('H:i') ?? $selfAttendanceRequest?->check_in_at?->format('H:i') ?? 'لم يُسجّل' }}</strong></div>
                <div><small>الانصراف</small><strong>{{ $todayRecord?->check_out_at?->format('H:i') ?? $selfAttendanceRequest?->check_out_at?->format('H:i') ?? 'لم يُسجّل' }}</strong></div>
                <div><small>الحالة</small><strong>{{ $todayRecord ? match($todayRecord->status) {'present' => 'حاضر', 'leave' => 'إجازة', 'absent' => 'غائب', 'holiday' => 'عطلة', default => $todayRecord->status} : ($selfAttendanceRequest ? match($selfAttendanceRequest->status) {'approved' => 'معتمد', 'rejected' => 'مرفوض', default => ($selfAttendanceRequest->check_out_at ? 'بانتظار اعتماد المسؤول' : 'حضور مسجل مبدئيًا')} : 'بانتظار أول تسجيل') }}</strong></div>
            </div>
            @if($selfAttendanceRequest && $selfAttendanceRequest->work_date->toDateString() !== now()->toDateString())
                <p class="page-subheading">التسجيل المعروض بدأ بتاريخ {{ $selfAttendanceRequest->work_date->format('Y-m-d') }}.</p>
            @endif
            <div class="myhr-punch-options">
                <div>
                    <strong>تسجيل حضوري من البوابة</strong>
                    <small>نحفظ وقت الخادم عند دخولك وخروجك. يظهر حضورك مبدئيًا، وبعد الانصراف يراجعه المسؤول قبل اعتماده في سجل الرواتب.</small>
                    @if($selfAttendanceRequest?->status === 'pending' && !$selfAttendanceRequest->check_out_at)
                        <form method="POST" action="{{ route('my-hr.punch') }}">@csrf<input type="hidden" name="action" value="check_out"><button class="btn btn-gold" type="submit">تسجيل انصرافي الآن</button></form>
                    @elseif(!$selfPunchEnabled)
                        <small>هذه الطريقة غير مفعلة في إعدادات الحضور.</small>
                    @elseif(!$todayRecord && !$todaySelfRequest)
                        <form method="POST" action="{{ route('my-hr.punch') }}">@csrf<input type="hidden" name="action" value="check_in"><button class="btn btn-gold" type="submit">حضرت للدوام الآن</button></form>
                    @else
                        <small>تم تسجيل دوام اليوم. تفاصيل الطلب وحالة الاعتماد تظهر أدناه.</small>
                    @endif
                    @if($selfAttendanceRequest?->decision_note)<small>ملاحظة المسؤول: {{ $selfAttendanceRequest->decision_note }}</small>@endif
                </div>
                <div>
                    <strong>بصمة الوجه</strong>
                    <small>تفتح الكاميرا وتتحقق من وجهك المسجّل قبل تسجيل الحضور أو الانصراف في سجلك.</small>
                    @if($facePunchEnabled && $faceConfigured && $faceProfileActive
                        && !($selfAttendanceRequest?->status === 'pending' && (!$selfAttendanceRequest->check_out_at || $todaySelfRequest))
                        && (!$todayRecord || (!$todayRecord->approved_at && $todayRecord->status === 'present' && !$todayRecord->check_out_at)))
                        <button class="btn btn-gold" type="button" data-myhr-open="myFaceDialog">{{ $todayRecord?->check_in_at ? 'تسجيل الانصراف بالوجه' : 'تسجيل الحضور بالوجه' }}</button>
                    @elseif(!$facePunchEnabled)
                        <small>التسجيل من البوابة غير مفعّل من إعدادات الحضور.</small>
                    @elseif(!$faceConfigured)
                        <small>خدمة بصمة الوجه غير مهيأة بعد.</small>
                    @elseif(!$faceProfileActive)
                        <small>اطلب من مسؤول الحضور تسجيل بصمة وجهك أولًا.</small>
                    @elseif($selfAttendanceRequest?->status === 'pending' && (!$selfAttendanceRequest->check_out_at || $todaySelfRequest))
                        <small>أنهِ أو راجع طلب الدوام الذاتي الحالي قبل استخدام الوجه.</small>
                    @else
                        <small>لا يمكن تعديل سجل اليوم بعد اكتماله أو اعتماده.</small>
                    @endif
                </div>
                <div>
                    <strong>بصمة جهاز الفرع</strong>
                    <small>سجّل بإصبعك على جهاز الحضور في الفرع؛ عندما تصل البصمة للنظام تظهر في سجل الحضور أدناه.</small>
                    <a class="btn btn-outline" href="#myHrAttendance">عرض سجل الحضور</a>
                </div>
            </div>
        </div>
    </section>

    @if($facePunchEnabled && $faceConfigured && $faceProfileActive)
        <dialog class="myhr-dialog myhr-face-dialog" id="myFaceDialog" aria-labelledby="myFaceTitle">
            <div class="myhr-dialog-head"><h2 id="myFaceTitle">تسجيل الدوام بالوجه</h2><button type="button" data-myhr-close aria-label="إغلاق">×</button></div>
            <div class="card-body myhr-face-content">
                <p class="page-subheading">انظر للأمام ثم لف رأسك إلى أحد الجانبين. ستُرسل اللقطتان لخدمة التحقق، ولن تُحفظا في سجل حضورك.</p>
                <div class="myhr-face-camera"><video id="myFaceVideo" autoplay muted playsinline></video><span id="myFacePlaceholder">الكاميرا ستفتح بعد بدء التحقق</span></div>
                <canvas id="myFaceCanvas" hidden></canvas>
                <p id="myFaceStatus" role="status" aria-live="polite">جاهز للبدء.</p>
                <div class="myhr-dialog-actions"><button class="btn btn-ghost" type="button" data-myhr-close>إغلاق</button><button class="btn btn-gold" type="button" id="myFaceAction">بدء التحقق</button></div>
            </div>
        </dialog>
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
                @foreach($selfAttendanceRequests as $attendanceRequest)
                    <div class="myhr-row">
                        <span>تسجيل دوام · {{ $attendanceRequest->work_date->format('Y-m-d') }}</span>
                        <strong>{{ match($attendanceRequest->status) {'approved' => 'معتمد', 'rejected' => 'مرفوض', default => ($attendanceRequest->check_out_at ? 'بانتظار المراجعة' : 'على رأس العمل')} }}</strong>
                        <small>حضور: {{ $attendanceRequest->check_in_at->format('H:i') }} · انصراف: {{ $attendanceRequest->check_out_at?->format('H:i') ?? '—' }}</small>
                        @if($attendanceRequest->decision_note)<small>{{ $attendanceRequest->decision_note }}</small>@endif
                    </div>
                @endforeach
                @if($leaveRequests->isEmpty() && $corrections->isEmpty() && $selfAttendanceRequests->isEmpty())<p>لم تقدّم طلبات بعد.</p>@endif
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
.myhr-punch-card{margin-bottom:1rem}.myhr-punch-times{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem;margin-bottom:1rem}.myhr-punch-times>div{padding:.8rem;border:1px solid var(--border);border-radius:12px}.myhr-punch-times small,.myhr-punch-options small{display:block;color:var(--text-muted)}.myhr-punch-times strong{display:block;margin-top:.3rem}.myhr-punch-options{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem}.myhr-punch-options>div{display:grid;align-content:start;gap:.65rem;padding:1rem;border:1px solid var(--border);border-radius:12px}.myhr-punch-options .btn{justify-self:start}.myhr-face-content{display:grid;gap:.8rem}.myhr-face-camera{position:relative;background:#101820;border-radius:12px;overflow:hidden;min-height:220px;display:grid;place-items:center;color:#fff}.myhr-face-camera video{width:100%;max-height:360px;object-fit:cover;transform:scaleX(-1)}.myhr-face-camera span{position:absolute;inset:0;display:grid;place-items:center;padding:1rem;text-align:center}.myhr-face-camera.live span{display:none}#myFaceStatus{min-height:1.5rem;margin:0}
@media(max-width:850px){.myhr-grid,.myhr-punch-options{grid-template-columns:1fr}}
@media(max-width:530px){.myhr-dates,.myhr-punch-options{grid-template-columns:1fr}.myhr-punch-times{gap:.35rem}.myhr-punch-times>div{padding:.5rem}.myhr-header-actions{justify-content:flex-start}}
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
@if($facePunchEnabled && $faceConfigured && $faceProfileActive)
<script>
(() => {
    const dialog = document.getElementById('myFaceDialog');
    const video = document.getElementById('myFaceVideo');
    const canvas = document.getElementById('myFaceCanvas');
    const camera = dialog.querySelector('.myhr-face-camera');
    const action = document.getElementById('myFaceAction');
    const status = document.getElementById('myFaceStatus');
    const csrf = @json(csrf_token());
    const challengeUrl = @json(route('my-hr.face.challenge'));
    const punchUrl = @json(route('my-hr.face.punch'));
    let stream = null, token = null, front = null, phase = 'idle';

    const stopCamera = () => {
        stream?.getTracks().forEach(track => track.stop());
        stream = null;
        video.srcObject = null;
        camera.classList.remove('live');
    };
    const reset = () => {
        stopCamera();
        token = front = null;
        phase = 'idle';
        action.disabled = false;
        action.textContent = 'بدء التحقق';
    };
    const capture = () => {
        if (!video.videoWidth || !video.videoHeight) throw new Error('انتظر حتى تظهر صورة الكاميرا، ثم حاول مجددًا.');
        const width = Math.min(720, video.videoWidth);
        canvas.width = width;
        canvas.height = Math.round(video.videoHeight * width / video.videoWidth);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', .85);
    };
    const send = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
            body: JSON.stringify(body),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.errors ? Object.values(result.errors).flat()[0] : result.message);
        return result;
    };
    action.addEventListener('click', async () => {
        action.disabled = true;
        try {
            if (phase === 'idle') {
                if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                    throw new Error('تشغيل الكاميرا يحتاج HTTPS أو localhost ومتصفحًا يدعمها.');
                }
                stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'user'}, audio: false});
                video.srcObject = stream;
                await video.play();
                // Start the short-lived server challenge only after camera permission and playback.
                const challenge = await send(challengeUrl, {});
                token = challenge.token;
                camera.classList.add('live');
                phase = 'front';
                status.textContent = 'انظر مباشرة إلى الكاميرا ثم التقط الصورة الأمامية.';
                action.textContent = 'التقاط الصورة الأمامية';
            } else if (phase === 'front') {
                front = capture();
                phase = 'turn';
                status.textContent = 'لف رأسك بوضوح يمينًا أو يسارًا مع بقاء وجهك ظاهرًا.';
                action.textContent = 'التقاط حركة الرأس والتسجيل';
            } else if (phase === 'turn') {
                const turned = capture();
                phase = 'submitting';
                status.textContent = 'جاري التحقق وتسجيل التوقيت...';
                const result = await send(punchUrl, {front_image: front, turned_image: turned, challenge_token: token});
                stopCamera();
                phase = 'done';
                status.textContent = result.message + ' ' + (result.action === 'check_in' ? result.attendance.check_in_at : result.attendance.check_out_at);
                action.textContent = 'تم التسجيل';
                setTimeout(() => window.location.reload(), 1200);
            }
        } catch (error) {
            reset();
            status.textContent = error.message || 'تعذر التحقق. حاول مجددًا.';
        } finally {
            if (phase !== 'done') action.disabled = false;
        }
    });
    dialog.addEventListener('close', reset);
    window.addEventListener('pagehide', stopCamera);
})();
</script>
@endif
@endsection
