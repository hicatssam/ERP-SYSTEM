@extends('layouts.app')

@section('title', 'كشك الحضور بالوجه')

@section('content')
<style>
.face-kiosk{max-width:820px;margin:0 auto}
.kiosk-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem}
.kiosk-card{position:relative;overflow:hidden;text-align:center;padding:2rem 1.25rem;border:1px solid var(--border);border-radius:22px;background:var(--surface)}
.kiosk-card:before{content:"";position:absolute;width:220px;height:220px;border-radius:50%;right:-90px;top:-110px;background:color-mix(in srgb,var(--theme-primary) 8%,transparent)}
.kiosk-location{display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .7rem;border-radius:999px;background:var(--off-white);border:1px solid var(--border);font-size:.72rem;font-weight:800}
.kiosk-dot{width:8px;height:8px;border-radius:50%;background:var(--theme-success)}
.kiosk-face{width:118px;height:118px;margin:1.4rem auto .9rem;border-radius:34px;display:grid;place-items:center;color:var(--theme-primary);background:color-mix(in srgb,var(--theme-primary) 8%,var(--surface));border:1px solid color-mix(in srgb,var(--theme-primary) 18%,var(--border))}
.kiosk-face svg{width:62px;height:62px;fill:none;stroke:currentColor;stroke-width:1.5;stroke-linecap:round}
.kiosk-card h2{margin:.2rem 0;font-size:1.35rem}.kiosk-card p{margin:.35rem auto 0;max-width:520px;color:var(--text-muted);font-size:.76rem;line-height:1.7}
.kiosk-action{margin-top:1.25rem;min-width:240px;min-height:48px;font-size:.85rem}
.kiosk-result{display:none;margin:1.2rem auto 0;max-width:560px;padding:1rem;border-radius:14px;text-align:right}
.kiosk-result.show{display:block}.kiosk-result.success{border:1px solid color-mix(in srgb,var(--theme-success) 28%,var(--border));background:color-mix(in srgb,var(--theme-success) 8%,var(--surface))}
.kiosk-result.error{border:1px solid color-mix(in srgb,var(--theme-danger) 28%,var(--border));background:color-mix(in srgb,var(--theme-danger) 7%,var(--surface))}
.kiosk-result.warning{border:1px solid color-mix(in srgb,var(--theme-warning) 28%,var(--border));background:color-mix(in srgb,var(--theme-warning) 7%,var(--surface))}
.kiosk-result strong{display:block;font-size:.95rem}.kiosk-result span{display:block;margin-top:.25rem;color:var(--text-muted);font-size:.72rem;line-height:1.6}
.kiosk-select{max-width:420px;margin:0 auto 1rem;text-align:right}.kiosk-note{margin-top:1rem;font-size:.68rem;color:var(--text-muted)}
@media(max-width:700px){.kiosk-head{flex-direction:column}.kiosk-card{padding:1.4rem .9rem}.kiosk-action{width:100%}}
</style>

