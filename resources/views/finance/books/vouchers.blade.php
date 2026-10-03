@extends('layouts.app')
@section('title', 'سندات القبض والصرف')
@section('page-title', 'سندات القبض والصرف')
@section('content')
<div class="page-header"><div><h1 class="page-heading">سندات القبض والصرف</h1><p class="page-subheading">المسودة لا تغير الحسابات أو الصندوق. الاعتماد يرحّل قيدًا مدينًا ودائنًا متساويين.</p></div>
    @can('accounting.vouchers.create')<a href="{{ route('accounting.books.vouchers.create', ['location_id' => $locationId]) }}" class="btn btn-primary">سند جديد</a>@endcan</div>
<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">التصفية</span></div><div class="card-body">
    <form method="GET" action="{{ route('accounting.books.vouchers') }}" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:end">
        @if($locations->count() > 1)<label>الفرع<select name="location_id" class="form-input"><option value="">جميع الفروع</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}</option>@endforeach</select></label>@endif
        <label>النوع<select name="type" class="form-input"><option value="">الكل</option><option value="receipt" @selected(request('type') === 'receipt')>قبض</option><option value="payment" @selected(request('type') === 'payment')>صرف</option></select></label>
        <label>الحالة<select name="status" class="form-input"><option value="">الكل</option><option value="draft" @selected(request('status') === 'draft')>مسودة</option><option value="posted" @selected(request('status') === 'posted')>مرحّل</option><option value="reversed" @selected(request('status') === 'reversed')>معكوس</option><option value="cancelled" @selected(request('status') === 'cancelled')>ملغى</option></select></label>
        <button class="btn btn-outline">عرض</button>
    </form>
</div></div>
<div class="card"><div class="card-header"><span class="card-title">السندات</span></div><div class="card-body"><div class="table-wrap">
    <table class="data-table"><thead><tr><th>السند</th><th>النوع</th><th>الفرع</th><th>التاريخ</th><th>الطرف</th><th>المبلغ</th><th>المرجع</th><th>الحالة</th><th></th></tr></thead><tbody>
        @forelse($vouchers as $voucher)<tr>
            <td>{{ $voucher->number }}</td><td>{{ $voucher->type === 'receipt' ? 'قبض' : 'صرف' }}</td><td>{{ $voucher->location?->name }}</td><td>{{ $voucher->voucher_date?->format('Y-m-d') }}</td><td>{{ $voucher->party_name }}</td>
            <td>{{ number_format((float) $voucher->amount, 2) }} {{ $voucher->currency?->code }}</td><td>{{ $voucher->external_reference }}</td>
            <td>{{ ['draft' => 'مسودة', 'posted' => 'مرحّل', 'reversed' => 'معكوس', 'cancelled' => 'ملغى'][$voucher->status] ?? $voucher->status }}</td>
            <td><a href="{{ route('accounting.books.vouchers.show', $voucher) }}" class="btn btn-outline btn-sm">تفاصيل</a></td>
        </tr>@empty<tr><td colspan="9" style="text-align:center">لا توجد سندات.</td></tr>@endforelse
    </tbody></table>
</div><div style="margin-top:1rem">{{ $vouchers->links() }}</div></div></div>
@endsection
