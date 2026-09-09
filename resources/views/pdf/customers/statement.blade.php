<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<style>
body{font-family:dejavusans,sans-serif;direction:rtl;color:#222;font-size:10px}.header{text-align:center;border-bottom:2px solid #caa53a;padding-bottom:8px;margin-bottom:12px}.header h1{font-size:18px;margin:0 0 4px}.meta{width:100%;border-collapse:collapse;margin-bottom:10px}.meta td{padding:4px;border:1px solid #ddd}.summary{width:100%;border-collapse:collapse;margin:10px 0}.summary td{width:20%;padding:7px;border:1px solid #ddd;text-align:center}.summary strong{display:block;font-size:12px;margin-top:3px}.statement{width:100%;border-collapse:collapse}.statement th{background:#f3ead0;padding:6px;border:1px solid #bbb}.statement td{padding:5px;border:1px solid #ddd;vertical-align:top}.number{text-align:left;white-space:nowrap}.muted{color:#666}.footer{margin-top:12px;color:#777;font-size:8px;text-align:center}
</style>
</head>
<body>
<div class="header">
    <h1>كشف حساب العميل</h1>
    <div>{{ $customer->name }}</div>
</div>
<table class="meta">
    <tr><td><strong>الهاتف:</strong> {{ $customer->phone }}</td><td><strong>نوع العميل:</strong> {{ $customer->typeLabel() }}</td><td><strong>النطاق:</strong> {{ $selectedLocation?->name ?? 'جميع الفروع' }}</td></tr>
    <tr><td><strong>من:</strong> {{ request('date_from') ?: 'بداية التعامل' }}</td><td><strong>إلى:</strong> {{ request('date_to') ?: 'تاريخ الطباعة' }}</td><td><strong>تاريخ الطباعة:</strong> {{ now()->format('Y-m-d H:i') }}</td></tr>
</table>
<table class="summary">
    <tr>
        <td>الرصيد السابق<strong>₪{{ number_format((float)$statement['opening_balance'],2) }}</strong></td>
        <td>مدين الفترة<strong>₪{{ number_format((float)$statement['period_debit'],2) }}</strong></td>
        <td>دائن الفترة<strong>₪{{ number_format((float)$statement['period_credit'],2) }}</strong></td>
        <td>الرصيد الختامي<strong>₪{{ number_format((float)$statement['closing_balance'],2) }}</strong></td>
        <td>المتأخر الحالي<strong>₪{{ number_format((float)$summary['overdue'],2) }}</strong></td>
    </tr>
</table>
<table class="statement">
<thead><tr><th>التاريخ</th><th>الفرع</th><th>الحركة</th><th>المرجع</th><th>البيان</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr></thead>
<tbody>
@if(abs((float)$statement['opening_balance']) > 0.0001)
<tr><td colspan="5"><strong>الرصيد الافتتاحي</strong></td><td></td><td></td><td class="number">₪{{ number_format((float)$statement['opening_balance'],2) }}</td></tr>
@endif
@forelse($statement['rows'] as $row)
<tr>
    <td>{{ $row['date']?->format('Y-m-d') }}</td><td>{{ $row['location'] }}</td><td>{{ $row['type_label'] }}</td><td>{{ $row['reference'] }}</td><td>{{ $row['description'] }}</td>
    <td class="number">{{ (float)$row['debit'] > 0 ? '₪'.number_format((float)$row['debit'],2) : '—' }}</td>
    <td class="number">{{ (float)$row['credit'] > 0 ? '₪'.number_format((float)$row['credit'],2) : '—' }}</td>
    <td class="number"><strong>₪{{ number_format((float)$row['balance'],2) }}</strong></td>
</tr>
@empty<tr><td colspan="8" style="text-align:center">لا توجد حركات ضمن الفترة المحددة.</td></tr>@endforelse
</tbody>
</table>
<div class="footer">حلويات دهب — كشف حساب صادر من نظام الإدارة</div>
</body>
</html>
