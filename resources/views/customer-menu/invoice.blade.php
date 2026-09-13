<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>فاتورة {{ $invoice->invoice_number }}</title>
<style id="invoiceStyles">
:root{--primary:{{ $theme['primary'] ?? '#704C34' }};--accent:{{ $theme['accent'] ?? '#D79A55' }};--bg:{{ $theme['background'] ?? '#F7F3EE' }};--surface:#fff;--text:{{ $theme['text'] ?? '#241D18' }};--muted:{{ $theme['muted'] ?? '#7D746C' }}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Cairo,Tajawal,Arial,sans-serif}.wrap{width:min(900px,calc(100% - 28px));margin:24px auto 60px}.toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}.btn{border:0;border-radius:12px;padding:11px 16px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}.btn.primary{background:var(--primary);color:#fff}.btn.secondary{background:#fff;color:var(--primary);border:1px solid #00000016}.invoice{background:var(--surface);border-radius:24px;padding:30px;box-shadow:0 18px 50px #00000012;border:1px solid #00000010}.head{display:flex;justify-content:space-between;gap:18px;border-bottom:2px solid #0000000d;padding-bottom:20px}.head h1{margin:0;font-size:1.55rem}.muted{color:var(--muted);font-size:.82rem}.badge{display:inline-flex;padding:7px 11px;border-radius:999px;background:#f4f1ed;color:var(--primary);font-weight:800;font-size:.78rem}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:20px 0}.box{padding:13px;border-radius:14px;background:#faf8f5;border:1px solid #0000000d}.box small{display:block;color:var(--muted);margin-bottom:5px}.items{width:100%;border-collapse:collapse;margin-top:16px}.items th,.items td{padding:12px 10px;border-bottom:1px solid #0000000d;text-align:right}.items th{font-size:.78rem;color:var(--muted)}.totals{margin-top:18px;margin-right:auto;width:min(360px,100%)}.row{display:flex;justify-content:space-between;padding:8px 0}.row.total{font-size:1.12rem;font-weight:900;border-top:2px solid #00000010;margin-top:6px;padding-top:13px}.payment{margin-top:22px;padding:16px;border-radius:16px;background:#faf8f5;border:1px solid #0000000d}.payment strong{display:block;margin-bottom:5px}.footer{margin-top:24px;text-align:center;color:var(--muted);font-size:.78rem}@media(max-width:620px){.invoice{padding:18px}.head{flex-direction:column}.grid{grid-template-columns:1fr}.items th:nth-child(2),.items td:nth-child(2){display:none}.toolbar .btn{flex:1}}
@media print{body{background:#fff}.wrap{width:100%;margin:0}.toolbar{display:none}.invoice{box-shadow:none;border:0;border-radius:0}}
</style>
</head>
<body>
<div class="wrap">
 <div class="toolbar">
  <a class="btn secondary" href="{{ route('customer-menu.track', $order->public_token) }}">العودة للطلب</a>
  <button class="btn secondary" type="button" onclick="window.print()">طباعة</button>
  <button class="btn primary" type="button" id="saveImage">حفظ الفاتورة كصورة</button>
 </div>

 <section class="invoice" id="invoiceCard">
  <div class="head">
   <div>
    <div class="muted">فاتورة العميل</div>
    <h1>{{ $branding['name'] ?? $order->location->name }}</h1>
    <div class="muted">{{ $order->location->name }}</div>
   </div>
   <div>
    <div class="badge">{{ $invoice->paymentStatusLabel() }}</div>
    <div style="margin-top:9px;font-weight:900">#{{ $invoice->invoice_number }}</div>
    <div class="muted">{{ optional($invoice->issued_at)->format('Y-m-d H:i') }}</div>
   </div>
  </div>

  <div class="grid">
   <div class="box"><small>رقم الطلب</small><strong>#{{ $order->order_number }}</strong></div>
   <div class="box"><small>العميل</small><strong>{{ $order->customer?->name ?? data_get($order->public_order_meta, 'customer_name', 'عميل') }}</strong></div>
   <div class="box"><small>نوع الطلب</small><strong>{{ data_get($payment, 'arrangement') === 'pay_on_pickup' ? 'الدفع عند الاستلام' : ($order->restaurant_service_type?->label() ?? 'طلب مطعم') }}</strong></div>
   <div class="box"><small>حالة الدفع</small><strong>{{ data_get($payment, 'label', $invoice->paymentStatusLabel()) }}</strong></div>
  </div>

  <table class="items">
   <thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr></thead>
   <tbody>
   @foreach($invoice->items as $item)
    <tr>
     <td>{{ $item->description }}</td>
     <td>{{ number_format((float)$item->quantity, 0) }}</td>
     <td>{{ number_format((float)$item->unit_price, 2) }} ₪</td>
     <td>{{ number_format((float)$item->line_total, 2) }} ₪</td>
    </tr>
   @endforeach
   </tbody>
  </table>

  <div class="totals">
   <div class="row"><span>الإجمالي</span><strong>{{ number_format((float)$invoice->total_amount,2) }} ₪</strong></div>
   <div class="row"><span>المدفوع</span><strong>{{ number_format((float)$invoice->paid_amount,2) }} ₪</strong></div>
   <div class="row total"><span>المتبقي</span><strong>{{ number_format((float)$invoice->remaining_amount,2) }} ₪</strong></div>
  </div>

  <div class="payment">
   <strong>{{ data_get($payment, 'label', 'حالة الدفع') }}</strong>
   <span class="muted">{{ data_get($payment, 'message', '') }}</span>
  </div>

  <div class="footer">شكراً لاختياركم لنا — احتفظ بهذه الفاتورة للرجوع إليها عند الحاجة.</div>
 </section>
</div>
<script>
document.getElementById('saveImage').addEventListener('click', async () => {
    const card = document.getElementById('invoiceCard');
    const width = Math.ceil(card.scrollWidth);
    const height = Math.ceil(card.scrollHeight);
    const styleText = document.getElementById('invoiceStyles').textContent;
    const clone = card.cloneNode(true);
    clone.style.margin = '0';
    clone.style.width = width + 'px';

    const xhtml = `<div xmlns="http://www.w3.org/1999/xhtml"><style>${styleText}</style>${clone.outerHTML}</div>`;
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}"><foreignObject width="100%" height="100%">${xhtml}</foreignObject></svg>`;
    const blob = new Blob([svg], {type:'image/svg+xml;charset=utf-8'});
    const url = URL.createObjectURL(blob);
    const img = new Image();

    img.onload = () => {
        const scale = Math.min(2, window.devicePixelRatio || 1.5);
        const canvas = document.createElement('canvas');
        canvas.width = Math.ceil(width * scale);
        canvas.height = Math.ceil(height * scale);
        const ctx = canvas.getContext('2d');
        ctx.scale(scale, scale);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);
        ctx.drawImage(img, 0, 0, width, height);
        URL.revokeObjectURL(url);
        const link = document.createElement('a');
        link.download = @json($invoice->invoice_number . '.png');
        link.href = canvas.toDataURL('image/png', 1);
        link.click();
    };

    img.onerror = () => {
        URL.revokeObjectURL(url);
        alert('تعذر إنشاء الصورة على هذا المتصفح. استخدم خيار الطباعة كحل بديل.');
    };

    img.src = url;
});
</script>
</body>
</html>
