@extends('layouts.app')
@section('title', 'فاتورة: ' . $invoice->invoice_number)

@section('content')
<div class="page-actions">
    <div class="page-actions-title">{{ $invoice->invoice_number }}</div>
    <div class="action-btns">
        @can('invoices.print')
            <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="btn btn-outline btn-sm">طباعة</a>
        @endcan

        @can('invoices.cancel')
            @if($invoice->isActive())
                <button
                    class="btn btn-ghost btn-sm"
                    style="color:var(--error)"
                    onclick="document.getElementById('cancelForm').style.display='block'"
                >
                    إلغاء الفاتورة
                </button>
            @endif
        @endcan

        <a href="{{ route('invoices.index') }}" class="btn btn-ghost btn-sm">رجوع</a>
    </div>
</div>

<div class="dashboard-row">
    <div class="card">
        <div class="card-header">
            <span class="card-title">بيانات الفاتورة</span>
        </div>
        <div class="card-body">
            <table class="data-table">
                <tr>
                    <td style="color:var(--text-muted)">رقم الفاتورة</td>
                    <td>{{ $invoice->invoice_number }}</td>
                </tr>
                <tr>
                    <td style="color:var(--text-muted)">الفرع</td>
                    <td>{{ $invoice->location?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="color:var(--text-muted)">العميل</td>
                    <td>@if($invoice->customer)<a href="{{ route('customers.show',$invoice->customer) }}">{{ $invoice->customer->name }}</a>@else عميل نقدي @endif</td>
                </tr>
                <tr>
                    <td style="color:var(--text-muted)">النوع</td>
                    <td>{{ $invoice->invoiceTypeValue() }}</td>
                </tr>
                <tr>
                    <td style="color:var(--text-muted)">الإجمالي</td>
                    <td><strong>₪{{ number_format((float) $invoice->total_amount, 2) }}</strong></td>
                </tr>
                <tr>
                    <td style="color:var(--text-muted)">المدفوع</td>
                    <td>₪{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                </tr>
                <tr>
                    <td style="color:var(--text-muted)">المتبقي</td>
                    <td style="color: {{ (float) $invoice->remaining_amount > 0 ? 'var(--error)' : 'var(--success)' }}">
                        ₪{{ number_format((float) $invoice->remaining_amount, 2) }}
                    </td>
                </tr>
                <tr>
                    <td style="color:var(--text-muted)">حالة السداد</td>
                    <td>
                        <span class="badge {{ $invoice->paymentStatusBadgeClass() }}">
                            {{ $invoice->paymentStatusLabel() }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="color:var(--text-muted)">حالة الفاتورة</td>
                    <td>
                        <span class="badge {{ $invoice->statusBadgeClass() }}">
                            {{ $invoice->statusLabel() }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="color:var(--text-muted)">تاريخ الإصدار</td>
                    <td>{{ $invoice->issued_at?->format('Y-m-d H:i') }}</td>
                </tr>
                <tr>
                    <td style="color:var(--text-muted)">تاريخ الاستحقاق</td>
                    <td>
                        {{ $invoice->due_at?->format('Y-m-d') ?? '—' }}
                        @if($invoice->due_at && $invoice->due_at->isPast() && (float)$invoice->remaining_amount > 0)
                            <span class="badge badge-inactive" style="margin-inline-start:.4rem">متأخرة</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">البنود</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>المنتج/الوصف</th>
                        <th>الكمية</th>
                        <th>سعر الوحدة</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>₪{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>₪{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@can('invoices.cancel')
    @if($invoice->isActive())
        <div class="card mt-4" id="cancelForm" style="display:none;max-width:500px">
            <div class="card-header">
                <span class="card-title">إلغاء الفاتورة</span>
            </div>
            <div class="card-body">
                <form action="{{ route('invoices.cancel', $invoice) }}" method="POST">
                    @csrf
                 
                    <div style="display:grid;gap:1rem">
                        <div class="form-group">
                            <label class="form-label">سبب الإلغاء *</label>
                            <textarea name="cancellation_reason" class="form-textarea" required>{{ old('cancellation_reason') }}</textarea>
                            @error('cancellation_reason')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div style="display:flex;gap:.75rem">
                            <button class="btn btn-danger" type="submit">تأكيد الإلغاء</button>
                            <button
                                type="button"
                                class="btn btn-ghost"
                                onclick="document.getElementById('cancelForm').style.display='none'"
                            >
                                تراجع
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endcan
@endsection
