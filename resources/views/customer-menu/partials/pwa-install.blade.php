{{-- Cross-platform PWA install helper. --}}
<button type="button" class="pwa-install-btn" id="pwaInstallBtn" hidden>
    <i class="fa-solid fa-mobile-screen-button"></i>
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
.pwa-install-btn{position:fixed;z-index:145;left:16px;bottom:calc(84px + env(safe-area-inset-bottom,0px));border:0;border-radius:16px;background:var(--primary);color:#fff;padding:11px 15px;display:inline-flex;align-items:center;gap:9px;font-weight:900;font-size:.78rem;box-shadow:0 12px 28px color-mix(in srgb,var(--primary) 36%,transparent);animation:pwaPulse 2.8s ease-in-out infinite}.pwa-install-btn[hidden]{display:none!important}.pwa-install-btn i{font-size:1rem}@keyframes pwaPulse{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
.pwa-install-sheet{position:fixed;inset:0;z-index:280;background:rgba(12,10,9,.52);display:none;align-items:flex-end;justify-content:center;padding:14px}.pwa-install-sheet.open{display:flex}.pwa-install-card{position:relative;width:min(440px,100%);background:var(--surface,#fff);color:var(--text,#17130f);border:1px solid var(--line,#eee);border-radius:24px;padding:22px 19px calc(20px + env(safe-area-inset-bottom,0px));box-shadow:0 25px 70px rgba(0,0,0,.22);direction:rtl}.pwa-install-close{position:absolute;top:11px;left:11px;width:34px;height:34px;border:0;border-radius:50%;background:var(--bg,#f5f5f5);color:var(--text,#222)}.pwa-install-mark{width:52px;height:52px;border-radius:16px;background:color-mix(in srgb,var(--primary) 10%,var(--surface,#fff));color:var(--primary);display:grid;place-items:center;font-size:1.3rem;margin-bottom:11px}.pwa-install-card h3{margin:0 0 6px;font-size:1.08rem;font-weight:900}.pwa-install-card p{margin:0 0 13px;color:var(--muted,#777);font-size:.84rem;line-height:1.6}.pwa-install-card ol{margin:0;padding:0 20px 0 0}.pwa-install-card li{margin:8px 0;font-size:.84rem;line-height:1.6;font-weight:700}
</style>

<script>
(function(){
 const standalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
 const btn=document.getElementById('pwaInstallBtn'),label=document.getElementById('pwaInstallLabel'),sheet=document.getElementById('pwaInstallSheet'),title=document.getElementById('pwaInstallTitle'),intro=document.getElementById('pwaInstallIntro'),steps=document.getElementById('pwaInstallSteps');
 if(!btn||standalone)return;
 const ua=navigator.userAgent||'',vendor=navigator.vendor||'',platform=navigator.platform||'';
 const appleVendor=/Apple/i.test(vendor);
 const isiPhoneUa=/iPhone|iPad|iPod/i.test(ua);
 const isIPadDesktop=platform==='MacIntel'&&navigator.maxTouchPoints>1;
 const isRealIos=appleVendor&&(isiPhoneUa||isIPadDesktop);
 const isSafari=isRealIos&&/^((?!CriOS|FxiOS|EdgiOS).)*Safari/i.test(ua);
 const isAndroid=/Android/i.test(ua);
 let deferredPrompt=null;
 if('serviceWorker'in navigator)navigator.serviceWorker.register('/sw.js').catch(()=>{});
 function show(kind){const d=kind==='ios'?{title:'تثبيت التطبيق على iPhone / iPad',intro:isSafari?'ثبّت المنيو على الشاشة الرئيسية للوصول السريع والطلب مباشرة.':'افتح الصفحة في Safari أولًا ثم ثبّتها.',steps:isSafari?['اضغط زر المشاركة ⤴︎ في Safari.','اختر «إضافة إلى الشاشة الرئيسية».','اضغط «إضافة».']:['افتح الرابط في Safari.','اضغط المشاركة ⤴︎.','اختر «إضافة إلى الشاشة الرئيسية».']}:{title:'تثبيت التطبيق',intro:'ثبّت المنيو كتطبيق على جهازك للوصول السريع.',steps:['اضغط زر التثبيت في المتصفح.','وافق على تثبيت التطبيق.']};title.textContent=d.title;intro.textContent=d.intro;steps.innerHTML=d.steps.map(x=>`<li>${x}</li>`).join('');sheet.classList.add('open');sheet.setAttribute('aria-hidden','false')}
 window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();deferredPrompt=e;label.textContent=isAndroid?'ثبّت التطبيق':'تثبيت التطبيق';btn.hidden=false});
 if(isRealIos){label.textContent='تثبيت على iPhone';btn.hidden=false}
 window.addEventListener('appinstalled',()=>btn.hidden=true);
 btn.addEventListener('click',async()=>{if(deferredPrompt){deferredPrompt.prompt();await deferredPrompt.userChoice;deferredPrompt=null;btn.hidden=true;return}if(isRealIos)show('ios')});
 const close=()=>{sheet.classList.remove('open');sheet.setAttribute('aria-hidden','true')};document.getElementById('pwaInstallClose')?.addEventListener('click',close);sheet?.addEventListener('click',e=>{if(e.target===sheet)close()});document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
})();
</script>
