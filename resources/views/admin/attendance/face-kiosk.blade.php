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
        html,body{width:100%;min-height:100%;margin:0}

        body{
            min-height:100vh;
            display:grid;
            place-items:center;
            padding:clamp(14px,3vw,30px);
            background:
                radial-gradient(circle at 85% 8%,rgba(182,139,50,.12),transparent 28%),
                radial-gradient(circle at 8% 92%,rgba(17,24,39,.07),transparent 30%),
                #f3f4f6;
            color:var(--ink);
            font-family:"Cairo","Segoe UI",Tahoma,Arial,sans-serif;
        }

        button,select{font:inherit}

        .kiosk-shell{width:min(860px,100%)}

        .kiosk-brand{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:.65rem;
            margin-bottom:1rem;
        }

        .kiosk-brand-mark{
            width:44px;height:44px;display:grid;place-items:center;
            border-radius:14px;background:#15191f;color:#d9aa43;
            font-size:1rem;font-weight:900;
            box-shadow:0 8px 24px rgba(15,23,42,.12);
        }

        .kiosk-brand-copy strong{display:block;font-size:.95rem}
        .kiosk-brand-copy span{display:block;margin-top:.08rem;color:var(--muted);font-size:.67rem}

        .kiosk-card{
            position:relative;overflow:hidden;
            padding:clamp(20px,4vw,36px);
            border:1px solid var(--border);
            border-radius:28px;
            background:rgba(255,255,255,.97);
            box-shadow:0 24px 70px rgba(15,23,42,.12);
        }

        .kiosk-top{
            display:flex;
            justify-content:space-between;
            gap:1rem;
            align-items:center;
            margin-bottom:1rem;
        }

        .kiosk-location{
            display:inline-flex;align-items:center;gap:.45rem;
            min-height:34px;padding:.35rem .75rem;
            border:1px solid var(--border);border-radius:999px;
            background:var(--soft);font-size:.72rem;font-weight:800;
        }

        .kiosk-dot{
            width:8px;height:8px;border-radius:50%;
            background:var(--success);
            box-shadow:0 0 0 4px rgba(22,132,91,.10);
        }

        .kiosk-clock{color:var(--muted);font-size:.68rem}

        .kiosk-select{max-width:390px;margin:0 auto 1rem;text-align:right}
        .kiosk-select label{display:block;margin-bottom:.35rem;color:var(--muted);font-size:.67rem;font-weight:800}
        .kiosk-select select{width:100%;min-height:42px;padding:0 .8rem;border:1px solid var(--border);border-radius:11px;background:#fff}

        .kiosk-grid{
            display:grid;
            grid-template-columns:minmax(0,1.3fr) minmax(260px,.7fr);
            gap:1rem;
            align-items:stretch;
        }

        .kiosk-camera{
            position:relative;
            overflow:hidden;
            min-height:420px;
            border-radius:20px;
            background:#111827;
            border:1px solid var(--border);
        }

        .kiosk-camera video{
            width:100%;
            height:100%;
            min-height:420px;
            object-fit:cover;
            transform:scaleX(-1);
        }

        .kiosk-camera-placeholder{
            position:absolute;inset:0;display:grid;place-items:center;
            color:#cbd5e1;text-align:center;padding:1.2rem;font-size:.78rem;
        }

        .kiosk-camera.live .kiosk-camera-placeholder{display:none}

        .kiosk-side{
            display:flex;
            flex-direction:column;
            justify-content:center;
            padding:1.15rem;
            border:1px solid var(--border);
            border-radius:18px;
            background:var(--soft);
        }

        .kiosk-step-number{
            display:inline-grid;place-items:center;
            width:34px;height:34px;border-radius:11px;
            background:rgba(182,139,50,.12);
            color:var(--gold-dark);
            font-weight:900;
            font-size:.8rem;
        }

        .kiosk-side h1{
            margin:.75rem 0 0;
            font-size:1.18rem;
            line-height:1.45;
        }

        .kiosk-side p{
            margin:.45rem 0 0;
            color:var(--muted);
            font-size:.72rem;
            line-height:1.75;
        }

        .kiosk-progress{
            display:flex;
            gap:.35rem;
            margin-top:1rem;
        }

        .kiosk-progress i{
            display:block;flex:1;height:6px;border-radius:999px;background:var(--border);
        }

        .kiosk-progress i.done{background:var(--success)}

        .kiosk-action{
            width:100%;
            min-height:48px;
            margin-top:1rem;
            border:0;border-radius:13px;
            background:linear-gradient(135deg,#c99d3d,#a8791d);
            color:#fff;font-size:.82rem;font-weight:900;cursor:pointer;
            box-shadow:0 10px 24px rgba(182,139,50,.22);
        }

        .kiosk-action.secondary{
            background:#fff;color:var(--ink);
            border:1px solid var(--border);
            box-shadow:none;
        }

        .kiosk-action:disabled{opacity:.55;cursor:not-allowed;box-shadow:none}

        .kiosk-result{
            display:none;
            margin-top:1rem;
            padding:.9rem 1rem;
            border-radius:14px;
            font-size:.72rem;
            line-height:1.7;
        }

        .kiosk-result.show{display:block}
        .kiosk-result.success{border:1px solid rgba(22,132,91,.24);background:rgba(22,132,91,.07)}
        .kiosk-result.error{border:1px solid rgba(180,35,24,.24);background:rgba(180,35,24,.06)}
        .kiosk-result.warning{border:1px solid rgba(183,121,31,.24);background:rgba(183,121,31,.07)}
        .kiosk-result strong{display:block;font-size:.9rem}
        .kiosk-result span{display:block;margin-top:.2rem;color:var(--muted)}

        .kiosk-secure-warning{
            display:none;margin-top:1rem;padding:.8rem .9rem;
            border:1px solid rgba(180,35,24,.24);border-radius:12px;
            background:rgba(180,35,24,.06);color:var(--danger);
            font-size:.69rem;line-height:1.7;
        }

        .kiosk-secure-warning.show{display:block}

        .kiosk-note{
            margin-top:1rem;
            text-align:center;
            color:var(--muted);
            font-size:.62rem;
        }

        @media(max-width:760px){
            body{padding:10px}
            .kiosk-card{padding:16px;border-radius:20px}
            .kiosk-top{align-items:flex-start;flex-direction:column}
            .kiosk-grid{grid-template-columns:1fr}
            .kiosk-camera,.kiosk-camera video{min-height:330px}
        }
    </style>
</head>

<body>
    <main class="kiosk-shell">
        <div class="kiosk-brand">
            <div class="kiosk-brand-mark">دهب</div>
            <div class="kiosk-brand-copy">
                <strong>نظام الحضور</strong>
                <span>CompreFace محلي</span>
            </div>
        </div>

        <section class="kiosk-card">
            <div class="kiosk-top">
                <div class="kiosk-location">
                    <span class="kiosk-dot"></span>
                    {{ $location->name }}
                </div>

                <div class="kiosk-clock" id="kioskClock"></div>
            </div>

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

            <div class="kiosk-grid">
                <div class="kiosk-camera" id="kioskCamera">
                    <video
                        id="kioskVideo"
                        autoplay
                        muted
                        playsinline
                    ></video>

                    <div class="kiosk-camera-placeholder">
                        اضغط "بدء التحقق" لفتح الكاميرا.
                    </div>
                </div>

                <div class="kiosk-side">
                    <span class="kiosk-step-number" id="stepNumber">1</span>

                    <h1 id="stepTitle">
                        جاهز لتسجيل الحضور
                    </h1>

                    <p id="stepDescription">
                        سنلتقط صورة أمامية، ثم نطلب منك لف رأسك إلى أحد الجانبين للتحقق من الحركة.
                    </p>

                    <div class="kiosk-progress">
                        <i id="progress1"></i>
                        <i id="progress2"></i>
                        <i id="progress3"></i>
                    </div>

                    <button
                        class="kiosk-action"
                        type="button"
                        id="kioskAction"
                    >
                        بدء التحقق
                    </button>

                    <button
                        class="kiosk-action secondary"
                        type="button"
                        id="kioskReset"
                        style="display:none"
                    >
                        إلغاء المحاولة
                    </button>
                </div>
            </div>

            <div
                class="kiosk-secure-warning"
                id="secureContextWarning"
            >
                الكاميرا على أجهزة الشبكة تحتاج HTTPS.
                يمكنك التجربة على localhost من نفس الجهاز.
            </div>

            <div
                class="kiosk-result"
                id="kioskResult"
                aria-live="polite"
            ></div>

            <div class="kiosk-note">
                حد المطابقة الحالي:
                {{ number_format((float) $similarityThreshold * 100, 0) }}%
                · التحقق يتضمن حركة الرأس عبر CompreFace pose.
            </div>
        </section>
    </main>

    <canvas id="kioskCanvas" hidden></canvas>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const video = document.getElementById('kioskVideo');
        const canvas = document.getElementById('kioskCanvas');
        const camera = document.getElementById('kioskCamera');
        const actionButton = document.getElementById('kioskAction');
        const resetButton = document.getElementById('kioskReset');
        const result = document.getElementById('kioskResult');
        const secureWarning = document.getElementById('secureContextWarning');
        const stepNumber = document.getElementById('stepNumber');
        const stepTitle = document.getElementById('stepTitle');
        const stepDescription = document.getElementById('stepDescription');
        const clock = document.getElementById('kioskClock');

        const csrf = @json(csrf_token());
        const challengeUrl = @json(route('attendance.face.challenge'));
        const punchUrl = @json(route('attendance.face.punch'));
        const locationId = {{ (int) $location->id }};

        let stream = null;
        let challengeToken = null;
        let frontImage = null;
        let turnedImage = null;
        let phase = 'idle';

        const refreshClock = () => {
            clock.textContent = new Intl.DateTimeFormat(
                'ar',
                {
                    dateStyle: 'full',
                    timeStyle: 'medium'
                }
            ).format(new Date());
        };

        refreshClock();
        setInterval(refreshClock, 1000);

        const showResult = (title, message, type = 'warning') => {
            result.className = 'kiosk-result show ' + type;
            result.innerHTML =
                '<strong>' + escapeHtml(title) + '</strong>'
                + '<span>' + escapeHtml(message) + '</span>';
        };

        const escapeHtml = value => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const setProgress = count => {
            for (let index = 1; index <= 3; index++) {
                document.getElementById('progress' + index)
                    ?.classList.toggle('done', count >= index);
            }
        };

        const stopCamera = () => {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }

            stream = null;
            video.srcObject = null;
            camera.classList.remove('live');
        };

        const reset = () => {
            stopCamera();
            challengeToken = null;
            frontImage = null;
            turnedImage = null;
            phase = 'idle';
            stepNumber.textContent = '1';
            stepTitle.textContent = 'جاهز لتسجيل الحضور';
            stepDescription.textContent =
                'سنلتقط صورة أمامية، ثم نطلب منك لف رأسك إلى أحد الجانبين للتحقق من الحركة.';
            actionButton.disabled = false;
            actionButton.textContent = 'بدء التحقق';
            resetButton.style.display = 'none';
            setProgress(0);
        };

        const startCamera = async () => {
            if (!window.isSecureContext) {
                secureWarning.classList.add('show');
                throw new Error(
                    'تشغيل الكاميرا يحتاج HTTPS أو localhost.'
                );
            }

            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            });

            video.srcObject = stream;
            camera.classList.add('live');

            await new Promise(resolve => {
                if (video.readyState >= 2) {
                    resolve();
                    return;
                }

                video.onloadeddata = () => resolve();
            });
        };

        const captureFrame = () => {
            const width = Math.min(720, video.videoWidth);
            const ratio = width / video.videoWidth;
            const height = Math.round(video.videoHeight * ratio);

            canvas.width = width;
            canvas.height = height;

            canvas.getContext('2d').drawImage(
                video,
                0,
                0,
                width,
                height
            );

            return canvas.toDataURL(
                'image/jpeg',
                0.88
            );
        };

        const requestChallenge = async () => {
            const response = await fetch(
                challengeUrl,
                {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(
                    data?.message
                    || 'تعذر بدء جلسة التحقق.'
                );
            }

            return data.token;
        };

        const submitPunch = async () => {
            const response = await fetch(
                punchUrl,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        front_image: frontImage,
                        turned_image: turnedImage,
                        challenge_token: challengeToken,
                        location_id: locationId
                    })
                }
            );

            const data = await response.json();

            if (!response.ok) {
                const errorMessage = data?.errors
                    ? Object.values(data.errors).flat()[0]
                    : data?.message;

                throw new Error(
                    errorMessage
                    || 'تعذر تسجيل الحضور.'
                );
            }

            return data;
        };

        actionButton.addEventListener('click', async function () {
            try {
                if (phase === 'idle') {
                    actionButton.disabled = true;
                    actionButton.textContent = 'جاري فتح الكاميرا...';

                    challengeToken = await requestChallenge();
                    await startCamera();

                    phase = 'front';
                    stepNumber.textContent = '1';
                    stepTitle.textContent = 'انظر مباشرة إلى الكاميرا';
                    stepDescription.textContent =
                        'ثبت وجهك داخل الإطار واضغط التقاط اللقطة الأمامية.';
                    actionButton.disabled = false;
                    actionButton.textContent = 'التقاط اللقطة الأمامية';
                    resetButton.style.display = 'block';
                    setProgress(0);
                    return;
                }

                if (phase === 'front') {
                    frontImage = captureFrame();
                    phase = 'turn';

                    stepNumber.textContent = '2';
                    stepTitle.textContent = 'لف رأسك إلى أحد الجانبين';
                    stepDescription.textContent =
                        'لف رأسك بوضوح يمينًا أو يسارًا مع بقاء الوجه ظاهرًا، ثم التقط الصورة.';
                    actionButton.textContent = 'التقاط حركة الرأس';
                    setProgress(1);

                    showResult(
                        'تمت اللقطة الأولى',
                        'الآن لف رأسك بوضوح إلى أحد الجانبين.',
                        'success'
                    );
                    return;
                }

                if (phase === 'turn') {
                    turnedImage = captureFrame();
                    phase = 'submit';

                    stepNumber.textContent = '3';
                    stepTitle.textContent = 'جاري التحقق';
                    stepDescription.textContent =
                        'يتم الآن التحقق من تطابق الوجه وحركة الرأس.';
                    actionButton.disabled = true;
                    actionButton.textContent = 'جاري التحقق...';
                    setProgress(2);

                    const data = await submitPunch();

                    stopCamera();
                    setProgress(3);

                    const actionLabel = data.action === 'check_in'
                        ? 'تم تسجيل الحضور'
                        : 'تم تسجيل الانصراف';

                    const time = data.action === 'check_in'
                        ? data.attendance.check_in_at
                        : data.attendance.check_out_at;

                    const similarity = data?.verification?.similarity
                        ? Math.round(data.verification.similarity * 100)
                        : null;

                    stepTitle.textContent = actionLabel;
                    stepDescription.textContent =
                        data.employee.name
                        + ' · '
                        + (time || '');

                    showResult(
                        '✓ ' + actionLabel,
                        data.employee.name
                        + ' · '
                        + (time || '')
                        + (similarity ? ' · مطابقة ' + similarity + '%' : ''),
                        'success'
                    );

                    setTimeout(reset, 3500);
                }
            } catch (error) {
                showResult(
                    'لم يتم التسجيل',
                    error.message || 'حدث خطأ أثناء التحقق.',
                    'error'
                );

                actionButton.disabled = false;
                actionButton.textContent =
                    phase === 'idle'
                        ? 'بدء التحقق'
                        : 'إعادة المحاولة';

                if (phase === 'submit') {
                    reset();
                }
            }
        });

        resetButton.addEventListener('click', reset);
        window.addEventListener('beforeunload', stopCamera);

        if (!window.isSecureContext) {
            secureWarning.classList.add('show');
        }
    });
    </script>
</body>
</html>
