@extends('layouts.app')

@section('title', 'المبيعات البنكية')
@section('page-title', 'المبيعات البنكية')

@section('content')
<div class="page-actions bank-sales-header">
    <div>
        <div class="page-actions-title">المبيعات البنكية والإلكترونية</div>
        <div class="text-muted bank-sales-subtitle">
            سجل مستقل للحوالات البنكية والمحافظ الإلكترونية مع بيانات العميل والحساب والإثبات والاعتماد.
        </div>
    </div>

    <a href="{{ route('payments.index') }}" class="btn btn-ghost btn-sm">
        الحركات المالية
    </a>
</div>

<div class="stats-grid bank-sales-stats">
    <div class="stat-card stat-blue">
        <div class="stat-info">
            <div class="stat-value">{{ (int) ($summary->transfers_count ?? 0) }}</div>
            <div class="stat-label">عدد الحوالات</div>
        </div>
    </div>

    <div class="stat-card stat-green">
        <div class="stat-info">
            <div class="stat-value">₪{{ number_format((float) ($summary->confirmed_total ?? 0), 2) }}</div>
            <div class="stat-label">الحوالات المعتمدة</div>
        </div>
    </div>

    <div class="stat-card stat-gold">
        <div class="stat-info">
            <div class="stat-value">₪{{ number_format((float) ($summary->pending_total ?? 0), 2) }}</div>
            <div class="stat-label">بانتظار التحقق</div>
        </div>
    </div>

    <div class="stat-card stat-red">
        <div class="stat-info">
            <div class="stat-value">{{ (int) ($summary->missing_proof_count ?? 0) }}</div>
            <div class="stat-label">بدون إثبات مرفق</div>
        </div>
    </div>
</div>

