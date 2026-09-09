@extends('layouts.app')
@section('title', 'العملاء')
@section('content')
<div class="page-actions">
    <div>
        <div class="page-actions-title">العملاء</div>
        <div style="color:var(--text-muted);font-size:.82rem;margin-top:.2rem">عملاء الفروع والحسابات المؤسسية متعددة الفروع</div>
    </div>
    @can('customers.create')
        <div class="action-btns"><a href="{{ route('customers.create') }}" class="btn btn-gold">+ إضافة عميل</a></div>
    @endcan
</div>

<div class="filter-row">
    <form method="GET" class="filter-grid customer-filter-grid">
        <div class="filter-group">
            <label class="filter-label">بحث</label>
            <input type="text" name="search" class="form-input" placeholder="الاسم، الهاتف، المسؤول أو الرقم التعريفي" value="{{ request('search') }}">
        </div>

        @if($isAdmin)
            <div class="filter-group">
                <label class="filter-label">الفرع</label>
                <select name="location_id" class="form-select">
                    <option value="">كل الفروع</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">نوع العميل</label>
                <select name="customer_type" class="form-select">
                    <option value="">الكل</option>
                    <option value="individual" @selected(request('customer_type') === 'individual')>فرد</option>
                    <option value="institution" @selected(request('customer_type') === 'institution')>مؤسسة</option>
                    <option value="company" @selected(request('customer_type') === 'company')>شركة</option>
                    <option value="government" @selected(request('customer_type') === 'government')>جهة / قطاع</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">النطاق</label>
                <select name="scope" class="form-select">
                    <option value="">الكل</option>
                    <option value="branch" @selected(request('scope') === 'branch')>فرع واحد</option>
                    <option value="selected" @selected(request('scope') === 'selected')>فروع محددة</option>
                    <option value="global" @selected(request('scope') === 'global')>جميع الفروع</option>
                </select>
            </div>
        @endif

        <div class="filter-group filter-actions">
            <button class="btn btn-outline btn-sm" type="submit">تطبيق</button>
            @if(request()->hasAny(['search','location_id','customer_type','scope']))
                <a href="{{ route('customers.index') }}" class="btn btn-ghost btn-sm">مسح</a>
            @endif
        </div>
    </form>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>العميل</th>
                <th>النوع</th>
                <th>النطاق</th>
                <th>الهاتف</th>
                <th>الطلبات</th>
                <th>المشتريات</th>
                <th>المستحق</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
        @forelse($customers as $customer)
            <tr>
                <td>
                    <strong>{{ $customer->name }}</strong>
                    <div class="muted-line">{{ $customer->location?->name ?? '—' }}</div>
                </td>
                <td><span class="account-badge">{{ $customer->typeLabel() }}</span></td>
                <td>
                    <span class="account-badge {{ $customer->isCentral() ? 'central' : '' }}">{{ $customer->scopeLabel() }}</span>
                    @if($customer->scope === 'selected' && $customer->locations->isNotEmpty())
                        <div class="muted-line">{{ $customer->locations->pluck('name')->join('، ') }}</div>
                    @endif
                </td>
                <td>{{ $customer->phone }}</td>
                <td>{{ number_format((int) ($customer->orders_count ?? 0)) }}</td>
                <td>₪{{ number_format((float) ($customer->total_spent ?? 0), 2) }}</td>
                <td>
                    @php($outstanding = (float) ($customer->outstanding_balance ?? 0))
                    <strong style="color:{{ $outstanding > 0 ? 'var(--error,#c0392b)' : 'var(--text)' }}">₪{{ number_format($outstanding, 2) }}</strong>
                </td>
                <td>
                    <div class="actions">
                        <a href="{{ route('customers.show', $customer) }}" class="btn btn-ghost btn-sm">عرض</a>
                        <a href="{{ route('customers.statement', $customer) }}" class="btn btn-outline btn-sm">كشف حساب</a>
                        @if(auth()->user()->can('customers.update') && (! $customer->isCentral() || auth()->user()->isAdmin() || auth()->user()->can('customers.view_all') || auth()->user()->can('financial.global.view')))
                            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline btn-sm">تعديل</a>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state-sm">لا يوجد عملاء مطابقون.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{ $customers->withQueryString()->links() }}

<style>
.customer-filter-grid{grid-template-columns:2fr repeat(3,minmax(150px,1fr)) auto}.filter-actions{display:flex;align-items:flex-end;gap:.45rem}.muted-line{margin-top:.18rem;color:var(--text-muted);font-size:.72rem;max-width:260px}.account-badge{display:inline-flex;padding:.22rem .5rem;border:1px solid var(--border);border-radius:999px;font-size:.72rem;font-weight:700}.account-badge.central{color:var(--gold);border-color:rgba(212,175,55,.4);background:rgba(212,175,55,.08)}
@media(max-width:1000px){.customer-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.customer-filter-grid{grid-template-columns:1fr}}
</style>
@endsection
