<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    @php
        $brandLogo = null;
        $logoCandidates = [
            data_get($branding, 'logo'),
            data_get($branding, 'logo.url'),
            data_get($branding, 'logo.src'),
            data_get($branding, 'logo.path'),
            data_get($branding, 'logo.original_url'),
            data_get($branding, 'logo.public_url'),
            data_get($branding, 'logo_url'),
            data_get($branding, 'brand_logo'),
            data_get($branding, 'logo_small'),
            data_get($branding, 'favicon'),
            data_get($branding, 'assets.logo'),
            data_get($branding, 'assets.logo.url'),
            data_get($branding, 'assets.logo.original_url'),
            data_get($branding, 'assets.logo.public_url'),
            data_get($branding, 'logos.primary'),
            data_get($branding, 'logos.primary.url'),
        ];
        foreach ($logoCandidates as $logoCandidate) {
            if (is_array($logoCandidate)) {
                $logoCandidate = $logoCandidate['url']
                    ?? $logoCandidate['src']
                    ?? $logoCandidate['path']
                    ?? $logoCandidate['original_url']
                    ?? $logoCandidate['public_url']
                    ?? null;
            }
            if (!empty($logoCandidate)) {
                $brandLogo = $logoCandidate;
                break;
            }
        }
        $brandName = !empty($branding['name'])
            ? $branding['name']
            : (!empty($branding['brand_name']) ? $branding['brand_name'] : 'طلبك');
        if (is_string($brandLogo) && $brandLogo !== '' && !preg_match('/^(https?:|data:|blob:|\/\/|\/)/i', $brandLogo)) {
            $brandLogo = '/'.ltrim($brandLogo, '/');
        }
        $appDownloadUrl = $branding['app_download_url']
            ?? $branding['app_url']
            ?? $branding['mobile_app_url']
            ?? $branding['app_link']
            ?? null;
        $websiteUrl = $branding['website_url']
            ?? $branding['website']
            ?? $branding['url']
            ?? null;
    @endphp
    <title>تتبع {{ $order->order_number }} — {{ $brandName }}</title>
    @if(!empty($branding['favicon']))
        <link rel="icon" href="{{ $branding['favicon'] }}">
    @endif
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{
            --primary:{{ $theme['primary'] ?? '#704C34' }};
            --accent:{{ $theme['accent'] ?? '#D79A55' }};
            --bg:{{ $theme['background'] ?? '#F7F3EE' }};
            --surface:{{ $theme['surface'] ?? '#FFFFFF' }};
            --text:{{ $theme['text'] ?? '#241D18' }};
            --muted:{{ $theme['muted'] ?? '#7D746C' }};
            --radius:{{ $theme['radius'] ?? 18 }}px;
        }
        *{box-sizing:border-box}
        body{
            margin:0;
            min-height:100vh;
            background:
                radial-gradient(circle at 90% 0%, color-mix(in srgb,var(--accent) 20%,transparent), transparent 25rem),
                var(--bg);
            color:var(--text);
            font-family:Cairo,Tajawal,Arial,sans-serif;
        }
        a{color:inherit;text-decoration:none}
        .wrap{width:min(820px,calc(100% - 30px));margin:auto}
        .appBar{
            background:var(--primary);
            color:#fff;
            font-size:.82rem;
        }
        .appBarInner{
            min-height:40px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
        }
        .appHint{display:flex;align-items:center;gap:7px;opacity:.92}
        .appLinks{display:flex;align-items:center;gap:8px}
        .appLink{
            display:inline-flex;
            align-items:center;
            gap:7px;
            padding:6px 10px;
            border-radius:999px;
            font-weight:900;
            white-space:nowrap;
        }
        .downloadLink{background:var(--accent);color:var(--text)}
        .websiteLink{border:1px solid rgba(255,255,255,.28);color:#fff}
        .websiteLink img{width:20px;height:20px;object-fit:contain;border-radius:6px;background:#fff}
        .topbar{
            position:sticky;
            top:0;
            z-index:30;
            padding:18px 0;
            background:color-mix(in srgb,var(--bg) 92%,transparent);
            backdrop-filter:blur(12px);
        }
        .topin{display:flex;align-items:center;justify-content:space-between;gap:14px}
        .brand{display:flex;align-items:center;gap:10px}
        .brand img{width:42px;height:42px;object-fit:contain}
        .brand b,.brand small{display:block}
        .brand small{color:var(--muted);font-size:.76rem}
        .back{color:var(--primary);font-weight:900}
        .card{
            background:var(--surface);
            border:1px solid color-mix(in srgb,var(--text) 8%,transparent);
            border-radius:calc(var(--radius) + 5px);
            padding:clamp(20px,5vw,38px);
            box-shadow:0 22px 65px color-mix(in srgb,var(--text) 11%,transparent);
        }
        .orderHead{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:18px;
            padding-bottom:24px;
            border-bottom:1px solid color-mix(in srgb,var(--text) 9%,transparent);
        }
        .eyebrow{font-size:.78rem;color:var(--muted);font-weight:800}
        h1{margin:5px 0 0;font-size:clamp(25px,5vw,38px)}
        .status{
            display:inline-flex;
            align-items:center;
            gap:7px;
            padding:9px 13px;
            border-radius:999px;
            background:color-mix(in srgb,var(--accent) 22%,var(--surface));
            color:var(--primary);
            font-size:.82rem;
            font-weight:900;
            white-space:nowrap;
        }
        .statusDot{width:8px;height:8px;border-radius:50%;background:var(--accent)}
        .progress{padding:28px 0 25px}
        .progressLine{display:grid;grid-template-columns:repeat(4,1fr);position:relative}
        .progressLine::before{
            content:"";
            position:absolute;
            top:17px;
            right:12.5%;
            left:12.5%;
            height:3px;
            background:color-mix(in srgb,var(--text) 12%,transparent);
        }
        .progressItem{position:relative;z-index:1;text-align:center;color:var(--muted);font-size:.74rem;font-weight:800}
        .progressItem .dot{
            display:grid;
            place-items:center;
            width:36px;
            height:36px;
            margin:0 auto 8px;
            border-radius:50%;
            background:var(--bg);
            border:3px solid color-mix(in srgb,var(--text) 13%,transparent);
            color:var(--muted);
            font-size:.8rem;
        }
        .progressItem.on{color:var(--primary)}
        .progressItem.on .dot{background:var(--primary);border-color:var(--primary);color:#fff}
        .progressItem.current .dot{box-shadow:0 0 0 5px color-mix(in srgb,var(--accent) 28%,transparent)}
        .notice{
            display:none;
            padding:12px 14px;
            margin-bottom:20px;
            border-radius:12px;
            background:color-mix(in srgb,var(--accent) 16%,var(--surface));
            color:var(--primary);
            font-size:.9rem;
        }
        .notice.show{display:block}
        .sectionTitle{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:4px 0 10px}
        .sectionTitle h2{font-size:1rem;margin:0}
        .live{font-size:.72rem;color:var(--muted)}
        .items{border-top:1px solid color-mix(in srgb,var(--text) 9%,transparent)}
        .item{display:flex;justify-content:space-between;gap:12px;padding:14px 0;border-bottom:1px solid color-mix(in srgb,var(--text) 9%,transparent)}
        .item small{display:block;color:var(--muted);margin-top:3px}
        .item b{white-space:nowrap;color:var(--primary)}
        .total{display:flex;justify-content:space-between;gap:12px;padding-top:20px;font-size:1.15rem;font-weight:900}
        .total span:last-child{color:var(--primary)}
        .footerActions{display:flex;gap:10px;margin-top:27px}
        .action{flex:1;text-align:center;padding:12px 14px;border-radius:12px;font-weight:900}
        .action.primary{background:var(--primary);color:#fff}
        .action.secondary{border:1px solid color-mix(in srgb,var(--primary) 20%,transparent);color:var(--primary)}
        .cancelled .progress{opacity:.42}
        @media(max-width:560px){
            .wrap{width:min(100% - 20px,820px)}
            .topbar{padding:12px 0}
            .brand img{width:36px;height:36px}
            .orderHead{flex-direction:column}
            .status{align-self:flex-start}
            .progressItem{font-size:.65rem}
            .progressItem .dot{width:32px;height:32px}
            .progressLine::before{top:15px}
            .footerActions{flex-direction:column}
            .appBarInner{min-height:38px}
            .appHint{font-size:.72rem}
            .appHint span:last-child{max-width:145px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .appLink{padding:6px 8px;font-size:.74rem}
            .websiteLink span{display:none}
        }
    </style>
</head>
<body>
    @if($appDownloadUrl || $websiteUrl)
        <div class="appBar">
            <div class="wrap appBarInner">
                <span class="appHint">
                    <span aria-hidden="true">📱</span>
                    <span>اطلب أسرع من تطبيق {{ $brandName }}</span>
                </span>
                <div class="appLinks">
                    @if($websiteUrl)
                        <a class="appLink websiteLink" href="{{ $websiteUrl }}" target="_blank" rel="noopener">
                            @if($brandLogo)
                                <img src="{{ $brandLogo }}" alt="">
                            @endif
                            <span>الموقع</span>
                        </a>
                    @endif
                    @if($appDownloadUrl)
                        <a class="appLink downloadLink" href="{{ $appDownloadUrl }}" target="_blank" rel="noopener">
                            تحميل التطبيق <span aria-hidden="true">↓</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif
    <header class="topbar">
        <div class="wrap topin">
            <a class="brand" href="{{ route('customer-menu.show',$order->location->code) }}">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}">
                @endif
                <span>
                    <b>{{ $brandName }}</b>
                    <small>{{ $order->location->name }}</small>
                </span>
            </a>
            <a class="back" href="{{ route('customer-menu.my-orders',$order->location->code) }}">طلباتي ←</a>
        </div>
    </header>

    <main class="wrap" style="padding-bottom:60px">
        <section class="card" id="trackingCard">
            <div class="orderHead">
                <div>
                    <span class="eyebrow">تفاصيل الطلب</span>
                    <h1>#{{ $order->order_number }}</h1>
                </div>
                <span class="status" id="label">
                    <span class="statusDot"></span>
                    <span>جاري تحديث الحالة</span>
                </span>
            </div>

            <div class="progress" aria-label="مراحل الطلب">
                <div class="progressLine">
                    <div class="progressItem on" data-stage="0">
                        <span class="dot"><i class="fa-solid fa-bag-shopping"></i></span>
                        <span>تم الاستلام</span>
                    </div>
                    <div class="progressItem" data-stage="1">
                        <span class="dot"><i class="fa-solid fa-check"></i></span>
                        <span>تم التأكيد</span>
                    </div>
                    <div class="progressItem" data-stage="2">
                        <span class="dot"><i class="fa-solid fa-fire-burner"></i></span>
                        <span>قيد التحضير</span>
                    </div>
                    <div class="progressItem" data-stage="3">
                        <span class="dot"><i class="fa-solid fa-house"></i></span>
                        <span>جاهز</span>
                    </div>
                </div>
            </div>

            <div class="notice" id="payment"></div>

            <div class="sectionTitle">
                <h2>ملخص الطلب</h2>
                <span class="live">التحديث تلقائيًا</span>
            </div>
            <div class="items">
                @foreach($order->items as $item)
                    <div class="item">
                        <span>
                            {{ $item->product_name }}
                            <small>الكمية: {{ $item->quantity }}</small>
                        </span>
                        <b>{{ number_format($item->line_total,2) }} ₪</b>
                    </div>
                @endforeach
            </div>
            <div class="total">
                <span>الإجمالي</span>
                <span>{{ number_format($order->total_amount,2) }} ₪</span>
            </div>

            <div class="footerActions">
                <a class="action primary" href="{{ route('customer-menu.show',$order->location->code) }}">طلب جديد</a>
                <a class="action secondary" href="{{ route('customer-menu.my-orders',$order->location->code) }}">كل طلباتي</a>
            </div>
        </section>
    </main>

    <script>
        const url = @json(route('customer-menu.status',$order->public_token));
        const label = document.querySelector('#label span:last-child');
        const payment = document.querySelector('#payment');
        const card = document.querySelector('#trackingCard');
        const stages = [...document.querySelectorAll('[data-stage]')];

        const statusText = {
            pending: 'تم استلام الطلب',
            received: 'تم استلام الطلب',
            confirmed: 'تم تأكيد الطلب',
            accepted: 'تم تأكيد الطلب',
            preparing: 'جاري تجهيز طلبك',
            ready: 'طلبك جاهز',
            completed: 'تم إكمال الطلب',
            cancelled: 'تم إلغاء الطلب',
            canceled: 'تم إلغاء الطلب'
        };

        function updateTracking(data) {
            const status = String(data.status || 'pending').toLowerCase();
            const kitchen = (data.kitchen || []).map(value => String(value).toLowerCase());
            const cancelled = status === 'cancelled' || status === 'canceled';
            let stage = 0;

            if (['confirmed','accepted'].includes(status) || kitchen.length) stage = 1;
            if (['preparing','ready','completed'].includes(status) || kitchen.includes('preparing') || kitchen.includes('ready')) stage = 2;
            if (['ready','completed'].includes(status) || kitchen.includes('ready')) stage = 3;

            stages.forEach(item => {
                const value = Number(item.dataset.stage);
                item.classList.toggle('on', !cancelled && value <= stage);
                item.classList.toggle('current', !cancelled && value === stage);
            });

            card.classList.toggle('cancelled', cancelled);
            label.textContent = statusText[status] || 'جاري تجهيز طلبك';

            const paymentStatus = String(data.payment_status || '').toLowerCase();
            const needsReview = paymentStatus === 'pending_payment_verification';
            payment.textContent = needsReview ? 'إثبات الدفع قيد المراجعة — سنحدّث الحالة فور اعتماد العملية.' : '';
            payment.classList.toggle('show', needsReview);
        }

        async function poll() {
            try {
                const response = await fetch(url, {headers:{Accept:'application/json'}});
                const data = await response.json();
                if (!response.ok) throw data;
                updateTracking(data);
            } catch (error) {
                label.textContent = 'تعذر تحديث الحالة مؤقتًا';
            }
        }

        poll();
        setInterval(poll, 5000);
    </script>
</body>
</html>