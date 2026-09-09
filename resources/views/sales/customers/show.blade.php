@extends('layouts.app')
@section('title', $customer->name)
@section('content')
<div class="page-actions">
    <div>
        <div class="page-actions-title">{{ $customer->name }}</div>
        <div class="customer-subtitle">{{ $customer->typeLabel() }} · {{ $customer->scopeLabel() }} @if($statementLocationId) · عرض الفرع المحدد @endif</div>
    </div>
    <div class="action-btns">
        <a href="{{ route('customers.statement', ['customer' => $customer, 'location_id' => $statementLocationId]) }}" class="btn btn-gold btn-sm">كشف الحساب</a>
        <a href="{{ route('customers.statement.pdf', ['customer' => $customer, 'location_id' => $statementLocationId]) }}" class="btn btn-outline btn-sm">PDF</a>
        @if(auth()->user()->can('customers.update') && (! $customer->isCentral() || auth()->user()->isAdmin() || auth()->user()->can('customers.view_all') || auth()->user()->can('financial.global.view')))
            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline btn-sm">تعديل</a>
        @endif
        <a href="{{ route('customers.index') }}" class="btn btn-ghost btn-sm">رجوع</a>
    </div>
</div>

@if($locations->isNotEmpty())
<div class="filter-row">
    <form method="GET" class="customer-location-filter">
        <div class="filter-group">
            <label class="filter-label">عرض الحساب</label>
            <select name="location_id" class="form-select" onchange="this.form.submit()">
                <option value="">الحساب الموحد — كل الفروع</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) $statementLocationId === (string) $location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
    </form>
</div>
@endif

<div class="customer-stats">
    <div class="stat-card"><span>الرصيد المستحق</span><strong class="danger">₪{{ number_format((float) $accountSummary['balance'], 2) }}</strong></div>
    <div class="stat-card"><span>إجمالي الفواتير</span><strong>₪{{ number_format((float) $accountSummary['invoiced'], 2) }}</strong></div>
    <div class="stat-card"><span>المدفوع على الفواتير</span><strong>₪{{ number_format((float) $accountSummary['paid'], 2) }}</strong></div>
    <div class="stat-card"><span>المتأخر</span><strong class="danger">₪{{ number_format((float) $accountSummary['overdue'], 2) }}</strong></div>
    <div class="stat-card"><span>عدد الطلبات</span><strong>{{ number_format((int) $accountSummary['orders_count']) }}</strong></div>
    <div class="stat-card"><span>رصيد دائن غير موزع</span><strong class="success">₪{{ number_format((float) $accountSummary['unallocated_credit'], 2) }}</strong></div>
</div>

<div class="customer-layout">
    <div class="card">
        <div class="card-header"><span class="card-title">بيانات العميل</span></div>
        <div class="card-body">
            <div class="detail-grid">
                <div><span>الاسم</span><strong>{{ $customer->name }}</strong></div>
                <div><span>الهاتف</span><strong>{{ $customer->phone }}</strong></div>
                <div><span>هاتف بديل</span><strong>{{ $customer->secondary_phone ?: '—' }}</strong></div>
                <div><span>الفرع المرجعي</span><strong>{{ $customer->location?->name ?? '—' }}</strong></div>
                <div><span>نوع العميل</span><strong>{{ $customer->typeLabel() }}</strong></div>
                <div><span>النطاق</span><strong>{{ $customer->scopeLabel() }}</strong></div>
                <div><span>المسؤول</span><strong>{{ $customer->contact_person ?: '—' }}</strong></div>
                <div><span>الرقم التعريفي</span><strong>{{ $customer->tax_number ?: '—' }}</strong></div>
                <div class="wide"><span>العنوان</span><strong>{{ $customer->address ?: '—' }}</strong></div>
                @if($customer->scope === 'selected')
                    <div class="wide"><span>الفروع المسموحة</span><strong>{{ $customer->locations->pluck('name')->join('، ') ?: '—' }}</strong></div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">سياسة الحساب</span></div>
        <div class="card-body">
            <div class="detail-grid one-col">
                <div><span>البيع على الحساب</span><strong>{{ $customer->allow_credit ? 'مسموح' : 'غير مسموح' }}</strong></div>
                <div><span>الحد الائتماني</span><strong>{{ $customer->credit_limit !== null ? '₪'.number_format((float)$customer->credit_limit,2) : 'بدون حد' }}</strong></div>
                <div><span>المتاح من الحد</span><strong>{{ $customer->availableCredit() !== null ? '₪'.number_format((float)$customer->availableCredit(),2) : '—' }}</strong></div>
                <div><span>دورة الفوترة</span><strong>{{ $customer->billingCycleLabel() }}</strong></div>
                <div><span>مهلة السداد</span><strong>{{ $customer->payment_terms_days }} يوم</strong></div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:1.25rem">
    <div class="card-header"><span class="card-title">آخر الفواتير</span><a href="{{ route('customers.statement', ['customer'=>$customer,'location_id'=>$statementLocationId]) }}" class="btn btn-ghost btn-sm">عرض الكشف كاملًا</a></div>
    <div class="table-wrap no-margin">
        <table class="data-table">
            <thead><tr><th>الفاتورة</th><th>الفرع</th><th>التاريخ</th><th>الاستحقاق</th><th>الإجمالي</th><th>المتبقي</th><th></th></tr></thead>
            <tbody>
            @forelse($customer->invoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->location?->name ?? '—' }}</td>
                    <td>{{ $invoice->issued_at?->format('Y-m-d') }}</td>
                    <td>{{ $invoice->due_at?->format('Y-m-d') ?? '—' }}</td>
                    <td>₪{{ number_format((float)$invoice->total_amount,2) }}</td>
                    <td><strong>₪{{ number_format((float)$invoice->remaining_amount,2) }}</strong></td>
                    <td><a href="{{ route('invoices.show',$invoice) }}" class="btn btn-ghost btn-sm">عرض</a></td>
                </tr>
            @empty<tr><td colspan="7"><div class="empty-state-sm">لا توجد فواتير.</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="customer-layout" style="margin-top:1.25rem">
    <div class="card">
        <div class="card-header"><span class="card-title">آخر الطلبات</span></div>
        <div class="card-body compact-list">
            @forelse($customer->orders as $order)
                <a href="{{ route('orders.show',$order) }}"><span>{{ $order->order_number }}</span><strong>₪{{ number_format((float)$order->total_amount,2) }}</strong></a>
            @empty<div class="empty-state-sm">لا توجد طلبات.</div>@endforelse
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">آخر دفعات الحساب</span></div>
        <div class="card-body compact-list">
            @forelse($customer->customerPayments as $payment)
                <div><span>{{ $payment->paid_at?->format('Y-m-d H:i') }} · {{ $payment->paymentMethod?->name_ar ?: $payment->paymentMethod?->name }}</span><strong>₪{{ number_format((float)$payment->amount,2) }}</strong></div>
            @empty<div class="empty-state-sm">لا توجد دفعات على الحساب.</div>@endforelse
        </div>
    </div>
