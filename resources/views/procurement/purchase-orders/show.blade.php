@extends('layouts.app')
@section('title', 'أمر شراء')
@section('content')
    @include('procurement.partials.flash')
    <div class="page-actions">
        <div class="page-actions-title">{{ $purchaseOrder->purchase_order_number }} <small style="font-weight:400">—
                {{ $purchaseOrder->statusLabel() }}</small></div>
        <div class="action-btns"><a class="btn btn-ghost" href="{{ route('purchase-orders.index') }}">رجوع</a>
            @if ($purchaseOrder->statusValue() === 'draft')
                @can('purchase_orders.update')
                    <a class="btn btn-outline" href="{{ route('purchase-orders.edit', $purchaseOrder) }}">تعديل</a>
                    <form action="{{ route('purchase-orders.submit', $purchaseOrder) }}" method="POST" style="display:inline">
                        @csrf<button class="btn btn-gold">إرسال للاعتماد</button></form>
                @endcan
                @endif
                @if ($purchaseOrder->statusValue() === 'submitted')
                    @can('purchase_orders.approve')
                        <form action="{{ route('purchase-orders.approve', $purchaseOrder) }}" method="POST"
                            style="display:inline">@csrf<button class="btn btn-gold">اعتماد</button></form>
                    @endcan
                    @endif
                    @if (in_array($purchaseOrder->statusValue(), ['approved', 'partially_received']))
                        @can('goods_receipts.create')
                            <a class="btn btn-gold"
                                href="{{ route('goods-receipts.create', ['purchase_order_id' => $purchaseOrder->id]) }}">استلام
                                بضاعة</a>
                        @endcan
                    @endif
        </div>
    </div>
    <div class="dashboard-row">
        <div class="card">
            <div class="card-header"><span class="card-title">بيانات الأمر</span></div>
            <div class="card-body">
                <div class="detail-list">
                    <div class="detail-row"><span class="detail-label">المورد</span><span
                            class="detail-value">{{ $purchaseOrder->supplier?->name }}</span></div>
                    <div class="detail-row"><span class="detail-label">الموقع</span><span
                            class="detail-value">{{ $purchaseOrder->location?->name }}</span></div>
                    <div class="detail-row"><span class="detail-label">تاريخ الأمر</span><span
                            class="detail-value">{{ $purchaseOrder->order_date?->format('Y/m/d') }}</span></div>
                    <div class="detail-row"><span class="detail-label">التسليم المتوقع</span><span
                            class="detail-value">{{ $purchaseOrder->expected_delivery_date?->format('Y/m/d') ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">الإجماليات</span></div>
            <div class="card-body">
                <div class="detail-list">
                    <div class="detail-row"><span class="detail-label">الإجمالي الفرعي</span><span
                            class="detail-value">{{ number_format($purchaseOrder->subtotal, 2) }}</span></div>
                    <div class="detail-row"><span class="detail-label">الخصم</span><span
                            class="detail-value">{{ number_format($purchaseOrder->discount_amount, 2) }}</span></div>
                    <div class="detail-row"><span class="detail-label">الشحن والضريبة</span><span
                            class="detail-value">{{ number_format($purchaseOrder->shipping_cost + $purchaseOrder->tax_amount, 2) }}</span>
                    </div>
                    <div class="detail-row"><span class="detail-label"><strong>الإجمالي</strong></span><span
                            class="detail-value"><strong>{{ number_format($purchaseOrder->grand_total, 2) }}
                                {{ $purchaseOrder->currency?->displayName() }}</strong></span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="table-wrap" style="margin-top:1rem">
        <table class="data-table">
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th>المطلوب</th>
                    <th>المستلم</th>
                    <th>المتبقي</th>
                    <th>سعر الوحدة</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchaseOrder->items as $item)
                    <tr>
                        <td>{{ $item->product?->name_ar ?: $item->product?->name }}</td>
                        <td>{{ number_format($item->ordered_quantity, 3) }}</td>
                        <td>{{ number_format($item->received_quantity, 3) }}</td>
                        <td>{{ number_format($item->remainingQuantity(), 3) }}</td>
                        <td>{{ number_format($item->unit_price, 4) }}</td>
                        <td>{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if (!in_array($purchaseOrder->statusValue(), ['received', 'cancelled']))
        @can('purchase_orders.cancel')
            <form action="{{ route('purchase-orders.cancel', $purchaseOrder) }}" method="POST" style="margin-top:1rem">
                @csrf<input class="form-input" name="reason" placeholder="سبب الإلغاء (اختياري)"
                    style="max-width:500px;display:inline-block"><button class="btn btn-ghost" style="color:var(--error)">إلغاء
                    أمر الشراء</button></form>
        @endcan
    @endif
@endsection
