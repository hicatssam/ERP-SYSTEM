{{-- Cross-platform PWA install helper + customer checkout UX enhancements. --}}
<button type="button" class="pwa-install-btn" id="pwaInstallBtn" hidden>
    <i class="fa-solid fa-download"></i>
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
.pwa-install-btn{position:fixed;z-index:145;left:14px;bottom:calc(82px + env(safe-area-inset-bottom,0px));border:0;border-radius:999px;background:var(--primary);color:#fff;padding:10px 14px;display:inline-flex;align-items:center;gap:8px;font-weight:900;font-size:.76rem;box-shadow:0 12px 28px color-mix(in srgb,var(--primary) 34%,transparent);animation:pwaPulse 2.8s ease-in-out infinite}.pwa-install-btn[hidden]{display:none!important}.pwa-install-btn i{font-size:.9rem}@keyframes pwaPulse{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
.pwa-install-sheet{position:fixed;inset:0;z-index:280;background:rgba(12,10,9,.52);display:none;align-items:flex-end;justify-content:center;padding:12px}.pwa-install-sheet.open{display:flex}.pwa-install-card{position:relative;width:min(440px,100%);background:var(--surface,#fff);color:var(--text,#17130f);border:1px solid var(--line,#eee);border-radius:24px;padding:22px 19px calc(20px + env(safe-area-inset-bottom,0px));box-shadow:0 25px 70px rgba(0,0,0,.22);direction:rtl}.pwa-install-close{position:absolute;top:11px;left:11px;width:34px;height:34px;border:0;border-radius:50%;background:var(--bg,#f5f5f5);color:var(--text,#222)}.pwa-install-mark{width:52px;height:52px;border-radius:16px;background:color-mix(in srgb,var(--primary) 10%,var(--surface,#fff));color:var(--primary);display:grid;place-items:center;font-size:1.3rem;margin-bottom:11px}.pwa-install-card h3{margin:0 0 6px;font-size:1.08rem;font-weight:900}.pwa-install-card p{margin:0 0 13px;color:var(--muted,#777);font-size:.84rem;line-height:1.6}.pwa-install-card ol{margin:0;padding:0 20px 0 0}.pwa-install-card li{margin:8px 0;font-size:.84rem;line-height:1.6;font-weight:700}
/* Checkout: compact mobile-first cards with a clear hierarchy. */
#checkoutForm{padding-bottom:18px}#checkoutForm h3{font-size:1rem;margin:20px 0 10px}.order-summary{border-radius:20px!important;border:1px solid var(--line)!important;background:var(--surface)!important;box-shadow:0 8px 26px color-mix(in srgb,var(--text) 5%,transparent);overflow:hidden}.checkout-grid{background:var(--surface);border:1px solid var(--line);border-radius:20px;padding:14px;gap:12px!important}.checkout-grid label{font-size:.76rem;font-weight:800;color:var(--muted)}.checkout-grid input,.checkout-grid select,.checkout-grid textarea{margin-top:6px;min-height:48px;border-radius:13px!important;border:1px solid var(--line)!important;background:var(--surface)!important;padding:0 13px!important;color:var(--text)}.checkout-grid textarea{padding:12px 13px!important;min-height:88px}.payment-methods{display:grid!important;gap:9px!important}.payment-card{min-height:62px!important;border-radius:16px!important;border:1px solid var(--line)!important;background:var(--surface)!important;padding:10px 12px!important;box-shadow:none!important}.payment-card.active{border-color:var(--primary)!important;background:color-mix(in srgb,var(--primary) 5%,var(--surface))!important;box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 12%,transparent)!important}.submit-order{position:sticky;bottom:calc(70px + env(safe-area-inset-bottom,0px));z-index:20;min-height:52px;border-radius:15px!important;box-shadow:0 12px 28px color-mix(in srgb,var(--primary) 24%,transparent)}.eta-banner{border-radius:15px!important;padding:11px 13px!important;margin-bottom:12px!important}.proof-ai-note{display:flex;gap:10px;align-items:flex-start;margin-top:10px;padding:11px 12px;border-radius:13px;background:color-mix(in srgb,var(--primary) 6%,var(--surface));border:1px solid color-mix(in srgb,var(--primary) 13%,var(--line));font-size:.74rem;line-height:1.65;color:var(--muted)}.proof-ai-note i{color:var(--primary);margin-top:3px}.proof-ai-note b{display:block;color:var(--text);font-size:.78rem}.upload-preview{padding:10px;border-radius:13px;background:var(--surface);border:1px solid var(--line)}
@media(max-width:560px){.pwa-install-btn{left:10px;bottom:calc(74px + env(safe-area-inset-bottom,0px));padding:9px 12px}.checkout-grid{grid-template-columns:1fr!important}.checkout-grid label.full{grid-column:auto!important}.pay-account-grid{grid-template-columns:1fr!important}}
</style>

<script>
(function(){
 const standalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
 const btn=document.getElementById('pwaInstallBtn'),label=document.getElementById('pwaInstallLabel'),sheet=document.getElementById('pwaInstallSheet'),title=document.getElementById('pwaInstallTitle'),intro=document.getElementById('pwaInstallIntro'),steps=document.getElementById('pwaInstallSteps');
 if(!btn||standalone)return;
 const ua=navigator.userAgent||'',vendor=navigator.vendor||'',platform=navigator.platform||'';
 const appleVendor=/Apple/i.test(vendor),isiPhoneUa=/iPhone|iPad|iPod/i.test(ua),isIPadDesktop=platform==='MacIntel'&&navigator.maxTouchPoints>1;
 const isRealIos=appleVendor&&(isiPhoneUa||isIPadDesktop),isSafari=isRealIos&&/^((?!CriOS|FxiOS|EdgiOS).)*Safari/i.test(ua),isAndroid=/Android/i.test(ua);
 let deferredPrompt=null;
 if('serviceWorker'in navigator){navigator.serviceWorker.register('/sw.js').catch(()=>{});}
 function show(kind){let d;if(kind==='ios'){d={title:'تثبيت التطبيق على iPhone / iPad',intro:isSafari?'ثبّت المنيو على الشاشة الرئيسية للوصول السريع والطلب مباشرة.':'افتح الصفحة في Safari أولًا ثم ثبّتها.',steps:isSafari?['اضغط زر المشاركة ⤴︎ في Safari.','اختر «إضافة إلى الشاشة الرئيسية».','اضغط «إضافة».']:['افتح الرابط في Safari.','اضغط المشاركة ⤴︎.','اختر «إضافة إلى الشاشة الرئيسية».']};}else{d={title:'ثبّت التطبيق على Android',intro:'خلي المنيو والطلبات على شاشة هاتفك مثل أي تطبيق.',steps:['اضغط «تثبيت» إذا ظهرت نافذة Chrome.','إذا لم تظهر، افتح قائمة Chrome ⋮.','اختر «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية».']};}title.textContent=d.title;intro.textContent=d.intro;steps.innerHTML=d.steps.map(x=>`<li>${x}</li>`).join('');sheet.classList.add('open');sheet.setAttribute('aria-hidden','false');}
 window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();deferredPrompt=e;label.textContent=isAndroid?'تثبيت على Android':'تثبيت التطبيق';btn.hidden=false;});
 if(isRealIos){label.textContent='تثبيت على iPhone';btn.hidden=false;}else if(isAndroid){label.textContent='تثبيت على Android';btn.hidden=false;}
 window.addEventListener('appinstalled',()=>{btn.hidden=true;deferredPrompt=null;});
 btn.addEventListener('click',async()=>{if(deferredPrompt){deferredPrompt.prompt();const choice=await deferredPrompt.userChoice;if(choice?.outcome==='accepted')btn.hidden=true;deferredPrompt=null;return;}if(isRealIos){show('ios');return;}if(isAndroid){show('android');return;}});
 const close=()=>{sheet.classList.remove('open');sheet.setAttribute('aria-hidden','true')};document.getElementById('pwaInstallClose')?.addEventListener('click',close);sheet?.addEventListener('click',e=>{if(e.target===sheet)close()});document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
})();
/* Payment-proof AI explanation: the existing backend analysis runs after the payment is created when enabled. */
(function(){
 const input=document.getElementById('paymentProofInput');if(!input)return;
 const field=document.getElementById('paymentProofField');if(!field||field.querySelector('.proof-ai-note'))return;
 const note=document.createElement('div');note.className='proof-ai-note';note.innerHTML='<i class="fa-solid fa-wand-magic-sparkles"></i><span><b>قراءة ذكية لإثبات الدفع</b>بعد إرسال الطلب، يستطيع النظام قراءة اسم المرسل والحساب والمرجع والمبلغ من صورة الإيصال ومساعدة الموظف في المراجعة. التحليل مساعد فقط ولا يعتمد أو يرفض الدفع تلقائيًا.</span>';field.appendChild(note);
 input.addEventListener('change',()=>{if(input.files?.length){note.querySelector('b').textContent='الصورة جاهزة للتحليل بعد إرسال الطلب';}});
})();
</script>
