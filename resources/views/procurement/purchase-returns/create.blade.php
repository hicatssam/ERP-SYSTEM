@extends('layouts.app')
@section('title', 'إنشاء مرتجع مورد')
@section('content')
    <div class="page-header">
        <h1 class="page-heading">إنشاء مرتجع مورد</h1>
        <p class="page-subheading"><a href="{{ route('purchase-returns.index') }}">المرتجعات</a> &laquo; جديد</p>
    </div>
    @include('procurement.partials.flash')
    <div class="card" style="margin-bottom:1rem">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:.75rem;align-items:end">
                <div class="form-group" style="min-width:320px"><label class="form-label">سند الاستلام
                        المُرحّل</label><select class="form-input" name="goods_receipt_id">
                        <option value="">اختر سند استلام</option>
                        @foreach ($receipts as $receipt)
                            <option value="{{ $receipt->id }}" @selected($selectedReceipt?->id === $receipt->id)>{{ $receipt->receipt_number }}
                                — {{ $receipt->supplier?->name }} — {{ $receipt->location?->name }}</option>
                        @endforeach
                    </select>
                </div><button class="btn btn-outline">تحميل البنود</button>
            </form>
        </div>
    </div>
    @if ($selectedReceipt)
        <form method="POST" action="{{ route('purchase-returns.store') }}">@csrf<input type="hidden"
                name="goods_receipt_id" value="{{ $selectedReceipt->id }}">
            <div class="card">
                <div class="card-body">
                    <div
                        style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1rem">
                        <div class="form-group"><label class="form-label">المورد</label><input class="form-input" disabled
                                value="{{ $selectedReceipt->supplier?->name }}"></div>
                        <div class="form-group"><label class="form-label">فاتورة المورد لتخفيض رصيدها
                                (اختياري)</label><select class="form-input" name="supplier_invoice_id">
                                <option value="">بدون ربط مالي الآن</option>
                                @foreach ($invoices as $invoice)
                                    <option value="{{ $invoice->id }}" @selected((string) old('supplier_invoice_id') === (string) $invoice->id)>
                                        {{ $invoice->invoice_number }} — المتبقي
                                        {{ number_format($invoice->remaining_amount, 2) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">تاريخ المرتجع</label><input class="form-input"
                                type="datetime-local" name="returned_at"
                                value="{{ old('returned_at', now()->format('Y-m-d\\TH:i')) }}"></div>
                    </div>
                    <div class="form-group"><label class="form-label">ملاحظات</label>
                        <textarea class="form-input" name="notes">{{ old('notes') }}</textarea>
                    </div>
                    <p style="font-size:.83rem;color:var(--text-muted)">اترك الكمية صفراً للأصناف التي لن تعيدها.</p>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>المنتج</th>
                                    <th>المقبول في الاستلام</th>
                                    <th>الدفعة</th>
                                    <th>كمية المرتجع</th>
                                    <th>سبب المرتجع</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($selectedReceipt->items as $index => $item)
                                    <tr>
                                        <td>{{ $item->product?->name_ar ?: $item->product?->name }}<input type="hidden"
                                                name="items[{{ $index }}][goods_receipt_item_id]"
                                                value="{{ $item->id }}"></td>
                                        <td>{{ number_format($item->accepted_quantity, 3) }}</td>
                                        <td>{{ $item->batch?->batch_number ?: '—' }}@if ($item->batch)
                                                <input type="hidden" name="items[{{ $index }}][inventory_batch_id]"
                                                    value="{{ $item->batch->id }}">
                                            @endif
                                        </td>
                                        <td><input class="form-input" type="number" min="0"
                                                max="{{ $item->accepted_quantity }}" step="0.001"
                                                name="items[{{ $index }}][return_quantity]"
                                                value="{{ old("items.$index.return_quantity", 0) }}"></td>
                                        <td><input class="form-input" name="items[{{ $index }}][reason]"
                                                value="{{ old("items.$index.reason") }}"
                                                placeholder="مثال: تلف، خطأ توريد"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:.75rem;margin-top:1rem"><button class="btn btn-gold">حفظ كمسودة</button><a
                    class="btn btn-ghost" href="{{ route('purchase-returns.index') }}">إلغاء</a></div>
        </form>
    @endif
@endsection
