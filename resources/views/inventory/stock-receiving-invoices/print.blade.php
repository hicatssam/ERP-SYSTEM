<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>فاتورة استلام مخزون — {{ $stockReceivingInvoice->invoice_number }}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Arial','Tahoma',sans-serif; font-size:13px; color:#222; direction:rtl; background:#fff; }
  .page { max-width:800px; margin:0 auto; padding:30px 25px; }

  /* Header */
  .inv-header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #d4af37; padding-bottom:18px; margin-bottom:20px; }
  .inv-brand h1 { font-size:22px; color:#d4af37; font-weight:800; letter-spacing:1px; }
  .inv-brand p { font-size:11px; color:#666; margin-top:3px; }
  .inv-title-block { text-align:center; }
  .inv-title-block h2 { font-size:18px; font-weight:700; color:#333; }
  .inv-title-block .inv-number { font-size:13px; color:#666; margin-top:4px; }
  .inv-stamp-area { width:90px; height:90px; border:2px dashed #d4af37; border-radius:50%; display:flex; align-items:center; justify-content:center; text-align:center; color:#d4af37; font-size:9px; font-weight:700; }

  /* Info Grid */
  .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px 20px; background:#fafafa; border:1px solid #e8e0c8; border-radius:6px; padding:14px 18px; margin-bottom:18px; }
  .info-row { display:flex; gap:6px; align-items:baseline; }
  .info-label { color:#888; font-size:11px; white-space:nowrap; }
  .info-value { color:#222; font-weight:600; font-size:12px; }

  /* Table */
  table { width:100%; border-collapse:collapse; margin-bottom:18px; }
  thead th { background:#d4af37; color:#fff; padding:8px 10px; font-size:12px; font-weight:700; text-align:right; }
  tbody td { padding:7px 10px; border-bottom:1px solid #eee; font-size:12px; }
  tbody tr:nth-child(even) { background:#fffdf5; }
  .diff-ok { color:#16a34a; font-weight:700; }
  .diff-bad { color:#dc2626; font-weight:700; }

  /* Summary */
  .summary-row { display:flex; gap:20px; margin-bottom:22px; }
  .summary-box { flex:1; background:#f8f5e8; border:1px solid #e8e0c8; border-radius:6px; padding:10px 14px; text-align:center; }
  .summary-box .s-label { font-size:10px; color:#888; }
  .summary-box .s-value { font-size:16px; font-weight:800; color:#d4af37; }

  /* Signatures */
  .sig-section { display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-top:30px; padding-top:18px; border-top:1px dashed #ccc; }
  .sig-box { text-align:center; }
  .sig-box .sig-line { border-bottom:1px solid #999; height:45px; margin-bottom:6px; }
  .sig-box .sig-label { font-size:11px; color:#666; }

  /* Footer */
  .inv-footer { text-align:center; font-size:10px; color:#aaa; margin-top:20px; padding-top:12px; border-top:1px solid #eee; }

  @media print {
    body { font-size:12px; }
    .page { padding:15px; }
    @page { margin:1cm; }
  }
</style>
</head>
<body>
<div class="page">

  {{-- Header --}}
  <div class="inv-header">
    <div class="inv-brand">
      <h1>حلويات دهب</h1>
      <p>Dahab Sweets</p>
    </div>
    <div class="inv-title-block">
      <h2>فاتورة استلام مخزون</h2>
      <div class="inv-number">{{ $stockReceivingInvoice->invoice_number }}</div>
      <div class="inv-number" style="margin-top:2px">{{ $stockReceivingInvoice->issued_at?->format('Y/m/d H:i') }}</div>
    </div>
    <div class="inv-stamp-area">ختم<br>حلويات<br>دهب</div>
  </div>

  {{-- Info Grid --}}
  <div class="info-grid">
    <div class="info-row">
      <span class="info-label">رقم التحويل:</span>
      <span class="info-value">{{ $stockReceivingInvoice->stockTransfer?->transfer_number }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">الفرع المستلِم:</span>
      <span class="info-value">{{ $stockReceivingInvoice->receivingLocation?->name }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">المرسِل (المصنع):</span>
      <span class="info-value">{{ $stockReceivingInvoice->sendingLocation?->name }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">المستلِم:</span>
      <span class="info-value">{{ $stockReceivingInvoice->receivedBy?->display_name ?? 'غير مسجل' }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">تاريخ الاستلام:</span>
      <span class="info-value">{{ $stockReceivingInvoice->issued_at?->format('Y/m/d') }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">وقت الاستلام:</span>
      <span class="info-value">{{ $stockReceivingInvoice->issued_at?->format('H:i') }}</span>
    </div>
  </div>

  {{-- Summary Boxes --}}
  <div class="summary-row">
    <div class="summary-box">
      <div class="s-label">إجمالي المطلوب</div>
      <div class="s-value">{{ $stockReceivingInvoice->total_items_ordered }}</div>
    </div>
    <div class="summary-box">
      <div class="s-label">إجمالي المستلَم</div>
      <div class="s-value" style="color:#16a34a">{{ $stockReceivingInvoice->total_items_received }}</div>
    </div>
    <div class="summary-box">
      <div class="s-label">تالف / ناقص</div>
      <div class="s-value" style="color:{{ $stockReceivingInvoice->total_items_damaged > 0 ? '#dc2626' : '#16a34a' }}">
        {{ $stockReceivingInvoice->total_items_damaged }}
      </div>
    </div>
  </div>

  {{-- Items Table --}}
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>المنتج</th>
        <th>SKU</th>
        <th>الكمية المرسَلة</th>
        <th>الكمية المستلَمة</th>
        <th>الفرق</th>
      </tr>
    </thead>
    <tbody>
      @foreach($stockReceivingInvoice->stockTransfer?->items ?? [] as $i => $item)
      @php $diff = $item->sent_quantity - $item->received_quantity; @endphp
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $item->product?->name }}</td>
        <td style="color:#888">{{ $item->product?->sku ?? '—' }}</td>
        <td>{{ $item->sent_quantity }}</td>
        <td>{{ $item->received_quantity }}</td>
        <td class="{{ $diff == 0 ? 'diff-ok' : 'diff-bad' }}">
          {{ $diff == 0 ? '✓ مطابق' : ($diff > 0 ? '-'.$diff : '+'.$diff) }}
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>

  @if($stockReceivingInvoice->notes)
  <div style="background:#fffdf5;border:1px solid #e8e0c8;border-radius:4px;padding:10px 14px;margin-bottom:14px;font-size:12px">
    <strong>ملاحظات:</strong> {{ $stockReceivingInvoice->notes }}
  </div>
  @endif

  {{-- Signatures --}}
  <div class="sig-section">
    <div class="sig-box">
      <div class="sig-line"></div>
      <div class="sig-label">توقيع المستلِم<br>{{ $stockReceivingInvoice->receivedBy?->display_name ?? 'غير مسجل' }}</div>
    </div>
    <div class="sig-box">
      <div class="sig-line"></div>
      <div class="sig-label">ختم حلويات دهب</div>
    </div>
  </div>

  <div class="inv-footer">
    تم إنشاء هذه الوثيقة تلقائياً بواسطة نظام حلويات دهب الذكي — {{ now()->format('Y/m/d H:i') }}
  </div>
</div>

<script>window.onload = function(){ window.print(); }</script>
</body>
</html>
