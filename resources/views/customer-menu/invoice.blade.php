<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    @php
        $brandName = $branding['name']
            ?? $branding['brand_name']
            ?? $invoice->location?->name
            ?? 'فاتورة الطلب';

        $logoCandidates = [
            $branding['logo'] ?? null,
            data_get($branding, 'assets.logo.url'),
            data_get($branding, 'assets.logo.public_url'),
            data_get($branding, 'logos.primary'),
            data_get($branding, 'logos.primary.url'),
        ];

        $brandLogo = collect($logoCandidates)
            ->map(function ($candidate) {
                if (is_array($candidate)) {
                    return $candidate['url']
                        ?? $candidate['src']
                        ?? $candidate['path']
                        ?? $candidate['public_url']
                        ?? null;
                }

                return $candidate;
            })
            ->first(fn ($candidate) => filled($candidate));

        if (
            is_string($brandLogo)
            && $brandLogo !== ''
            && ! preg_match('/^(https?:|data:|blob:|\/\/|\/)/i', $brandLogo)
        ) {
            $brandLogo = '/' . ltrim($brandLogo, '/');
        }

        $customerName = $invoice->customer?->name
            ?? $order->customer?->name
            ?? $order->guest_name
            ?? 'عميل نقدي';

        $customerPhone = $invoice->customer?->phone
            ?? $order->customer?->phone
            ?? $order->guest_phone;

        $invoiceRows = $invoice->items->map(fn ($item) => [
            'name' => $item->description ?: ($item->product?->name_ar ?: $item->product?->name ?: 'صنف'),
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'line_total' => (float) $item->line_total,
        ]);

        if ($invoiceRows->isEmpty()) {
            $invoiceRows = $order->items->map(fn ($item) => [
                'name' => $item->product_name ?: 'صنف',
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ]);
        }

        $issuedAt = $invoice->issued_at ?? $invoice->created_at;
        $statusIsCancelled = $invoice->statusValue() === 'cancelled';
    @endphp

    <title>فاتورة {{ $invoice->invoice_number }} — {{ $brandName }}</title>

    @if(!empty($branding['favicon']))
        <link rel="icon" href="{{ $branding['favicon'] }}">
    @endif

    <style>
        :root{
            --primary:{{ $theme['primary'] ?? '#704C34' }};
            --accent:{{ $theme['accent'] ?? '#D79A55' }};
            --background:{{ $theme['background'] ?? '#F7F3EE' }};
            --surface:{{ $theme['surface'] ?? '#FFFFFF' }};
            --text:{{ $theme['text'] ?? '#241D18' }};
            --muted:{{ $theme['muted'] ?? '#7D746C' }};
            --border:color-mix(in srgb,var(--text) 11%,transparent);
        }
        *{box-sizing:border-box}
        body{
            margin:0;
            min-height:100vh;
            color:var(--text);
            background:var(--background);
            font-family:Cairo,Tajawal,Arial,sans-serif;
        }
        a{color:inherit;text-decoration:none}
        .shell{width:min(900px,calc(100% - 28px));margin:0 auto;padding:28px 0 52px}
        .toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
        .toolbarLinks{display:flex;gap:8px}
        .button{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:42px;
            padding:9px 14px;
            border:1px solid var(--border);
            border-radius:12px;
            background:var(--surface);
            color:var(--primary);
            font-size:.82rem;
            font-weight:900;
            cursor:pointer;
        }
        .button.primary{border-color:var(--primary);background:var(--primary);color:#fff}
        .invoice{
            overflow:hidden;
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:24px;
            box-shadow:0 22px 60px color-mix(in srgb,var(--text) 10%,transparent);
        }
        .invoiceHead{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:24px;
            padding:30px;
            color:#fff;
            background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 78%,#000));
        }
        .brand{display:flex;align-items:center;gap:12px}
        .brand img{width:58px;height:58px;object-fit:contain;border-radius:15px;background:#fff;padding:5px}
        .brand h1{margin:0;font-size:1.15rem}
        .brand small{display:block;margin-top:4px;color:rgba(255,255,255,.7)}
        .invoiceNumber{text-align:left}
        .invoiceNumber small{display:block;color:rgba(255,255,255,.72);font-size:.7rem}
        .invoiceNumber strong{display:block;margin-top:4px;font-size:1.15rem;direction:ltr}
        .statusRow{display:flex;flex-wrap:wrap;gap:8px;padding:18px 30px;border-bottom:1px solid var(--border)}
        .status{
            display:inline-flex;
            align-items:center;
            padding:6px 10px;
            border-radius:999px;
            color:#166534;
            background:#dcfce7;
            font-size:.72rem;
            font-weight:900;
        }
        .status.payment{color:#92400e;background:#fef3c7}
        .status.cancelled{color:#b91c1c;background:#fee2e2}
        .partyGrid{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:22px 30px}
        .partyBox{padding:16px;border:1px solid var(--border);border-radius:15px;background:color-mix(in srgb,var(--background) 55%,var(--surface))}
        .partyBox small{display:block;margin-bottom:8px;color:var(--muted);font-size:.68rem;font-weight:800}
        .partyBox strong{display:block;font-size:.95rem}
        .partyBox span{display:block;margin-top:5px;color:var(--muted);font-size:.76rem}
        .itemsWrap{padding:0 30px 24px;overflow-x:auto}
        table{width:100%;border-collapse:collapse}
        th,td{padding:13px 10px;border-bottom:1px solid var(--border);text-align:right;font-size:.78rem}
        th{color:var(--muted);background:color-mix(in srgb,var(--background) 60%,var(--surface));font-size:.68rem}
        th:not(:first-child),td:not(:first-child){text-align:center;white-space:nowrap}
        .totals{display:flex;justify-content:flex-end;padding:0 30px 30px}
        .totalsCard{width:min(100%,360px);padding:16px;border:1px solid var(--border);border-radius:15px}
        .totalRow{display:flex;justify-content:space-between;gap:18px;padding:8px 0;color:var(--muted);font-size:.78rem}
        .totalRow strong{color:var(--text)}
        .totalRow.grand{margin-top:5px;padding-top:14px;border-top:1px solid var(--border);font-size:1rem;font-weight:900}
        .totalRow.grand strong{color:var(--primary)}
        .invoiceFoot{padding:18px 30px;border-top:1px solid var(--border);color:var(--muted);font-size:.7rem;text-align:center}
        @media(max-width:620px){
            .shell{width:min(100% - 18px,900px);padding-top:12px}
            .toolbar{align-items:stretch;flex-direction:column}
            .toolbarLinks{display:grid;grid-template-columns:1fr 1fr}
            .invoiceHead{padding:22px 18px;flex-direction:column}
            .invoiceNumber{text-align:right}
            .partyGrid{grid-template-columns:1fr;padding:18px}
            .statusRow,.itemsWrap,.totals,.invoiceFoot{padding-left:18px;padding-right:18px}
            th,td{padding:11px 8px}
        }
        @media print{
            body{background:#fff}
            .shell{width:100%;padding:0}
            .toolbar{display:none}
            .invoice{border:0;border-radius:0;box-shadow:none}
            .invoiceHead{print-color-adjust:exact;-webkit-print-color-adjust:exact}
            .status,.partyBox,th{print-color-adjust:exact;-webkit-print-color-adjust:exact}
        }
    </style>
</head>
<body>
    <main class="shell">
        <nav class="toolbar" aria-label="إجراءات الفاتورة">
            <a class="button" href="{{ route('customer-menu.track', ['token' => $order->public_token]) }}">
                العودة لتتبع الطلب
            </a>

            <div class="toolbarLinks">
                <a class="button" href="{{ route('customer-menu.show', $order->location->code) }}">
                    المنيو
                </a>
                <button class="button primary" type="button" onclick="window.print()">
                    طباعة الفاتورة
                </button>
            </div>
        </nav>

        <article class="invoice">
            <header class="invoiceHead">
                <div class="brand">
                    @if($brandLogo)
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }}">
                    @endif
                    <div>
                        <h1>{{ $brandName }}</h1>
                        <small>{{ $invoice->location?->name ?? $order->location?->name }}</small>
                    </div>
                </div>

                <div class="invoiceNumber">
                    <small>فاتورة بيع</small>
                    <strong>{{ $invoice->invoice_number }}</strong>
                    <small>{{ $issuedAt?->format('Y-m-d H:i') }}</small>
                </div>
            </header>

            <div class="statusRow">
                <span class="status {{ $statusIsCancelled ? 'cancelled' : '' }}">
                    {{ $invoice->statusLabel() }}
                </span>
                <span class="status payment">
                    {{ $invoice->paymentStatusLabel() }}
                </span>
                <span class="status payment">
                    الطلب #{{ $order->order_number }}
                </span>
            </div>

            <section class="partyGrid">
                <div class="partyBox">
                    <small>بيانات العميل</small>
                    <strong>{{ $customerName }}</strong>
                    @if($customerPhone)
                        <span dir="ltr">{{ $customerPhone }}</span>
                    @endif
                    @if($order->delivery_address)
                        <span>{{ $order->delivery_address }}</span>
                    @endif
                </div>

                <div class="partyBox">
                    <small>بيانات الطلب</small>
                    <strong>{{ $order->location?->name ?? '—' }}</strong>
                    <span>رقم الطلب: {{ $order->order_number }}</span>
                    @if($order->restaurantTable)
                        <span>الطاولة: {{ $order->restaurantTable->name ?? $order->restaurantTable->number }}</span>
                    @endif
                </div>
            </section>

            <section class="itemsWrap">
                <table>
                    <thead>
                        <tr>
                            <th>الصنف</th>
                            <th>الكمية</th>
                            <th>سعر الوحدة</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoiceRows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ rtrim(rtrim(number_format($row['quantity'], 3), '0'), '.') }}</td>
                                <td>{{ number_format($row['unit_price'], 2) }} ₪</td>
                                <td><strong>{{ number_format($row['line_total'], 2) }} ₪</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">لا توجد عناصر مسجلة في الفاتورة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="totals">
                <div class="totalsCard">
                    <div class="totalRow">
                        <span>المجموع الفرعي</span>
                        <strong>{{ number_format((float) $invoice->subtotal, 2) }} ₪</strong>
                    </div>
                    @if((float) $invoice->discount_amount > 0)
                        <div class="totalRow">
                            <span>الخصم</span>
                            <strong>-{{ number_format((float) $invoice->discount_amount, 2) }} ₪</strong>
                        </div>
                    @endif
                    @if((float) $invoice->tax_amount > 0)
                        <div class="totalRow">
                            <span>الضريبة</span>
                            <strong>{{ number_format((float) $invoice->tax_amount, 2) }} ₪</strong>
                        </div>
                    @endif
                    <div class="totalRow">
                        <span>المدفوع</span>
                        <strong>{{ number_format((float) $invoice->paid_amount, 2) }} ₪</strong>
                    </div>
                    <div class="totalRow">
                        <span>المتبقي</span>
                        <strong>{{ number_format((float) $invoice->remaining_amount, 2) }} ₪</strong>
                    </div>
                    <div class="totalRow grand">
                        <span>الإجمالي</span>
                        <strong>{{ number_format((float) $invoice->total_amount, 2) }} ₪</strong>
                    </div>
                </div>
            </section>

            <footer class="invoiceFoot">
                احتفظ بهذه الفاتورة للرجوع إليها عند الحاجة. شكرًا لاختيارك {{ $brandName }}.
            </footer>
        </article>
    </main>
</body>
</html>
