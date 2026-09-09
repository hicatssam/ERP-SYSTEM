@extends('layouts.app')
@section('title', 'تفاصيل المورد')
@section('content')
    @include('procurement.partials.flash')
    <div class="page-actions">
        <div class="page-actions-title">{{ $supplier->name }} <small
                style="font-weight:400">({{ $supplier->supplier_code }})</small></div>
        <div class="action-btns"><a class="btn btn-ghost" href="{{ route('suppliers.statement', $supplier) }}">كشف الحساب</a>
            @can('suppliers.update')
                <a class="btn btn-outline" href="{{ route('suppliers.edit', $supplier) }}">تعديل</a>
            @endcan
        </div>
    </div>
    <div class="dashboard-row">
        <div class="card">
            <div class="card-header"><span class="card-title">بيانات المورد</span></div>
            <div class="card-body">
                <div class="detail-list">
                    <div class="detail-row"><span class="detail-label">العملة</span><span
                            class="detail-value">{{ $supplier->currency?->displayName() }}
                            ({{ $supplier->currency?->displayName() }})</span></div>
                    <div class="detail-row"><span class="detail-label">الحالة</span><span
                            class="detail-value">{{ $supplier->statusLabel() }}</span></div>
                    <div class="detail-row"><span class="detail-label">الهاتف</span><span
                            class="detail-value">{{ $supplier->phone ?: '—' }}</span></div>
                    <div class="detail-row"><span class="detail-label">شروط الدفع</span><span
                            class="detail-value">{{ $supplier->payment_terms ?: '—' }}</span></div>
                    <div class="detail-row"><span class="detail-label">حد الائتمان</span><span
                            class="detail-value">{{ number_format($supplier->credit_limit, 2) }}</span></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">ملخص الرصيد</span></div>
            <div class="card-body">
                <div class="detail-list">
                    <div class="detail-row"><span class="detail-label">الرصيد الافتتاحي</span><span
                            class="detail-value">{{ number_format($summary['opening_balance'], 2) }}</span></div>
                    <div class="detail-row"><span class="detail-label">المشتريات</span><span
                            class="detail-value">{{ number_format($summary['purchases'], 2) }}</span></div>
                    <div class="detail-row"><span class="detail-label">المرتجعات</span><span
                            class="detail-value">{{ number_format($summary['returns'], 2) }}</span></div>
                    <div class="detail-row"><span class="detail-label">الدفعات</span><span
                            class="detail-value">{{ number_format($summary['payments'], 2) }}</span></div>
                    <div class="detail-row"><span class="detail-label"><strong>الرصيد المستحق</strong></span><span
                            class="detail-value"><strong>{{ number_format($summary['outstanding_balance'], 2) }}</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="dashboard-row" style="margin-top:1rem">
        <div class="card">
            <div class="card-header"><span class="card-title">جهات الاتصال</span></div>
            <div class="card-body">
                @forelse($supplier->contacts as $contact)
                    <div style="padding:.45rem 0;border-bottom:1px solid var(--border-light)">
                        <strong>{{ $contact->name }}</strong> — {{ $contact->position }}<br><small>{{ $contact->phone }}
                            {{ $contact->email }}</small>
                </div>@empty<div class="empty-state-sm">لا توجد جهات اتصال إضافية.
                    </div>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">أصناف المورد</span></div>
            <div class="card-body">
                @forelse($supplier->supplierProducts as $row)
                    <div style="padding:.45rem 0;border-bottom:1px solid var(--border-light)">
                        <strong>{{ $row->product?->name_ar ?: $row->product?->name }}</strong><br><small>{{ number_format($row->purchase_price, 4) }}
                            {{ $row->currency?->displayName() }}
                            / {{ $row->purchaseUnit?->displayName() ?? $row->product?->unitDefinition?->displayName() ?? $row->product?->unit }}
                            @if((float) $row->conversion_factor !== 1.0)
                                — يعادل {{ number_format((float) $row->conversion_factor, 6) }} من وحدة المخزون
                            @endif
                            @if($row->package_description)
                                — {{ $row->package_description }}
                            @endif
                            @if ($row->is_preferred)
                                — مورد مفضّل
                            @endif
                        </small>
                </div>@empty<div class="empty-state-sm">لا توجد أصناف مرتبطة بالمورد.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
