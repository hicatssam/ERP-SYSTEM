@extends('layouts.app')

@section('title', 'تسجيل بصمة الوجه')

@section('content')
<style>
.face-page{max-width:980px;margin:0 auto}
.face-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}
.face-card{background:var(--surface);border:1px solid var(--border);border-radius:18px;overflow:hidden}
.face-card-body{padding:1.25rem}
.face-employee{display:flex;align-items:center;gap:.9rem}
.face-avatar{width:54px;height:54px;border-radius:16px;display:grid;place-items:center;font-size:1.35rem;font-weight:900;color:var(--theme-primary);background:color-mix(in srgb,var(--theme-primary) 9%,var(--surface));border:1px solid color-mix(in srgb,var(--theme-primary) 20%,var(--border))}
.face-employee strong{display:block;font-size:1rem}.face-employee small{display:block;color:var(--text-muted);margin-top:.2rem}
.face-status{display:flex;align-items:center;gap:.45rem;margin-top:1rem;padding:.75rem .9rem;border-radius:12px;border:1px solid var(--border);background:var(--off-white);font-size:.76rem}
.face-dot{width:9px;height:9px;border-radius:50%;background:var(--text-muted)}
.face-status.active .face-dot{background:var(--theme-success)}
.face-status.pending .face-dot{background:var(--theme-warning)}
.face-status.revoked .face-dot{background:var(--theme-danger)}
.face-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem}
.face-panel{padding:1rem;border:1px solid var(--border);border-radius:14px;background:var(--off-white)}
.face-panel h3{margin:0 0 .35rem;font-size:.9rem}.face-panel p{margin:0;color:var(--text-muted);font-size:.72rem;line-height:1.75}
.face-consent{display:flex;align-items:flex-start;gap:.55rem;margin-top:1rem;padding:.8rem;border:1px solid var(--border);border-radius:12px;background:var(--surface);font-size:.72rem;line-height:1.6}
.face-actions{display:flex;gap:.55rem;flex-wrap:wrap;margin-top:1rem}
.face-result{display:none;margin-top:1rem;padding:.8rem .9rem;border-radius:12px;font-size:.74rem;line-height:1.7}
.face-result.show{display:block}.face-result.success{background:color-mix(in srgb,var(--theme-success) 8%,var(--surface));border:1px solid color-mix(in srgb,var(--theme-success) 25%,var(--border));color:var(--theme-success)}
.face-result.warning{background:color-mix(in srgb,var(--theme-warning) 8%,var(--surface));border:1px solid color-mix(in srgb,var(--theme-warning) 25%,var(--border))}
.face-result.error{background:color-mix(in srgb,var(--theme-danger) 7%,var(--surface));border:1px solid color-mix(in srgb,var(--theme-danger) 25%,var(--border));color:var(--theme-danger)}
.face-security{margin-top:1rem;padding:.85rem;border-radius:12px;background:color-mix(in srgb,var(--theme-info) 6%,var(--surface));border:1px solid color-mix(in srgb,var(--theme-info) 18%,var(--border));font-size:.7rem;line-height:1.75;color:var(--text-muted)}
@media(max-width:760px){.face-head{flex-direction:column}.face-grid{grid-template-columns:1fr}.face-actions .btn{flex:1}}
</style>

@php
    $profileStatus = $faceProfile?->status;
    $statusClass = $profileStatus === 'active'
        ? 'active'
        : ($profileStatus === 'pending_verification'
            ? 'pending'
            : ($profileStatus === 'revoked' ? 'revoked' : ''));
    $statusLabel = match($profileStatus) {
        'active' => 'بصمة الوجه مفعلة',
        'pending_verification' => 'بانتظار تأكيد FACEIO',
        'revoked' => 'بصمة الوجه ملغاة',
        default => 'لم يتم تسجيل الوجه بعد',
    };
@endphp

<div class="face-page">
    <div class="face-head">
        <div>
            <h1 class="page-heading">تسجيل بصمة الوجه</h1>
            <p class="page-subheading">تسجيل وربط وجه الموظف بالحضور والانصراف دون حفظ صورة الوجه داخل النظام.</p>
        </div>

        <a class="btn btn-outline" href="{{ route('attendance.index') }}">
            العودة للحضور
        </a>
    </div>

    <div class="face-card">
        <div class="face-card-body">
            <div class="face-employee">
                <div class="face-avatar">
                    {{ mb_substr(trim($employee->full_name), 0, 1) }}
                </div>

                <div>
                    <strong>{{ $employee->full_name }}</strong>
                    <small>
                        {{ $employee->employee_number }}
                        @if($employee->job_title)
                            · {{ $employee->job_title }}
                        @endif
                    </small>
                </div>
            </div>

            <div class="face-status {{ $statusClass }}" id="faceProfileStatus">
                <span class="face-dot"></span>
                <strong id="faceProfileStatusText">{{ $statusLabel }}</strong>
            </div>

            <div class="face-grid">
                <div class="face-panel">
                    <h3>كيف يتم التسجيل؟</h3>
                    <p>
                        يفتح FACEIO الكاميرا، يتحقق من الوجه، ثم يعيد معرفًا فريدًا.
                        النظام يخزن Hash لهذا المعرف فقط ولا يخزن صورة الوجه.
                    </p>
                </div>

                <div class="face-panel">
                    <h3>الحماية</h3>
                    <p>
                        عند تفعيل Webhook الآمن، لا تصبح البصمة فعالة إلا بعد وصول
                        تأكيد ENROLL من FACEIO إلى الخادم.
                    </p>
                </div>
            </div>

            <label class="face-consent">
                <input type="checkbox" id="faceConsent">
                <span>
                    أؤكد أن الموظف وافق على استخدام بيانات الوجه لغرض تسجيل الحضور والانصراف،
                    وأن عملية التسجيل تتم بحضوره.
                </span>
            </label>

            <div class="face-actions">
                <button
                    type="button"
                    class="btn btn-gold"
                    id="faceEnrollButton"
                >
                    {{ $faceProfile ? 'إعادة تسجيل الوجه' : 'تسجيل الوجه الآن' }}
                </button>

                @if($faceProfile && $faceProfile->status !== 'revoked')
                    <form
                        method="POST"
                        action="{{ route('attendance.face.revoke', $employee) }}"
                        onsubmit="return confirm('هل تريد إلغاء بصمة الوجه لهذا الموظف؟')"
                    >
                        @csrf
                        <button class="btn btn-danger" type="submit">
                            إلغاء البصمة
                        </button>
                    </form>
                @endif
            </div>

            <div class="face-result" id="faceResult"></div>

            <div class="face-security">
                <strong>مهم:</strong>
                @if($webhookRequired)
                    وضع الأمان الكامل مفعل: تسجيل الوجه يحتاج Webhook مؤكد من FACEIO قبل التفعيل.
                @else
                    وضع Webhook غير مطلوب في الإعدادات الحالية. هذا مناسب للاختبار فقط، وليس للإنتاج.
                @endif
            </div>
        </div>
    </div>
