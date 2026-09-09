<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $alert['title'] }}</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f5f6f8;padding:24px;color:#1f2937">
    <div style="max-width:640px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">
        <div style="padding:18px 22px;background:#fff8e7;border-bottom:1px solid #ead9aa">
            <h2 style="margin:0;font-size:20px">{{ $alert['title'] }}</h2>
        </div>

        <div style="padding:22px">
            <p style="margin-top:0">
                يوجد مخزون يحتاج متابعة بسبب قرب/تجاوز تاريخ الصلاحية.
            </p>

            <table style="width:100%;border-collapse:collapse">
                <tr><td style="padding:7px;border-bottom:1px solid #eee">المنتج</td><td style="padding:7px;border-bottom:1px solid #eee"><strong>{{ $alert['product_name'] }}</strong></td></tr>
                <tr><td style="padding:7px;border-bottom:1px solid #eee">الموقع</td><td style="padding:7px;border-bottom:1px solid #eee">{{ $alert['location_name'] }}</td></tr>
                <tr><td style="padding:7px;border-bottom:1px solid #eee">التشغيلة</td><td style="padding:7px;border-bottom:1px solid #eee">{{ $alert['batch_number'] }}</td></tr>
                <tr><td style="padding:7px;border-bottom:1px solid #eee">تاريخ الإنتاج</td><td style="padding:7px;border-bottom:1px solid #eee">{{ $alert['manufacturing_date'] ?: '—' }}</td></tr>
                <tr><td style="padding:7px;border-bottom:1px solid #eee">تاريخ الصلاحية</td><td style="padding:7px;border-bottom:1px solid #eee"><strong>{{ $alert['expiry_date'] }}</strong></td></tr>
                <tr><td style="padding:7px;border-bottom:1px solid #eee">الحالة</td><td style="padding:7px;border-bottom:1px solid #eee">{{ $alert['status_text'] }}</td></tr>
                <tr><td style="padding:7px">الكمية المتبقية</td><td style="padding:7px">{{ number_format((float) $alert['available_quantity'], 3) }}</td></tr>
            </table>

            <p style="margin-bottom:0;margin-top:20px;color:#6b7280;font-size:13px">
                تم إنشاء هذا التنبيه آلياً من نظام مراقبة صلاحية المخزون.
            </p>
        </div>
    </div>
</body>
</html>
