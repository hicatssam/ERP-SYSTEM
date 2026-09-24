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
.face-status.revoked .face-dot{background:var(--theme-danger)}
.face-camera-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(260px,.75fr);gap:1rem;margin-top:1rem}
.face-preview{position:relative;overflow:hidden;aspect-ratio:4/3;border-radius:16px;background:#111827;border:1px solid var(--border)}
.face-preview video{width:100%;height:100%;object-fit:cover;transform:scaleX(-1)}
.face-preview-placeholder{position:absolute;inset:0;display:grid;place-items:center;color:#cbd5e1;font-size:.75rem;text-align:center;padding:1rem}
.face-preview.live .face-preview-placeholder{display:none}
.face-guide{padding:1rem;border:1px solid var(--border);border-radius:14px;background:var(--off-white)}
.face-guide h3{margin:0;font-size:.92rem}
.face-step{margin-top:.8rem;padding:.8rem;border:1px solid var(--border);border-radius:12px;background:var(--surface)}
.face-step strong{display:block;font-size:.78rem}.face-step span{display:block;margin-top:.22rem;color:var(--text-muted);font-size:.68rem;line-height:1.6}
.face-progress{display:flex;gap:.35rem;margin-top:.8rem}
.face-progress i{display:block;height:6px;flex:1;border-radius:999px;background:var(--border)}
.face-progress i.done{background:var(--theme-success)}
.face-consent{display:flex;align-items:flex-start;gap:.55rem;margin-top:1rem;padding:.8rem;border:1px solid var(--border);border-radius:12px;background:var(--surface);font-size:.72rem;line-height:1.6}
.face-actions{display:flex;gap:.55rem;flex-wrap:wrap;margin-top:1rem}
.face-result{display:none;margin-top:1rem;padding:.8rem .9rem;border-radius:12px;font-size:.74rem;line-height:1.7}
.face-result.show{display:block}.face-result.success{background:color-mix(in srgb,var(--theme-success) 8%,var(--surface));border:1px solid color-mix(in srgb,var(--theme-success) 25%,var(--border));color:var(--theme-success)}
.face-result.warning{background:color-mix(in srgb,var(--theme-warning) 8%,var(--surface));border:1px solid color-mix(in srgb,var(--theme-warning) 25%,var(--border))}
.face-result.error{background:color-mix(in srgb,var(--theme-danger) 7%,var(--surface));border:1px solid color-mix(in srgb,var(--theme-danger) 25%,var(--border));color:var(--theme-danger)}
.face-security{margin-top:1rem;padding:.85rem;border-radius:12px;background:color-mix(in srgb,var(--theme-info) 6%,var(--surface));border:1px solid color-mix(in srgb,var(--theme-info) 18%,var(--border));font-size:.7rem;line-height:1.75;color:var(--text-muted)}
@media(max-width:760px){.face-head{flex-direction:column}.face-camera-grid{grid-template-columns:1fr}.face-actions .btn{flex:1}}
</style>

@php
    $profileStatus = $faceProfile?->status;
    $statusClass = $profileStatus === 'active'
        ? 'active'
        : ($profileStatus === 'revoked' ? 'revoked' : '');

    $statusLabel = match($profileStatus) {
        'active' => 'بصمة الوجه مفعلة عبر CompreFace',
        'revoked' => 'بصمة الوجه ملغاة',
        default => 'لم يتم تسجيل الوجه بعد',
    };

    $canEnroll = !$faceProfile
        || $profileStatus === 'revoked';
@endphp

<div class="face-page">
    <div class="face-head">
        <div>
            <h1 class="page-heading">تسجيل بصمة الوجه</h1>
            <p class="page-subheading">
                تسجيل وجه الموظف محليًا داخل CompreFace دون إرسال البيانات إلى بوابة سحابية مدفوعة.
            </p>
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

            <div class="face-status {{ $statusClass }}">
                <span class="face-dot"></span>
                <strong>{{ $statusLabel }}</strong>
            </div>

            @if($canEnroll)
                <div class="face-camera-grid">
                    <div class="face-preview" id="facePreview">
                        <video
                            id="faceVideo"
                            autoplay
                            muted
                            playsinline
                        ></video>

                        <div class="face-preview-placeholder">
                            افتح الكاميرا ثم التقط ثلاث زوايا واضحة للموظف.
                        </div>
                    </div>

                    <div class="face-guide">
                        <h3>خطوات التسجيل</h3>

                        <div class="face-step">
                            <strong id="captureTitle">
                                1. انظر مباشرة إلى الكاميرا
                            </strong>

                            <span id="captureDescription">
                                اجعل الوجه كاملًا وواضحًا وفي إضاءة جيدة.
                            </span>
                        </div>

                        <div class="face-progress">
                            <i id="captureProgress1"></i>
                            <i id="captureProgress2"></i>
                            <i id="captureProgress3"></i>
                        </div>

                        <div class="face-actions">
                            <button
                                type="button"
                                class="btn btn-outline"
                                id="startCameraButton"
                            >
                                فتح الكاميرا
                            </button>

                            <button
                                type="button"
                                class="btn btn-gold"
                                id="captureButton"
                                disabled
                            >
                                التقاط الصورة
                            </button>
                        </div>
                    </div>
                </div>

                <label class="face-consent">
                    <input type="checkbox" id="faceConsent">
                    <span>
                        أؤكد أن الموظف وافق على استخدام بيانات الوجه لغرض الحضور والانصراف
                        وأن التسجيل يتم بحضوره.
                    </span>
                </label>

                <div class="face-actions">
                    <button
                        type="button"
                        class="btn btn-gold"
                        id="saveFaceButton"
                        disabled
                    >
                        حفظ وتفعيل بصمة الوجه
                    </button>

                    <button
                        type="button"
                        class="btn btn-outline"
                        id="resetCaptureButton"
                        disabled
                    >
                        إعادة التقاط الصور
                    </button>
                </div>
            @else
                <div class="face-security">
                    الوجه مسجل ومفعل بالفعل. لإعادة التسجيل، ألغِ البصمة الحالية أولًا
                    حتى يتم حذف الـSubject وصوره من CompreFace ثم سجّل الوجه من جديد.
                </div>
            @endif

            @if($faceProfile && $profileStatus === 'active')
                <div class="face-actions">
                    <form
                        method="POST"
                        action="{{ route('attendance.face.revoke', $employee) }}"
                        onsubmit="return confirm('سيتم حذف بيانات الوجه من CompreFace. هل تريد المتابعة؟')"
                    >
                        @csrf
                        <button class="btn btn-danger" type="submit">
                            إلغاء وحذف بصمة الوجه
                        </button>
                    </form>
                </div>
            @endif

            <div class="face-result" id="faceResult"></div>

            <div class="face-security">
                النظام لا يحفظ صور الوجه داخل Laravel. الصور ترسل من المتصفح إلى Laravel
                ثم إلى CompreFace المحلي، بينما قاعدة النظام تحتفظ فقط بربط الموظف مع
                Subject مشفر وسجل الموافقة والتدقيق.
            </div>
        </div>
    </div>
</div>

<canvas id="faceCanvas" hidden></canvas>
@endsection

@push('scripts')
@if($canEnroll)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const video = document.getElementById('faceVideo');
    const canvas = document.getElementById('faceCanvas');
    const preview = document.getElementById('facePreview');
    const startButton = document.getElementById('startCameraButton');
    const captureButton = document.getElementById('captureButton');
    const saveButton = document.getElementById('saveFaceButton');
    const resetButton = document.getElementById('resetCaptureButton');
    const consent = document.getElementById('faceConsent');
    const result = document.getElementById('faceResult');
    const title = document.getElementById('captureTitle');
    const description = document.getElementById('captureDescription');

    const csrf = @json(csrf_token());
    const enrollUrl = @json(route('attendance.face.enroll', $employee));

    const steps = [
        {
            title: '1. انظر مباشرة إلى الكاميرا',
            description: 'اجعل الوجه كاملًا وواضحًا وفي إضاءة جيدة.'
        },
        {
            title: '2. لف رأسك قليلًا إلى اليمين',
            description: 'لا تخرج من إطار الكاميرا وحافظ على وضوح الوجه.'
        },
        {
            title: '3. لف رأسك قليلًا إلى اليسار',
            description: 'هذه الزاوية الإضافية تحسن دقة التعرف لاحقًا.'
        }
    ];

    let stream = null;
    let images = [];

    const showResult = (message, type = 'warning') => {
        result.className = 'face-result show ' + type;
        result.textContent = message;
    };

    const updateGuide = () => {
        const index = Math.min(images.length, steps.length - 1);

        title.textContent = images.length >= steps.length
            ? 'اكتملت الصور المطلوبة'
            : steps[index].title;

        description.textContent = images.length >= steps.length
            ? 'راجع الموافقة ثم احفظ بصمة الوجه.'
            : steps[index].description;

        for (let i = 1; i <= 3; i++) {
            document.getElementById('captureProgress' + i)
                ?.classList.toggle('done', images.length >= i);
        }

        captureButton.disabled =
            !stream || images.length >= steps.length;

        saveButton.disabled =
            images.length < steps.length;

        resetButton.disabled =
            images.length === 0;
    };

    const stopCamera = () => {
        if (!stream) return;

        stream.getTracks().forEach(track => track.stop());
        stream = null;
        preview.classList.remove('live');
        startButton.disabled = false;
        startButton.textContent = 'فتح الكاميرا';
        updateGuide();
    };

    const startCamera = async () => {
        if (!window.isSecureContext) {
            showResult(
                'تشغيل الكاميرا يحتاج HTTPS، أو افتح النظام على localhost من نفس الجهاز.',
                'error'
            );
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            });

            video.srcObject = stream;
            preview.classList.add('live');
            startButton.disabled = true;
            startButton.textContent = 'الكاميرا مفتوحة';
            showResult('الكاميرا جاهزة. اتبع خطوات الالتقاط.', 'success');
            updateGuide();
        } catch (error) {
            showResult(
                'تعذر فتح الكاميرا. تحقق من إذن الكاميرا في المتصفح.',
                'error'
            );
        }
    };

    const captureFrame = () => {
        if (!stream || !video.videoWidth) {
            showResult('انتظر حتى تظهر صورة الكاميرا بوضوح.', 'error');
            return;
        }

        const width = Math.min(720, video.videoWidth);
        const ratio = width / video.videoWidth;
        const height = Math.round(video.videoHeight * ratio);

        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d');

        context.drawImage(
            video,
            0,
            0,
            width,
            height
        );

        images.push(
            canvas.toDataURL(
                'image/jpeg',
                0.88
            )
        );

        showResult(
            'تم التقاط الصورة ' + images.length + ' من 3.',
            'success'
        );

        updateGuide();

        if (images.length >= steps.length) {
            stopCamera();
        }
    };

    const resetCapture = () => {
        images = [];
        showResult(
            'تم مسح اللقطات. افتح الكاميرا وابدأ من جديد.',
            'warning'
        );
        updateGuide();
    };

    const saveFace = async () => {
        if (!consent.checked) {
            showResult(
                'يجب تأكيد موافقة الموظف قبل حفظ بصمة الوجه.',
                'error'
            );
            return;
        }

        if (images.length < 3) {
            showResult(
                'يجب التقاط الصور الثلاث أولًا.',
                'error'
            );
            return;
        }

        saveButton.disabled = true;
        saveButton.textContent = 'جاري التسجيل...';

        try {
            const response = await fetch(
                enrollUrl,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        images,
                        consent: true
                    })
                }
            );

            const data = await response.json();

            if (!response.ok) {
                const errorMessage = data?.errors
                    ? Object.values(data.errors).flat()[0]
                    : data?.message;

                throw new Error(
                    errorMessage || 'تعذر تسجيل الوجه.'
                );
            }

            showResult(data.message, 'success');

            setTimeout(
                () => window.location.reload(),
                1200
            );
        } catch (error) {
            showResult(
                error.message || 'تعذر تسجيل الوجه.',
                'error'
            );
            saveButton.disabled = false;
            saveButton.textContent = 'حفظ وتفعيل بصمة الوجه';
        }
    };

    startButton.addEventListener('click', startCamera);
    captureButton.addEventListener('click', captureFrame);
    resetButton.addEventListener('click', resetCapture);
    saveButton.addEventListener('click', saveFace);

    window.addEventListener('beforeunload', stopCamera);

    updateGuide();
});
</script>
@endif
@endpush