</div>

<div id="faceio-modal"></div>
@endsection

@push('scripts')
<script src="{{ $faceioScriptUrl }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const publicId = @json($faceioPublicId);
    const button = document.getElementById('faceEnrollButton');
    const consent = document.getElementById('faceConsent');
    const result = document.getElementById('faceResult');
    const statusBox = document.getElementById('faceProfileStatus');
    const statusText = document.getElementById('faceProfileStatusText');
    const enrollUrl = @json(route('attendance.face.enroll', $employee));
    const statusUrl = @json(route('attendance.face.status', $employee));
    const csrf = @json(csrf_token());

    const showResult = (message, type = 'warning') => {
        result.className = 'face-result show ' + type;
        result.textContent = message;
    };

    const errorMessage = (code) => {
        const codes = window.fioErrCode || {};

        if (code === codes.PERMISSION_REFUSED) return 'تم رفض إذن الكاميرا.';
        if (code === codes.TERMS_NOT_ACCEPTED) return 'لم تتم الموافقة على شروط استخدام الكاميرا.';
        if (code === codes.FACE_DUPLICATION) return 'هذا الوجه مسجل مسبقًا.';
        if (code === codes.MANY_FACES) return 'ظهر أكثر من وجه أمام الكاميرا.';
        if (code === codes.PAD_ATTACK) return 'تم رفض المحاولة بسبب اكتشاف صورة/عرض غير حي.';
        if (code === codes.FACE_MISMATCH) return 'لم تتطابق لقطات الوجه. حاول مرة أخرى.';
        if (code === codes.NO_FACES_DETECTED) return 'لم يتم اكتشاف وجه واضح.';
        if (code === codes.ABORTED_BY_USER) return 'تم إلغاء العملية.';
        if (code === codes.NETWORK_IO) return 'تعذر الاتصال بخدمة التحقق من الوجه.';

        return 'تعذر إكمال تسجيل الوجه. رمز الخطأ: ' + String(code ?? 'غير معروف');
    };

    const pollActivation = async () => {
        for (let attempt = 0; attempt < 10; attempt++) {
            await new Promise(resolve => setTimeout(resolve, 900));

            const response = await fetch(statusUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) continue;

            const data = await response.json();

            if (data.active) {
                statusBox.className = 'face-status active';
                statusText.textContent = 'بصمة الوجه مفعلة';
                showResult('تم تأكيد وتفعيل بصمة الوجه بنجاح.', 'success');
                return true;
            }
        }

        showResult(
            'تم حفظ التسجيل، لكن تأكيد Webhook لم يصل بعد. تأكد من إعداد Webhook العام في FACEIO.',
            'warning'
        );

        return false;
    };

    button?.addEventListener('click', async function () {
        if (!consent?.checked) {
            showResult('يجب تأكيد موافقة الموظف قبل تسجيل الوجه.', 'error');
            return;
        }

        if (!publicId || typeof window.faceIO !== 'function') {
            showResult('FACEIO غير مهيأ أو لم يتم تحميل المكتبة.', 'error');
            return;
        }

        button.disabled = true;
        showResult('جاري فتح الكاميرا...', 'warning');

        try {
            const faceio = new faceIO(publicId);

            const userInfo = await faceio.enroll({
                locale: 'auto',
                userConsent: true,
                showAbortBtn: true,
                payload: {
                    employee_id: {{ (int) $employee->id }}
                }
            });

            const response = await fetch(enrollUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    facial_id: userInfo.facialId
                })
            });

            const data = await response.json();

            if (!response.ok) {
                const firstError = data?.errors
                    ? Object.values(data.errors).flat()[0]
                    : data?.message;

                throw new Error(firstError || 'تعذر حفظ بصمة الوجه.');
            }

            if (data.active) {
                statusBox.className = 'face-status active';
                statusText.textContent = 'بصمة الوجه مفعلة';
                showResult(data.message, 'success');
            } else {
                statusBox.className = 'face-status pending';
                statusText.textContent = 'بانتظار تأكيد FACEIO';
                showResult(data.message, 'warning');
                await pollActivation();
            }
        } catch (error) {
            if (error instanceof Error) {
                showResult(error.message, 'error');
            } else {
                showResult(errorMessage(error), 'error');
            }
        } finally {
            button.disabled = false;
        }
    });
});
</script>
@endpush
