<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; direction: rtl; }
        .header { background: #b8860b; color: #fff; padding: 20px 24px; border-radius: 6px 6px 0 0; }
        .header h1 { margin: 0; font-size: 1.2rem; }
        .body { padding: 24px; background: #fafafa; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 6px 6px; }
        .meta { background: #fff; border: 1px solid #e8e8e8; border-radius: 4px; padding: 16px; margin-bottom: 16px; }
        .meta-row { display: flex; margin-bottom: 8px; font-size: .9rem; }
        .meta-label { font-weight: bold; min-width: 120px; color: #555; }
        .footer { margin-top: 20px; font-size: .78rem; color: #999; text-align: center; }
    </style>
</head>
<body>
<div class="header">
    <h1>حلويات دهب — تقرير مجدول</h1>
</div>
<div class="body">
    <p>مرحباً،</p>
    <p>يرجى الاطلاع على التقرير المرفق الذي تم إنشاؤه تلقائياً وفق الجدول الزمني المحدد.</p>

    <div class="meta">
        <div class="meta-row"><span class="meta-label">اسم الجدول:</span> <span>{{ $schedule->name }}</span></div>
        <div class="meta-row"><span class="meta-label">نوع التقرير:</span> <span>{{ [
            'orders'=>'تقرير الطلبات','cake-orders'=>'تقرير طلبات الكيك الخاصة',
            'inventory'=>'تقرير المخزون','stock-movements'=>'تقرير حركات المخزون',
            'low-stock'=>'تقرير المخزون المنخفض','stock-transfers'=>'تقرير التحويلات',
            'payments'=>'تقرير الدفعات','invoices'=>'تقرير الفواتير',
            'cash-sessions'=>'تقرير جلسات الكاشير','daily-sales'=>'تقرير المبيعات اليومية',
            'monthly-sales'=>'تقرير المبيعات الشهرية','branch-sales'=>'تقرير أداء الفروع',
            'product-sales'=>'تقرير مبيعات المنتجات','collections'=>'تقرير التحصيلات',
            'outstanding'=>'تقرير الأرصدة المعلقة','activity-logs'=>'سجل النشاطات',
        ][$schedule->report_type] ?? $schedule->report_type }}</span></div>
        <div class="meta-row"><span class="meta-label">الفترة الزمنية:</span> <span>{{ $dateFrom }} — {{ $dateTo }}</span></div>
        <div class="meta-row"><span class="meta-label">الفرع / الموقع:</span> <span>{{ $schedule->location?->name ?? 'جميع الفروع' }}</span></div>
        <div class="meta-row"><span class="meta-label">التكرار:</span> <span>{{ $schedule->frequencyLabel() }}</span></div>
    </div>

    <p>ملاحظة: يرجى عدم الرد على هذا البريد. إن كنت لا تريد تلقي هذه التقارير، يرجى التواصل مع المسؤول لإزالة بريدك من القائمة.</p>
</div>
<div class="footer">نظام إدارة حلويات دهب — تم الإرسال تلقائياً</div>
</body>
</html>
