@extends('layouts.app')
@section('title', 'سجل الترحيلات المالية')
@section('page-title', 'سجل الترحيلات المالية')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">سجل الترحيلات المالية</h1>
        <p class="page-subheading">حركات البيع والتحصيل والاسترداد والمصروفات المرحّلة، مع مصدر كل حركة وتاريخها.</p>
    </div>
    <div class="page-header-actions">
        @can('financial.dashboard.view')
            <a class="btn btn-outline btn-sm" href="{{ route('financial.dashboard') }}">لوحة المالية</a>
        @endcan
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">تصفية الحركات</span></div>
    <div class="card-body">
        <form action="{{ route('accounting.ledger.index') }}" method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:end">
            <label>من تاريخ <input class="form-input" type="date" name="date_from" value="{{ $from }}"></label>
            <label>إلى تاريخ <input class="form-input" type="date" name="date_to" value="{{ $to }}"></label>
            @if($locations->isNotEmpty())
                <label>الفرع
                    <select class="form-input" name="location_id">
                        <option value="">جميع الفروع</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label>نوع الحركة
                <select class="form-input" name="entry_type">
                    <option value="">جميع الأنواع</option>
                    @foreach($types as $type)
                        <option value="{{ $type->value }}" @selected($entryType === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn btn-primary">عرض</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="card-header"><span class="card-title">ملخص الفترة المحددة</span></div>
    <div class="card-body">
        <div class="kpi-grid">
            @forelse($summary as $item)
                <div class="kpi-item">
                    <div class="kpi-label">{{ $item->entry_type?->label() ?? '—' }} · {{ $item->entries_count }} حركة</div>
                    <div class="kpi-value">{{ number_format((float) $item->amount_total, 2) }} {{ $item->currency_code }}</div>
                </div>
            @empty
                <p class="page-subheading">لا توجد ترحيلات ضمن هذه التصفية.</p>
            @endforelse
        </div>
        <p class="page-subheading" style="margin-top:.75rem">المبالغ مفصولة حسب النوع والعملة؛ هذه ترحيلات تشغيلية وليست ميزان مراجعة أو رصيد صندوق.</p>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="card-header"><span class="card-title">الحركات · {{ $entries->total() }}</span></div>
    <div class="card-body">
        <div style="overflow-x:auto">
            <table class="table" style="width:100%">
                <thead><tr>
                    <th>التاريخ</th><th>الفرع</th><th>النوع</th><th>البيان</th><th>المصدر</th><th>الفترة</th><th>المبلغ</th><th>سجّلها</th>
                </tr></thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td>{{ $entry->entry_date?->format('Y-m-d') }}</td>
                            <td>{{ $entry->location?->name ?? '—' }}</td>
                            <td>{{ $entry->entry_type?->label() ?? '—' }}</td>
                            <td>{{ $entry->description ?: '—' }}</td>
                            <td>
                                @if($entry->reference_type === 'invoices' && $entry->reference_id && auth()->user()->can('invoices.view'))
                                    <a href="{{ route('invoices.show', $entry->reference_id) }}">فاتورة #{{ $entry->reference_id }}</a>
                                @elseif($entry->reference_type === 'expenses' && $entry->reference_id && auth()->user()->can('expenses.view'))
                                    <a href="{{ route('costing.expenses.show', $entry->reference_id) }}">مصروف #{{ $entry->reference_id }}</a>
                                @else
                                    {{ $entry->reference_type ? $entry->reference_type . ' #' . $entry->reference_id : '—' }}
                                @endif
                            </td>
                            <td>{{ $entry->period ? $entry->period->year . '/' . str_pad($entry->period->month, 2, '0', STR_PAD_LEFT) : '—' }}</td>
                            <td dir="ltr">{{ number_format((float) $entry->amount, 2) }} {{ $entry->currency_code }}</td>
                            <td>{{ $entry->createdBy?->display_name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="text-align:center">لا توجد حركات في هذه الفترة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem">{{ $entries->links() }}</div>
    </div>
</div>
@endsection
