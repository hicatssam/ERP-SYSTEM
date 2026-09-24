<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>كشك الحضور بالوجه — {{ $location->name }}</title>

    <style>
        :root{
            color-scheme:light;
            --gold:#b68b32;
            --gold-dark:#8c6818;
            --ink:#111827;
            --muted:#667085;
            --border:#e5e7eb;
            --surface:#ffffff;
            --soft:#f8fafc;
            --success:#16845b;
            --danger:#b42318;
            --warning:#b7791f;
        }

        *{box-sizing:border-box}

        html,body{
            width:100%;
            min-height:100%;
            margin:0;
        }

        body{
            min-height:100vh;
            display:grid;
            place-items:center;
            padding:clamp(16px,3vw,34px);
            background:
                radial-gradient(circle at 85% 8%,rgba(182,139,50,.12),transparent 28%),
                radial-gradient(circle at 8% 92%,rgba(17,24,39,.07),transparent 30%),
                #f3f4f6;
            color:var(--ink);
            font-family:"Cairo","Segoe UI",Tahoma,Arial,sans-serif;
        }

        button,select{font:inherit}

        .kiosk-shell{
            width:min(760px,100%);
        }

        .kiosk-brand{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:.65rem;
            margin-bottom:1rem;
        }

        .kiosk-brand-mark{
            width:44px;
            height:44px;
            display:grid;
            place-items:center;
            border-radius:14px;
            background:#15191f;
            color:#d9aa43;
            font-size:1rem;
            font-weight:900;
            box-shadow:0 8px 24px rgba(15,23,42,.12);
        }

        .kiosk-brand-copy strong{
            display:block;
            font-size:.95rem;
        }

        .kiosk-brand-copy span{
            display:block;
            margin-top:.08rem;
            color:var(--muted);
            font-size:.67rem;
        }

        .kiosk-card{
            position:relative;
            overflow:hidden;
            padding:clamp(24px,5vw,48px);
            text-align:center;
            border:1px solid var(--border);
            border-radius:28px;
            background:rgba(255,255,255,.96);
            box-shadow:0 24px 70px rgba(15,23,42,.12);
        }

        .kiosk-card:before{
            content:"";
            position:absolute;
            width:250px;
            height:250px;
            border-radius:50%;
            right:-120px;
            top:-140px;
            background:rgba(182,139,50,.08);
        }

        .kiosk-location{
            position:relative;
            z-index:1;
            display:inline-flex;
            align-items:center;
            gap:.45rem;
            min-height:34px;
            padding:.35rem .75rem;
            border:1px solid var(--border);
            border-radius:999px;
            background:var(--soft);
            font-size:.72rem;
            font-weight:800;
        }

        .kiosk-dot{
            width:8px;
            height:8px;
            border-radius:50%;
            background:var(--success);
            box-shadow:0 0 0 4px rgba(22,132,91,.10);
        }

        .kiosk-select{
            position:relative;
            z-index:1;
            max-width:390px;
            margin:0 auto 1rem;
            text-align:right;
        }

        .kiosk-select label{
            display:block;
            margin-bottom:.35rem;
            color:var(--muted);
            font-size:.67rem;
            font-weight:800;
        }

        .kiosk-select select{
            width:100%;
            min-height:42px;
            padding:0 .8rem;
            border:1px solid var(--border);
            border-radius:11px;
            background:#fff;
            color:var(--ink);
            outline:none;
        }

        .kiosk-face{
            width:126px;
            height:126px;
            margin:1.55rem auto 1rem;
            display:grid;
            place-items:center;
            border:1px solid rgba(182,139,50,.24);
            border-radius:36px;
            background:rgba(182,139,50,.08);
            color:var(--gold-dark);
        }

        .kiosk-face svg{
            width:66px;
            height:66px;
            fill:none;
            stroke:currentColor;
            stroke-width:1.45;
            stroke-linecap:round;
            stroke-linejoin:round;
        }

        h1{
            margin:.15rem 0 0;
            font-size:clamp(1.35rem,4vw,1.8rem);
            line-height:1.35;
        }

        .kiosk-description{
            max-width:520px;
            margin:.55rem auto 0;
            color:var(--muted);
            font-size:.78rem;
            line-height:1.8;
        }

        .kiosk-action{
            min-width:250px;
            min-height:50px;
            margin-top:1.3rem;
            padding:.65rem 1.25rem;
            border:0;
            border-radius:13px;
            background:linear-gradient(135deg,#c99d3d,#a8791d);
            color:#fff;
            cursor:pointer;
            font-size:.84rem;
            font-weight:900;
            box-shadow:0 10px 24px rgba(182,139,50,.24);
            transition:.16s ease;
        }

        .kiosk-action:hover:not(:disabled){
            transform:translateY(-1px);
        }

        .kiosk-action:disabled{
            opacity:.58;
            cursor:not-allowed;
            box-shadow:none;
        }

        .kiosk-result{
            display:none;
            max-width:560px;
            margin:1.15rem auto 0;
            padding:1rem;
            border-radius:14px;
            text-align:right;
        }

        .kiosk-result.show{display:block}

        .kiosk-result.success{
            border:1px solid rgba(22,132,91,.24);
            background:rgba(22,132,91,.07);
        }

        .kiosk-result.error{
            border:1px solid rgba(180,35,24,.24);
            background:rgba(180,35,24,.06);
        }

        .kiosk-result.warning{
            border:1px solid rgba(183,121,31,.24);
            background:rgba(183,121,31,.07);
        }

        .kiosk-result strong{
            display:block;
            font-size:.92rem;
        }

        .kiosk-result span{
            display:block;
            margin-top:.28rem;
            color:var(--muted);
            font-size:.72rem;
            line-height:1.7;
        }

        .kiosk-security{
            display:flex;
            justify-content:center;
            gap:.4rem;
            flex-wrap:wrap;
            margin-top:1.1rem;
            color:var(--muted);
            font-size:.63rem;
        }

        .kiosk-security span{
            padding:.25rem .48rem;
            border:1px solid var(--border);
            border-radius:999px;
            background:var(--soft);
        }

        .kiosk-secure-warning{
            display:none;
            margin:.85rem auto 0;
            max-width:560px;
            padding:.8rem .9rem;
            border:1px solid rgba(180,35,24,.24);
            border-radius:12px;
            background:rgba(180,35,24,.06);
            color:var(--danger);
            font-size:.7rem;
            line-height:1.7;
            text-align:right;
        }

        .kiosk-secure-warning.show{
            display:block;
        }

        .kiosk-clock{
            margin-top:1rem;
            color:var(--muted);
            font-size:.69rem;
        }

        @media(max-width:620px){
            body{padding:12px}
            .kiosk-card{padding:24px 16px;border-radius:22px}
            .kiosk-face{width:108px;height:108px;border-radius:30px}
            .kiosk-action{width:100%;min-width:0}
        }
    </style>
</head>

<body>
    <main class="kiosk-shell">
        <div class="kiosk-brand">
            <div class="kiosk-brand-mark">دهب</div>

            <div class="kiosk-brand-copy">
                <strong>نظام الحضور</strong>
                <span>تسجيل آمن بالوجه</span>
            </div>
        </div>

        <section class="kiosk-card">
            @if(auth()->user()->isAdmin() && $locations->count() > 1)
                <form
                    class="kiosk-select"
                    method="GET"
                    action="{{ route('attendance.face.kiosk') }}"
                >
                    <label for="kioskLocation">
                        الفرع الذي يعمل عليه هذا الكشك
                    </label>

                    <select
                        id="kioskLocation"
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

            <h1>ضع وجهك أمام الكاميرا</h1>

            <p class="kiosk-description">
                يتعرف النظام على الموظف تلقائيًا، ثم يسجل الحضور أو
                الانصراف حسب سجل اليوم والوردية الحالية.
            </p>

            <button
                class="kiosk-action"
                type="button"
                id="facePunchButton"
            >
                فتح الكاميرا
            </button>

            <div
                class="kiosk-secure-warning"
                id="secureContextWarning"
            >
                الكاميرا في المتصفح تحتاج اتصال HTTPS آمن.
                افتح كشك الحضور من رابط HTTPS بدل عنوان الشبكة المحلي HTTP.
            </div>

            <div
                class="kiosk-result"
                id="kioskResult"
                aria-live="polite"
            ></div>

            <div class="kiosk-security">
                <span>تحقق بالوجه</span>
                <span>عزل حسب الفرع</span>

                @if($webhookRequired)
                    <span>Webhook مؤكد</span>
                @endif
            </div>

            <div
                class="kiosk-clock"
                id="kioskClock"
            ></div>
        </section>
    </main>

    <div id="faceio-modal"></div>

    <script src="{{ $faceioScriptUrl }}"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const publicId = @json($faceioPublicId);
        const punchUrl = @json(route('attendance.face.punch'));
        const locationId = {{ (int) $location->id }};
        const csrf = @json(csrf_token());

        const button =
            document.getElementById('facePunchButton');

        const result =
            document.getElementById('kioskResult');

        const secureWarning =
            document.getElementById('secureContextWarning');

        const clock =
            document.getElementById('kioskClock');

        const refreshClock = () => {
            if (!clock) return;

            clock.textContent =
                new Intl.DateTimeFormat(
                    'ar',
                    {
                        dateStyle: 'full',
                        timeStyle: 'medium'
                    }
                ).format(new Date());
        };

        refreshClock();
        setInterval(refreshClock, 1000);

        const showResult = (
            html,
            type = 'warning'
        ) => {
            result.className =
                'kiosk-result show ' + type;

            result.innerHTML = html;
        };

        const escapeHtml = (value) => {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        };

        if (!window.isSecureContext) {
            button.disabled = true;
            secureWarning.classList.add('show');

            showResult(
                '<strong>الكاميرا غير متاحة على اتصال غير آمن</strong>'
                + '<span>استخدم HTTPS لتشغيل بصمة الوجه من الهاتف أو التابلت.</span>',
                'error'
            );
        }

        const errorMessage = (code) => {
            const codes =
                window.fioErrCode || {};

            if (code === codes.PERMISSION_REFUSED) {
                return 'تم رفض إذن الكاميرا.';
            }

            if (code === codes.UNRECOGNIZED_FACE) {
                return 'الوجه غير مسجل في النظام.';
            }

            if (code === codes.MANY_FACES) {
                return 'يجب أن يظهر وجه واحد فقط أمام الكاميرا.';
            }

            if (code === codes.PAD_ATTACK) {
                return 'تم رفض المحاولة بسبب اكتشاف صورة أو عرض غير حي.';
            }

            if (code === codes.NO_FACES_DETECTED) {
                return 'لم يتم اكتشاف وجه واضح.';
            }

            if (code === codes.NETWORK_IO) {
                return 'تعذر الاتصال بخدمة التحقق.';
            }

            if (code === codes.ABORTED_BY_USER) {
                return 'تم إلغاء العملية.';
            }

            return 'تعذر التحقق من الوجه. رمز الخطأ: '
                + String(code ?? 'غير معروف');
        };

        const postPunch = async (
            facialId,
            attempt = 0
        ) => {
            const response = await fetch(
                punchUrl,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type':
                            'application/json',
                        'Accept':
                            'application/json',
                        'X-CSRF-TOKEN':
                            csrf,
                        'X-Requested-With':
                            'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        facial_id:
                            facialId,
                        location_id:
                            locationId
                    })
                }
            );

            const data =
                await response.json();

            if (
                response.status === 422
                && data?.errors
                    ?.face_confirmation
                && attempt < 8
            ) {
                await new Promise(
                    resolve =>
                        setTimeout(
                            resolve,
                            700
                        )
                );

                return postPunch(
                    facialId,
                    attempt + 1
                );
            }

            if (!response.ok) {
                const firstError =
                    data?.errors
                        ? Object
                            .values(
                                data.errors
                            )
                            .flat()[0]
                        : data?.message;

                throw new Error(
                    firstError
                    || 'تعذر تسجيل الحضور.'
                );
            }

            return data;
        };

        button?.addEventListener(
            'click',
            async function () {
                if (!window.isSecureContext) {
                    return;
                }

                if (
                    !publicId
                    || typeof window.faceIO
                        !== 'function'
                ) {
                    showResult(
                        '<strong>FACEIO غير مهيأ</strong>'
                        + '<span>تحقق من Public ID وتحميل المكتبة.</span>',
                        'error'
                    );

                    return;
                }

                button.disabled = true;
                button.textContent =
                    'جاري التحقق...';

                showResult(
                    '<strong>افتح الكاميرا</strong>'
                    + '<span>اتبع التعليمات التي تظهر على الشاشة.</span>',
                    'warning'
                );

                try {
                    const faceio =
                        new faceIO(
                            publicId
                        );

                    const userData =
                        await faceio
                            .authenticate({
                                locale:'auto'
                            });

                    const data =
                        await postPunch(
                            userData.facialId
                        );

                    const actionLabel =
                        data.action
                        === 'check_in'
                            ? 'تم تسجيل الحضور'
                            : 'تم تسجيل الانصراف';

                    const time =
                        data.action
                        === 'check_in'
                            ? data.attendance
                                .check_in_at
                            : data.attendance
                                .check_out_at;

                    showResult(
                        '<strong>✓ '
                        + escapeHtml(
                            actionLabel
                        )
                        + '</strong>'
                        + '<span>'
                        + escapeHtml(
                            data.employee.name
                        )
                        + ' · '
                        + escapeHtml(
                            time || ''
                        )
                        + (
                            data.attendance.shift
                                ? ' · '
                                    + escapeHtml(
                                        data.attendance.shift
                                    )
                                : ''
                        )
                        + '</span>',
                        'success'
                    );

                    setTimeout(
                        () =>
                            window
                                .location
                                .reload(),
                        2800
                    );
                } catch (error) {
                    const message =
                        error instanceof Error
                            ? error.message
                            : errorMessage(
                                error
                            );

                    showResult(
                        '<strong>لم يتم التسجيل</strong>'
                        + '<span>'
                        + escapeHtml(
                            message
                        )
                        + '</span>',
                        'error'
                    );

                    button.disabled = false;
                    button.textContent =
                        'إعادة المحاولة';
                }
            }
        );
    });
    </script>
</body>
</html>
