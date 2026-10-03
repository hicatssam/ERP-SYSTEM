@extends('layouts.app')
@section('title', 'دفتر القيود المحاسبية')
@section('page-title', 'دفتر القيود المحاسبية')
@section('content')
<div class="page-header"><div><h1 class="page-heading">دفتر القيود المحاسبية</h1><p class="page-subheading">قيد مدين ودائن متساويان لكل سند مرحّل أو تسوية يدوية. لا يُحذف القيد المرحّل؛ التصحيح بقيد عكسي.</p></div>
    @can('accounting.journals.post')<a href="{{ route('accounting.books.journals.create', ['location_id' => $locationId]) }}" class="btn btn-primary">قيد تسوية يدوي</a>@endcan</div>
<div class="card" style="margin-bottom:1rem"><div class="card-body"><form method="GET" action="{{ route('accounting.books.journals') }}" style="display:flex;align-items:end;gap:1rem">
    @if($locations->count() > 1)<label>الفرع<select name="location_id" class="form-input"><option value="">جميع الفروع</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}</option>@endforeach</select></label>@endif
    <button class="btn btn-outline">عرض</button></form></div></div>
<div class="card"><div class="card-header"><span class="card-title">قيود اليومية</span></div><div class="card-body"><div class="table-wrap"><table class="data-table"><thead><tr><th>الرقم</th><th>التاريخ</th><th>الفرع</th><th>النوع</th><th>البيان</th><th>مدين</th><th>دائن</th><th></th></tr></thead><tbody>
    @forelse($journals as $journal)<tr><td>{{ $journal->number }}</td><td>{{ $journal->entry_date?->format('Y-m-d') }}</td><td>{{ $journal->location?->name }}</td><td>{{ ['manual' => 'تسوية', 'receipt' => 'سند قبض', 'payment' => 'سند صرف', 'reversal' => 'عكس'][$journal->kind] ?? $journal->kind }}</td><td>{{ $journal->description }}</td><td>{{ number_format((float) $journal->total_debit, 2) }}</td><td>{{ number_format((float) $journal->total_credit, 2) }}</td><td><a href="{{ route('accounting.books.journals.show', $journal) }}" class="btn btn-outline btn-sm">عرض</a></td></tr>
    @empty<tr><td colspan="8" style="text-align:center">لا توجد قيود مرحّلة.</td></tr>@endforelse
</tbody></table></div><div style="margin-top:1rem">{{ $journals->links() }}</div></div></div>
@endsection
