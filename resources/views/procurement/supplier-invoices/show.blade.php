@extends('layouts.app')
@section('title', 'فاتورة مورد')
@section('content')
    @include('procurement.partials.flash')
   <div class="page-actions">

    <div class="page-actions-title">
        فاتورة المورد {{ $supplierInvoice->invoice_number }}

        <small style="font-weight:400">
            — {{ $supplierInvoice->statusLabel() }}
        </small>
    </div>


    <div class="action-btns">

        {{-- رجوع --}}
        <a
            class="btn btn-ghost"
            href="{{ route('supplier-invoices.index') }}"
        >
            رجوع
        </a>


        {{-- طباعة الفاتورة --}}
        <a
            class="btn btn-gold"
            href="{{ route('supplier-invoices.print', $supplierInvoice) }}"
            target="_blank"
        >
            🖨️ طباعة الفاتورة
        </a>


        {{-- تسجيل دفعة --}}
        @if ($supplierInvoice->isPayable())

            @can('supplier_payments.create')

                <a
                    class="btn btn-gold"
                    href="{{ route(
                        'supplier-payments.create',
                        [
                            'supplier_invoice_id' => $supplierInvoice->id
                        ]
                    ) }}"
                >
                    تسجيل دفعة
                </a>

            @endcan

        @endif


        {{-- إلغاء الفاتورة --}}
        @if ($supplierInvoice->statusValue() !== 'cancelled')

            @can('supplier_invoices.cancel')

                <form
                    action="{{ route(
                        'supplier-invoices.cancel',
                        $supplierInvoice
                    ) }}"
                    method="POST"
                    style="display:inline"
                    onsubmit="return confirm('هل أنت متأكد من إلغاء الفاتورة؟')"
                >
                    @csrf

                    <button
                        type="submit"
                        class="btn btn-ghost"
                        style="color:var(--error)"
                    >
                        إلغاء الفاتورة
                    </button>

                </form>

            @endcan

        @endif

    </div>

</div>
    <div class="dashboard-row">
        <div class="card">
            <div class="card-header"><span class="card-title">بيانات الفاتورة</span></div>
            <div class="card-body">
                <div class="detail-list">
                    <div class="detail-row"><span class="detail-label">المورد</span><span class="detail-value"><a
                                href="{{ route('suppliers.show', $supplierInvoice->supplier) }}">{{ $supplierInvoice->supplier?->name }}</a></span>
                    </div>
                    <div class="detail-row"><span class="detail-label">الموقع</span><span
                            class="detail-value">{{ $supplierInvoice->location?->name }}</span></div>
                    <div class="detail-row"><span class="detail-label">التاريخ</span><span
                            class="detail-value">{{ $supplierInvoice->invoice_date?->format('Y/m/d') }}</span></div>
                    <div class="detail-row"><span class="detail-label">الاستحقاق</span><span
                            class="detail-value">{{ $supplierInvoice->due_date?->format('Y/m/d') ?? '—' }}</span></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">الرصيد</span></div>
            <div class="card-body">
                <div class="detail-list">
                    <div class="detail-row"><span class="detail-label">الإجمالي</span><span
                            class="detail-value">{{ number_format($supplierInvoice->grand_total, 2) }}
                            {{ $supplierInvoice->currency?->displayName() }}</span></div>
                    <div class="detail-row"><span class="detail-label">المرتجعات المطبقة</span><span
                            class="detail-value">{{ number_format($supplierInvoice->credited_amount, 2) }}</span></div>
                    <div class="detail-row"><span class="detail-label">الدفعات</span><span
                            class="detail-value">{{ number_format($supplierInvoice->paid_amount, 2) }}</span></div>
                    <div class="detail-row"><span class="detail-label"><strong>المتبقي</strong></span><span
                            class="detail-value"><strong>{{ number_format($supplierInvoice->remaining_amount, 2) }}</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="table-wrap" style="margin-top:1rem">
        <table class="data-table">
            <thead>
                <tr>
                    <th>البند</th>
                    <th>الكمية</th>
                    <th>السعر</th>
                    <th>الخصم</th>
                    <th>الضريبة</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($supplierInvoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ number_format($item->quantity, 3) }}</td>
                        <td>{{ number_format($item->unit_price, 4) }}</td>
                        <td>{{ number_format($item->discount_amount, 2) }}</td>
                        <td>{{ number_format($item->tax_amount, 2) }}</td>
                        <td>{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="dashboard-row" style="margin-top:1rem">
        <div class="card">
            <div class="card-header"><span class="card-title">الدفعات</span></div>
            <div class="card-body">
                @forelse($supplierInvoice->payments as $payment)
                    <div style="padding:.45rem 0;border-bottom:1px solid var(--border-light)">
                        <strong>{{ $payment->payment_number }}</strong> — {{ number_format($payment->applied_amount, 2) }}
                        {{ $supplierInvoice->currency?->displayName() }}<br><small>{{ $payment->payment_date?->format('Y/m/d') }} —
                            {{ $payment->paymentMethod?->name_ar }}</small>
                </div>@empty<div class="empty-state-sm">لا توجد
                        دفعات.</div>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">المرتجعات</span></div>
            <div class="card-body">
                @forelse($supplierInvoice->purchaseReturns as $return)
                    <div style="padding:.45rem 0;border-bottom:1px solid var(--border-light)"><a
                            href="{{ route('purchase-returns.show', $return) }}">{{ $return->return_number }}</a> —
                        {{ number_format($return->grand_total, 2) }}<br><small>{{ $return->statusLabel() }}</small></div>
                @empty<div class="empty-state-sm">لا توجد مرتجعات مرتبطة.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