</div>

@if($customer->specialCakeOrders->isNotEmpty())
<div class="card" style="margin-top:1.25rem">
    <div class="card-header"><span class="card-title">طلبات الكيك الخاصة</span></div>
    <div class="table-wrap no-margin">
        <table class="data-table">
            <thead><tr><th>رقم الطلب</th><th>فرع المنشأ</th><th>تاريخ التسليم</th><th>القيمة</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            @foreach($customer->specialCakeOrders as $cakeOrder)
                <tr>
                    <td>{{ $cakeOrder->order_number }}</td>
                    <td>{{ $cakeOrder->originBranch?->name ?? '—' }}</td>
                    <td>{{ $cakeOrder->required_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>₪{{ number_format((float)($cakeOrder->net_price ?? $cakeOrder->total_price),2) }}</td>
                    <td>@statusArabic($cakeOrder->status)</td>
                    <td><a href="{{ route('cake-orders.show',$cakeOrder) }}" class="btn btn-ghost btn-sm">عرض</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<style>
.customer-subtitle{color:var(--text-muted);font-size:.8rem;margin-top:.2rem}.customer-location-filter{max-width:360px}.customer-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.8rem;margin-bottom:1.25rem}.stat-card{padding:1rem;background:var(--surface);border:1px solid var(--border);border-radius:12px;display:flex;flex-direction:column;gap:.35rem}.stat-card span{color:var(--text-muted);font-size:.72rem}.stat-card strong{font-size:1.05rem}.danger{color:var(--error,#c0392b)}.success{color:#16845b}.customer-layout{display:grid;grid-template-columns:2fr 1fr;gap:1.25rem}.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.detail-grid.one-col{grid-template-columns:1fr}.detail-grid>div{display:flex;flex-direction:column;gap:.2rem}.detail-grid span{color:var(--text-muted);font-size:.73rem}.detail-grid .wide{grid-column:1/-1}.no-margin{margin:0}.compact-list{display:grid;gap:.1rem}.compact-list>a,.compact-list>div{display:flex;justify-content:space-between;gap:1rem;padding:.7rem 0;border-bottom:1px solid var(--border);color:var(--text);text-decoration:none}.compact-list>a:last-child,.compact-list>div:last-child{border-bottom:0}
@media(max-width:1200px){.customer-stats{grid-template-columns:repeat(3,1fr)}}@media(max-width:800px){.customer-layout{grid-template-columns:1fr}.customer-stats{grid-template-columns:repeat(2,1fr)}.detail-grid{grid-template-columns:1fr}.detail-grid .wide{grid-column:auto}}@media(max-width:480px){.customer-stats{grid-template-columns:1fr}}
</style>
@endsection
