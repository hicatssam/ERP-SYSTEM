{{-- Cross-platform PWA install helper. Detects iOS, Android and desktop/web. --}}
<button type="button" class="pwa-install-btn" id="pwaInstallBtn" hidden>
    <i class="fa-solid fa-arrow-down-to-bracket"></i>
    <span id="pwaInstallLabel">ثبّت التطبيق</span>
</button>

<div class="pwa-install-sheet" id="pwaInstallSheet" aria-hidden="true">
    <div class="pwa-install-card" role="dialog" aria-modal="true" aria-labelledby="pwaInstallTitle">
        <button type="button" class="pwa-install-close" id="pwaInstallClose" aria-label="إغلاق"><i class="fa-solid fa-xmark"></i></button>
        <div class="pwa-install-mark"><i class="fa-solid fa-mobile-screen-button"></i></div>
        <h3 id="pwaInstallTitle">ثبّت التطبيق</h3>
        <p id="pwaInstallIntro">أضف المنيو إلى جهازك للوصول السريع إليه.</p>
        <ol id="pwaInstallSteps"></ol>
    </div>
</div>

<style>
.pwa-install-btn{position:fixed;z-index:140;left:16px;bottom:calc(82px + env(safe-area-inset-bottom,0px));border:0;border-radius:999px;background:var(--primary);color:var(--on-primary,#fff);padding:10px 14px;display:inline-flex;align-items:center;gap:8px;font-weight:900;font-size:.76rem;box-shadow:0 10px 26px color-mix(in srgb,var(--primary) 34%,transparent)}
.pwa-install-btn[hidden]{display:none!important}
.pwa-install-sheet{position:fixed;inset:0;z-index:260;background:rgba(12,10,9,.52);display:none;align-items:flex-end;justify-content:center;padding:16px}
.pwa-install-sheet.open{display:flex}
.pwa-install-card{position:relative;width:min(520px,100%);background:var(--surface,#fff);color:var(--text,#17130f);border:1px solid var(--line,#eee);border-radius:24px;padding:24px 20px calc(22px + env(safe-area-inset-bottom,0px));box-shadow:0 25px 70px rgba(0,0,0,.22);direction:rtl}
.pwa-install-close{position:absolute;top:12px;left:12px;width:36px;height:36px;border:0;border-radius:50%;background:var(--bg,#f5f5f5);color:var(--text,#222)}
.pwa-install-mark{width:54px;height:54px;border-radius:17px;background:color-mix(in srgb,var(--primary) 10%,var(--surface,#fff));color:var(--primary);display:grid;place-items:center;font-size:1.35rem;margin-bottom:12px}
.pwa-install-card h3{margin:0 0 6px;font-size:1.1rem;font-weight:900}.pwa-install-card p{margin:0 0 14px;color:var(--muted,#777);font-size:.86rem;line-height:1.6}.pwa-install-card ol{margin:0;padding:0 20px 0 0}.pwa-install-card li{margin:9px 0;font-size:.86rem;line-height:1.65;font-weight:700}
</style>

<script>
(function () {
    const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const btn = document.getElementById('pwaInstallBtn');
    const label = document.getElementById('pwaInstallLabel');
    const sheet = document.getElementById('pwaInstallSheet');
    const title = document.getElementById('pwaInstallTitle');
    const intro = document.getElementById('pwaInstallIntro');
    const steps = document.getElementById('pwaInstallSteps');
    if (!btn || standalone) return;

    const ua = navigator.userAgent || '';
    const isIos = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isAndroid = /android/i.test(ua);
    const isSafari = /^((?!chrome|android|crios|fxios).)*safari/i.test(ua);
    let deferredPrompt = null;

    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});

    function showInstructions(kind) {
        const data = kind === 'ios' ? {
            title: 'تثبيت التطبيق على iPhone / iPad',
            intro: isSafari ? 'Safari يحتاج خطوتين فقط لإضافة المنيو إلى الشاشة الرئيسية.' : 'على iPhone افتح هذه الصفحة في Safari أولًا ثم أضفها إلى الشاشة الرئيسية.',
            steps: isSafari
                ? ['اضغط زر المشاركة ⤴︎ في Safari.', 'مرّر للأسفل واختر «إضافة إلى الشاشة الرئيسية».', 'اضغط «إضافة» وسيظهر التطبيق بين تطبيقاتك.']
                : ['افتح هذه الصفحة في Safari.', 'اضغط زر المشاركة ⤴︎.', 'اختر «إضافة إلى الشاشة الرئيسية» ثم «إضافة».']
        } : kind === 'android' ? {
            title: 'تثبيت التطبيق على Android',
            intro: 'يمكن تثبيت المنيو كتطبيق مباشرة من Chrome أو المتصفح المدعوم.',
            steps: ['افتح قائمة المتصفح ⋮.', 'اختر «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية».', 'وافق على التثبيت.']
        } : {
            title: 'تثبيت التطبيق على الكمبيوتر',
            intro: 'يمكن تثبيت المنيو كتطبيق مستقل من Chrome أو Edge.',
            steps: ['افتح قائمة المتصفح.', 'اختر «تثبيت التطبيق» أو أيقونة التثبيت بجانب شريط العنوان.', 'أكد التثبيت.']
        };
        title.textContent = data.title;
        intro.textContent = data.intro;
        steps.innerHTML = data.steps.map(item => `<li>${item}</li>`).join('');
        sheet.classList.add('open');
        sheet.setAttribute('aria-hidden','false');
    }

    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        deferredPrompt = event;
        label.textContent = isAndroid ? 'ثبّت التطبيق' : 'تثبيت على الجهاز';
        btn.hidden = false;
    });

    if (isIos) {
        label.textContent = 'ثبّت على iPhone';
        btn.hidden = false;
    } else {
        // Keep a help/install entry visible even when the browser does not
        // expose beforeinstallprompt yet (e.g. desktop Safari or unsupported web).
        setTimeout(() => { if (!deferredPrompt) btn.hidden = false; }, 900);
    }

    window.addEventListener('appinstalled', () => { btn.hidden = true; });

    btn.addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            btn.hidden = true;
            return;
        }
        showInstructions(isIos ? 'ios' : (isAndroid ? 'android' : 'web'));
    });

    function closeSheet(){ sheet.classList.remove('open'); sheet.setAttribute('aria-hidden','true'); }
    document.getElementById('pwaInstallClose')?.addEventListener('click', closeSheet);
    sheet?.addEventListener('click', event => { if (event.target === sheet) closeSheet(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeSheet(); });
})();
</script>