<div class="filter-row bank-sales-filters">
    <form method="GET" action="{{ route('payments.bank-sales') }}" class="filter-grid">
        <div class="filter-group bank-search">
            <label class="filter-label">بحث</label>
            <input
                type="search"
                name="search"
                value="{{ request('search') }}"
                class="form-input"
                placeholder="العميل، الهاتف، رقم الطلب، المرجع أو الحساب"
            >
        </div>

        <div class="filter-group">
            <label class="filter-label">الحالة</label>
            <select name="status" class="form-select">
                <option value="">كل الحالات</option>
                @foreach($statusOptions as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                        {{ match($status->value) {
                            'pending_verification' => 'بانتظار التحقق',
                            'confirmed' => 'مؤكدة',
                            'rejected' => 'مرفوضة',
                            'corrected' => 'مصححة',
                            'refunded' => 'مستردة',
                            default => $status->value,
                        } }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">طريقة الدفع</label>
            <select name="payment_method_id" class="form-select">
                <option value="">كل الطرق البنكية</option>
                @foreach($paymentMethods as $method)
                    <option value="{{ $method->id }}" @selected((string) request('payment_method_id') === (string) $method->id)>
                        {{ $method->name_ar ?: $method->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">حساب الاستلام</label>
            <select name="location_payment_account_id" class="form-select">
                <option value="">كل الحسابات</option>
                @foreach($paymentAccounts as $account)
                    <option value="{{ $account->id }}" @selected((string) request('location_payment_account_id') === (string) $account->id)>
                        {{ $account->name }}
                        @if($account->provider_name)
                            — {{ $account->provider_name }}
                        @endif
                    </option>
                @endforeach
            </select>
        </div>

        @if($locations->isNotEmpty())
            <div class="filter-group">
                <label class="filter-label">الفرع</label>
                <select name="location_id" class="form-select">
                    <option value="">كل الفروع</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="filter-group">
            <label class="filter-label">من تاريخ</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input">
        </div>

        <div class="filter-group">
            <label class="filter-label">إلى تاريخ</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input">
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-gold btn-sm">تطبيق</button>
            <a href="{{ route('payments.bank-sales') }}" class="btn btn-ghost btn-sm">مسح</a>
        </div>
    </form>
</div>

<div class="table-wrap bank-sales-table-wrap">
    <table class="data-table bank-sales-table">
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>المحوّل</th>
                <th>الطلب</th>
                <th>الفرع</th>
                <th>طريقة الدفع</th>
                <th>حساب الاستلام</th>
                <th>المبلغ</th>
                <th>المرجع</th>
                <th>إثبات الدفع</th>
                <th>الحالة والاعتماد</th>
                <th>المستلم</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $payment)
                @php
                    $statusValue = $payment->status?->value ?? (string) $payment->status;
                    $orderType = $payment->order_type?->value ?? (string) $payment->order_type;
                    $linkedOrder = $payment->relationLoaded('linkedOrder')
                        ? $payment->getRelation('linkedOrder')
                        : null;
                    $linkedInvoice = $payment->relationLoaded('linkedInvoice')
                        ? $payment->getRelation('linkedInvoice')
                        : null;
                    $customer = $linkedOrder?->customer;
                    $account = $payment->locationPaymentAccount;
                    $accountNumber = $account?->iban
                        ?: ($account?->account_number ?: $account?->phone_number);
                    $orderLink = $orderType === 'special_cake_order'
                        ? route('cake-orders.show', $payment->order_id)
                        : route('orders.show', $payment->order_id);
                    $statusMeta = match($statusValue) {
                        'confirmed' => ['badge-active', 'مؤكدة'],
                        'pending_verification' => ['badge-warning', 'بانتظار التحقق'],
                        'rejected' => ['badge-inactive', 'مرفوضة'],
                        'corrected' => ['badge-info', 'مصححة'],
                        'refunded' => ['badge-secondary', 'مستردة'],
                        default => ['badge-secondary', $statusValue ?: 'غير محددة'],
                    };
                @endphp

                <tr>
                    <td>
                        <strong>{{ $payment->paid_at?->format('Y-m-d') ?? '—' }}</strong>
                        <small class="bank-muted">{{ $payment->paid_at?->format('H:i') }}</small>
                    </td>

                    <td>
                        <strong>
                            {{ $payment->sender_name ?: ($customer?->name ?? 'غير محدد') }}
                        </strong>
                        <small class="bank-muted" dir="ltr">
                            {{ $payment->sender_phone ?: ($customer?->phone ?: '—') }}
                        </small>
                        @if($payment->sender_account_number)
                            <small class="bank-muted" dir="ltr">
                                {{ $payment->sender_account_number }}
                            </small>
                        @endif
                    </td>

                    <td>
                        <a href="{{ $orderLink }}" class="link-gold bank-order-number">
                            {{ $linkedOrder?->order_number ?? ('#' . $payment->order_id) }}
                        </a>
                        <small class="bank-muted">
                            {{ $orderType === 'special_cake_order' ? 'كيك خاص' : 'طلب عادي' }}
                        </small>
                        @if($linkedInvoice)
                            <a href="{{ route('invoices.show', $linkedInvoice) }}" class="bank-inline-link">
                                {{ $linkedInvoice->invoice_number }}
                            </a>
                        @endif
                    </td>

                    <td>{{ $payment->location?->name ?? '—' }}</td>

                    <td>
                        <strong>
                            {{ $payment->paymentMethod?->name_ar
                                ?: ($payment->paymentMethod?->name ?? '—') }}
                        </strong>
                    </td>

                    <td>
                        @if($account)
                            <div class="bank-account">
                                <strong>{{ $account->account_holder_name ?: $account->name }}</strong>
                                <small>{{ $account->provider_name ?: $account->name }}</small>
                                <span dir="ltr">{{ $accountNumber ?: 'بدون رقم' }}</span>
                            </div>
                        @else
                            <span class="bank-warning">الحساب غير مربوط</span>
                        @endif
                    </td>

                    <td>
                        <strong class="bank-amount">₪{{ number_format((float) $payment->amount, 2) }}</strong>
                    </td>

                    <td>
                        <span class="bank-reference" dir="ltr">
                            {{ $payment->reference_number ?: '—' }}
                        </span>
                    </td>

                    <td>
                        @if($payment->payment_proof)
                            <a
                                href="{{ route('payments.proof', $payment) }}"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-outline btn-xs"
                            >
                                عرض الإثبات
                            </a>
                        @else
                            <span class="bank-warning">غير مرفق</span>
                        @endif
                    </td>

                    <td>
                        <div class="bank-status">
                            <span class="badge {{ $statusMeta[0] }}">{{ $statusMeta[1] }}</span>

                            @if($payment->verifiedBy)
                                <small>
                                    {{ $payment->verifiedBy->display_name }}
                                    @if($payment->verified_at)
                                        · {{ $payment->verified_at->format('Y-m-d H:i') }}
                                    @endif
                                </small>
                            @endif

                            @if($statusValue === 'rejected' && $payment->rejection_reason)
                                <small class="bank-warning">{{ $payment->rejection_reason }}</small>
                            @endif
                        </div>
                    </td>

                    <td>{{ $payment->receivedBy?->display_name ?? '—' }}</td>

                    <td>
                        <div class="bank-actions">
                            @if($statusValue === 'pending_verification')
                                @can('payments.verify')
                                    <form method="POST" action="{{ route('payments.verify', $payment) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="verify">
                                        <button type="submit" class="btn btn-success btn-xs" onclick="return confirm('تأكيد الحوالة؟')">
                                            اعتماد
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('payments.verify', $payment) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="rejection_reason" value="تم رفض إثبات الحوالة من صفحة المبيعات البنكية">
                                        <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('رفض الحوالة؟')">
                                            رفض
                                        </button>
                                    </form>
                                @endcan
                            @else
                                <a href="{{ $orderLink }}" class="btn btn-ghost btn-xs">الطلب</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12">
                        <div class="empty-state-sm">
                            لا توجد حوالات بنكية أو مدفوعات إلكترونية تطابق الفلاتر.
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($payments->hasPages())
    <div style="margin-top:1rem">{{ $payments->links() }}</div>
@endif
@endsection

@push('styles')
<style>
.bank-sales-header {
    align-items: flex-start;
}

.bank-sales-subtitle {
    margin-top: .25rem;
    font-size: .78rem;
}

.bank-sales-stats {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin-bottom: 1.2rem;
}

.bank-sales-stats .stat-card {
    padding: .9rem 1rem;
}

.bank-sales-stats .stat-value {
    font-size: 1.12rem;
}

.bank-sales-filters {
    margin-bottom: 1rem;
}

.bank-sales-filters .filter-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.bank-sales-filters .bank-search {
    grid-column: span 2;
}

.filter-actions {
    display: flex;
    align-items: flex-end;
    gap: .5rem;
}

.bank-sales-table {
    min-width: 1580px;
}

.bank-sales-table td {
    vertical-align: top;
}

.bank-sales-table td > strong,
.bank-sales-table td > a {
    display: block;
}

.bank-muted,
.bank-status small,
.bank-account small {
    display: block;
    margin-top: .18rem;
    color: var(--text-muted);
    font-size: .67rem;
    line-height: 1.5;
}

.bank-order-number,
.bank-inline-link {
    font-weight: 800;
    text-decoration: none;
}

.bank-inline-link {
    display: inline-block !important;
    margin-top: .3rem;
    color: var(--gold);
    font-size: .68rem;
}

.bank-account,
.bank-status {
    display: grid;
    gap: .18rem;
    min-width: 150px;
}

.bank-account span {
    font-size: .72rem;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.bank-amount {
    color: #16845b;
    white-space: nowrap;
}

.bank-reference {
    display: block;
    max-width: 130px;
    overflow-wrap: anywhere;
}

.bank-warning {
    color: #b42318;
    font-size: .7rem;
    font-weight: 800;
}

.bank-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}

.bank-actions form {
    margin: 0;
}

@media(max-width:1100px) {
    .bank-sales-stats,
    .bank-sales-filters .filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width:650px) {
    .bank-sales-stats,
    .bank-sales-filters .filter-grid {
        grid-template-columns: 1fr;
    }

    .bank-sales-filters .bank-search {
        grid-column: auto;
    }
}
</style>
@endpush
