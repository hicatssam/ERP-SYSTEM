@extends('layouts.app')
@section('title', 'دفعات الموردين')
@section('content')
    @include('procurement.partials.flash')
    <style>
    .supplier-payment-method-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .875rem;
    }

    .supplier-payment-method-card {
        position: relative;
        min-height: 96px;
        display: flex;
        align-items: center;
        gap: .8rem;
        padding: .9rem 1rem;
        border: 1px solid var(--border-light);
        border-radius: var(--radius);
        background: var(--surface);
        cursor: pointer;
        transition: .18s ease;
        text-align: right;
    }

    .supplier-payment-method-card:hover {
        border-color: var(--gold);
        transform: translateY(-2px);
    }

    .supplier-payment-method-card:has(input:checked) {
        border-color: var(--gold);
        background: var(--gold-ultra);
        box-shadow: 0 0 0 2px rgba(212, 160, 23, .13);
    }

    .supplier-payment-method-card input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .supplier-payment-method-logo,
    .supplier-payment-method-fallback {
        width: 58px;
        height: 58px;
        flex-shrink: 0;
        border-radius: 12px;
    }

    .supplier-payment-method-logo {
        object-fit: contain;
        padding: .35rem;
        background: #fff;
        border: 1px solid var(--border-light);
    }

    .supplier-payment-method-fallback {
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--gold-ultra);
        color: var(--gold-deep);
        font-size: 1.25rem;
        font-weight: 800;
    }

    .supplier-payment-method-name {
        display: block;
        color: var(--text);
        font-size: .95rem;
        font-weight: 800;
    }

    .supplier-payment-method-subtitle {
        display: block;
        margin-top: .18rem;
        color: var(--text-muted);
        font-size: .75rem;
        direction: ltr;
        text-align: right;
    }

    .supplier-payment-method-check {
        display: none;
        position: absolute;
        top: .55rem;
        left: .55rem;
        width: 21px;
        height: 21px;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--gold);
        color: #fff;
        font-size: .75rem;
        font-weight: 800;
    }

    .supplier-payment-method-card:has(input:checked) .supplier-payment-method-check {
        display: flex;
    }

    @media (max-width: 900px) {
        .supplier-payment-method-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 560px) {
        .supplier-payment-method-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
    <div class="page-actions">
        <div class="page-actions-title">دفعات الموردين</div>
        @can('supplier_payments.create')
            <a class="btn btn-gold" href="{{ route('supplier-payments.create') }}">تسجيل دفعة مورد</a>
        @endcan
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>رقم الدفعة</th>
                    <th>المورد</th>
                    <th>الفاتورة</th>
                    <th>التاريخ</th>
                    <th>المبلغ المدفوع</th>
                    <th>المبلغ المطبق</th>
                    <th>طريقة الدفع</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>{{ $payment->payment_number }}</td>
                        <td>{{ $payment->supplier?->name }}</td>
                        <td><a
                                href="{{ route('supplier-invoices.show', $payment->supplierInvoice) }}">{{ $payment->supplierInvoice?->invoice_number }}</a>
                        </td>
                        <td>{{ $payment->payment_date?->format('Y/m/d') }}</td>
                        <td>{{ number_format($payment->amount, 2) }} {{ $payment->currency?->displayName() }}</td>
                        <td>{{ number_format($payment->applied_amount, 2) }}</td>
                        <td>{{ $payment->paymentMethod?->name_ar }}</td>
                </tr>@empty<tr>
                        <td colspan="7">
                            <div class="empty-state-sm">لا توجد دفعات موردين.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:1rem">{{ $payments->links() }}</div>
@endsection
