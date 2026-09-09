@extends('layouts.app')
@section('title', 'الفواتير')

@section('content')
<div class="page-actions">
    <div class="page-actions-title">الفواتير</div>
</div>

<div class="filter-row">
    <form method="GET" class="filter-grid">
        <div class="filter-group">
            <label class="filter-label">بحث برقم الفاتورة</label>
            <input type="text" name="search" class="form-input" value="{{ request('search') }}">
        </div>

        <div class="filter-group">
            <label class="filter-label">حالة الفاتورة</label>
            <select name="status" class="form-select">
                <option value="">الكل</option>
                <option value="active" @selected(request('status') === 'active')>نشطة</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>ملغاة</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">من تاريخ</label>
            <input type="date" name="date_from" class="form-input" value="{{ request('date_from') }}">
        </div>

        <div class="filter-group">
            <label class="filter-label">إلى تاريخ</label>
            <input type="date" name="date_to" class="form-input" value="{{ request('date_to') }}">
        </div>

        <div class="filter-group" style="justify-content:flex-end">
            <button class="btn btn-outline btn-sm" type="submit">تصفية</button>
        </div>
    </form>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>رقم الفاتورة</th>
                <th>الفرع</th>
                <th>العميل</th>
                <th>الإجمالي</th>
                <th>المدفوع</th>
                <th>المتبقي</th>
                <th>حالة السداد</th>
                <th>حالة الفاتورة</th>
                <th>التاريخ</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
        @forelse($invoices as $inv)
            <tr>
                <td><strong>{{ $inv->invoice_number }}</strong></td>
                <td>{{ $inv->location?->name ?? '—' }}</td>
                <td>{{ $inv->customer?->name ?? 'عميل نقدي' }}</td>
                <td>₪{{ number_format((float) $inv->total_amount, 2) }}</td>
                <td>₪{{ number_format((float) $inv->paid_amount, 2) }}</td>
                <td>₪{{ number_format((float) $inv->remaining_amount, 2) }}</td>
                <td>
                    <span class="badge {{ $inv->paymentStatusBadgeClass() }}">
                        {{ $inv->paymentStatusLabel() }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ $inv->statusBadgeClass() }}">
                        {{ $inv->statusLabel() }}
                    </span>
                </td>
                <td>{{ $inv->issued_at?->format('Y-m-d') }}</td>
                <td>
                    <div class="actions">
                        <a href="{{ route('invoices.show', $inv) }}" class="btn btn-ghost btn-sm">عرض</a>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="10">
                    <div class="empty-state-sm">لا توجد فواتير.</div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>


<div>
    {{ $invoices->withQueryString()->links() }}
</div>

@endsection
