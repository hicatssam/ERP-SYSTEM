<button type="button" id="customerNotifyBtn" class="customer-notify-btn">
    <i class="fa-regular fa-bell"></i>
    <span>فعّل إشعارات الطلب</span>
</button>

<style>
.customer-notify-btn{width:100%;margin:12px 0 0;border:1px solid color-mix(in srgb,var(--primary) 22%,transparent);background:color-mix(in srgb,var(--primary) 6%,var(--surface));color:var(--primary);border-radius:13px;padding:11px 14px;font-weight:900;font-size:.8rem;display:flex;align-items:center;justify-content:center;gap:8px}
.customer-notify-btn.enabled{background:color-mix(in srgb,#177245 9%,var(--surface));color:#177245;border-color:#17724522}
</style>

<script>
(function(){
    const btn=document.getElementById('customerNotifyBtn');
    if(!btn)return;
    const key='customer_order_notifications_enabled';
    let audioCtx=null;

    function enabled(){return localStorage.getItem(key)==='1'}
    function setButton(){
        const on=enabled();
        btn.classList.toggle('enabled',on);
        btn.innerHTML=on
            ? '<i class="fa-solid fa-bell"></i><span>إشعارات الطلب مفعّلة</span>'
            : '<i class="fa-regular fa-bell"></i><span>فعّل إشعارات الطلب</span>';
    }

    function chime(){
        if(!enabled())return;
        try{
            audioCtx=audioCtx||new (window.AudioContext||window.webkitAudioContext)();
            const now=audioCtx.currentTime;
            [659.25,783.99].forEach((freq,i)=>{
                const osc=audioCtx.createOscillator();
                const gain=audioCtx.createGain();
                osc.type='sine';osc.frequency.value=freq;
                gain.gain.setValueAtTime(.0001,now+i*.12);
                gain.gain.exponentialRampToValueAtTime(.16,now+i*.12+.02);
                gain.gain.exponentialRampToValueAtTime(.0001,now+i*.12+.18);
                osc.connect(gain);gain.connect(audioCtx.destination);
                osc.start(now+i*.12);osc.stop(now+i*.12+.2);
            });
        }catch(e){}
        if(navigator.vibrate)navigator.vibrate([80,40,80]);
    }

    window.CustomerOrderNotify={
        changed(title,message){
            if(!enabled())return;
            chime();
            if('Notification' in window&&Notification.permission==='granted'){
                try{new Notification(title,{body:message,icon:@json($branding['logo_small'] ?? $branding['logo'] ?? $branding['favicon'] ?? null),tag:'customer-order-status'});}catch(e){}
            }
        }
    };

    btn.addEventListener('click',async()=>{
        if(enabled()){
            localStorage.removeItem(key);setButton();return;
        }
        if('Notification' in window&&Notification.permission==='default'){
            try{await Notification.requestPermission();}catch(e){}
        }
        localStorage.setItem(key,'1');
        try{audioCtx=audioCtx||new (window.AudioContext||window.webkitAudioContext)();await audioCtx.resume();}catch(e){}
        setButton();chime();
    });
    setButton();
})();
</script>