<div class="face-kiosk">
    <div class="kiosk-head">
        <div>
            <h1 class="page-heading">كشك الحضور بالوجه</h1>
            <p class="page-subheading">حضور وانصراف الموظفين باستخدام كاميرا الهاتف أو التابلت.</p>
        </div>

        <a class="btn btn-outline" href="{{ route('attendance.index') }}">
            العودة للحضور
        </a>
    </div>

    @if(auth()->user()->isAdmin() && $locations->count() > 1)
        <form class="kiosk-select" method="GET" action="{{ route('attendance.face.kiosk') }}">
            <label class="form-label">الفرع الذي يعمل عليه هذا الكشك</label>
            <select
                class="form-input"
                name="location_id"
                onchange="this.form.submit()"
            >
                @foreach($locations as $branch)
                    <option
                        value="{{ $branch->id }}"
                        @selected((int) $branch->id === (int) $location->id)
                    >
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
        </form>
    @endif

    <div class="kiosk-card">
        <div class="kiosk-location">
            <span class="kiosk-dot"></span>
            {{ $location->name }}
        </div>

        <div class="kiosk-face">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M8 3H6a3 3 0 0 0-3 3v2M16 3h2a3 3 0 0 1 3 3v2M8 21H6a3 3 0 0 1-3-3v-2M16 21h2a3 3 0 0 0 3-3v-2"/>
                <path d="M9 10h.01M15 10h.01M9 15c1.6 1.3 4.4 1.3 6 0"/>
            </svg>
        </div>

        <h2>ضع وجهك أمام الكاميرا</h2>
        <p>
            سيقوم النظام بالتعرف على الموظف تلقائيًا ثم تسجيل الحضور أو الانصراف
            حسب سجل اليوم والوردية الحالية.
        </p>

        <button
            class="btn btn-gold kiosk-action"
            type="button"
            id="facePunchButton"
        >
            فتح الكاميرا
        </button>

        <div class="kiosk-result" id="kioskResult"></div>

        <div class="kiosk-note">
            @if($webhookRequired)
                التحقق الآمن عبر Webhook مفعل.
            @else
                وضع الاختبار بدون Webhook مفعل — لا تستخدمه في الإنتاج.
            @endif
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
    const punchUrl = @json(route('attendance.face.punch'));
    const locationId = {{ (int) $location->id }};
    const csrf = @json(csrf_token());
    const button = document.getElementById('facePunchButton');
    const result = document.getElementById('kioskResult');

    const showResult = (html, type = 'warning') => {
        result.className = 'kiosk-result show ' + type;
        result.innerHTML = html;
    };

    const errorMessage = (code) => {
        const codes = window.fioErrCode || {};

        if (code === codes.PERMISSION_REFUSED) return 'تم رفض إذن الكاميرا.';
        if (code === codes.UNRECOGNIZED_FACE) return 'الوجه غير مسجل في النظام.';
        if (code === codes.MANY_FACES) return 'يجب أن يظهر وجه واحد فقط أمام الكاميرا.';
        if (code === codes.PAD_ATTACK) return 'تم رفض المحاولة بسبب اكتشاف صورة أو عرض غير حي.';
        if (code === codes.WRONG_PIN_CODE) return 'رمز التحقق غير صحيح.';
        if (code === codes.NO_FACES_DETECTED) return 'لم يتم اكتشاف وجه واضح.';
        if (code === codes.NETWORK_IO) return 'تعذر الاتصال بخدمة التحقق.';
        if (code === codes.ABORTED_BY_USER) return 'تم إلغاء العملية.';

        return 'تعذر التحقق من الوجه. رمز الخطأ: ' + String(code ?? 'غير معروف');
    };

    const postPunch = async (facialId, attempt = 0) => {
        const response = await fetch(punchUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                facial_id: facialId,
                location_id: locationId
            })
        });

        const data = await response.json();

        if (
            response.status === 422
            && data?.errors?.face_confirmation
            && attempt < 8
        ) {
            await new Promise(resolve => setTimeout(resolve, 700));
            return postPunch(facialId, attempt + 1);
        }

        if (!response.ok) {
            const firstError = data?.errors
                ? Object.values(data.errors).flat()[0]
                : data?.message;

            throw new Error(firstError || 'تعذر تسجيل الحضور.');
        }

        return data;
    };

    button?.addEventListener('click', async function () {
        if (!publicId || typeof window.faceIO !== 'function') {
            showResult(
                '<strong>FACEIO غير مهيأ</strong><span>تحقق من Public ID وتحميل المكتبة.</span>',
                'error'
            );
            return;
        }

        button.disabled = true;
        button.textContent = 'جاري التحقق...';
        showResult(
            '<strong>افتح الكاميرا</strong><span>اتبع التعليمات التي تظهر على الشاشة.</span>',
            'warning'
        );

        try {
            const faceio = new faceIO(publicId);
            const userData = await faceio.authenticate({
                locale: 'auto'
            });

            const data = await postPunch(userData.facialId);

            const actionLabel = data.action === 'check_in'
                ? 'تم تسجيل الحضور'
                : 'تم تسجيل الانصراف';

            const time = data.action === 'check_in'
                ? data.attendance.check_in_at
                : data.attendance.check_out_at;

            showResult(
                '<strong>✓ ' + actionLabel + '</strong>'
                + '<span>'
                + data.employee.name
                + ' · '
                + (time || '')
                + (data.attendance.shift ? ' · ' + data.attendance.shift : '')
                + '</span>',
                'success'
            );

            setTimeout(() => window.location.reload(), 2800);
        } catch (error) {
            if (error instanceof Error) {
                showResult(
                    '<strong>لم يتم التسجيل</strong><span>'
                    + error.message
                    + '</span>',
                    'error'
                );
            } else {
                showResult(
                    '<strong>لم يتم التحقق</strong><span>'
                    + errorMessage(error)
                    + '</span>',
                    'error'
                );
            }

            button.disabled = false;
            button.textContent = 'إعادة المحاولة';
        }
    });
});
</script>
@endpush
