{{--
    One button, two paths:
    - Chrome/Edge (Android + desktop): real native install prompt via
      `beforeinstallprompt`.
    - iOS Safari: that event doesn't exist at all, so we show a small sheet
      with the manual "Share → Add to Home Screen" steps instead.
--}}
<button type="button" class="pwa-install-btn" id="pwaInstallBtn" hidden>
    <i class="fa-solid fa-arrow-down-to-bracket"></i>
    <span>ثبّت التطبيق</span>
</button>

<div class="pwa-ios-sheet" id="pwaIosSheet">
    <div class="pwa-ios-card">
        <button type="button" class="pwa-ios-close" id="pwaIosClose" aria-label="إغلاق"><i class="fa-solid fa-xmark"></i></button>
        <h3>ثبّت التطبيق على آيفون</h3>
        <ol>
            <li>اضغط زر المشاركة <i class="fa-solid fa-arrow-up-from-bracket"></i> بأسفل المتصفح</li>
            <li>اختر "إضافة إلى الشاشة الرئيسية"</li>
        </ol>
    </div>
</div>

<script>
(function () {
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const btn = document.getElementById('pwaInstallBtn');

    if (isStandalone || !btn) return;

    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
    let deferredPrompt = null;

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    window.addEventListener('beforeinstallprompt', e => {
        e.preventDefault();
        deferredPrompt = e;
        btn.hidden = false;
    });

    window.addEventListener('appinstalled', () => { btn.hidden = true; });

    // iOS never fires beforeinstallprompt, so offer the manual-steps
    // sheet unconditionally there instead of waiting for an event that
    // will never come.
    if (isIos) btn.hidden = false;

    btn.addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            btn.hidden = true;
            return;
        }

        if (isIos) {
            document.getElementById('pwaIosSheet').classList.add('open');
        }
    });

    document.getElementById('pwaIosClose')?.addEventListener('click', () => {
        document.getElementById('pwaIosSheet').classList.remove('open');
    });
})();
</script>
