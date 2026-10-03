@extends('layouts.app')
@section('title', 'سند ' . $voucher->number)
@section('page-title', 'سند ' . $voucher->number)
@section('content')
<div class="page-header"><div><h1 class="page-heading">{{ $voucher->type === 'receipt' ? 'سند قبض' : 'سند صرف' }} · {{ $voucher->number }}</h1>
    <p class="page-subheading">{{ $voucher->location?->name }} · {{ $voucher->voucher_date?->format('Y-m-d') }} · {{ ['draft' => 'مسودة', 'posted' => 'مرحّل', 'reversed' => 'معكوس', 'cancelled' => 'ملغى'][$voucher->status] ?? $voucher->status }}</p></div>
    <div><a href="{{ route('accounting.books.vouchers') }}" class="btn btn-outline">السندات</a> <button type="button" class="btn btn-outline" onclick="window.print()">طباعة</button></div>
</div>
<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">تفاصيل السند</span></div><div class="card-body">
    <div class="form-grid">
        <div><strong>الطرف:</strong> {{ $voucher->party_name }}</div><div><strong>المبلغ:</strong> {{ number_format((float) $voucher->amount, 2) }} {{ $voucher->currency?->code }}</div>
        <div><strong>طريقة الدفع:</strong> {{ $voucher->paymentMethod?->name_ar ?: $voucher->paymentMethod?->name }}</div><div><strong>المرجع الخارجي:</strong> {{ $voucher->external_reference }}</div>
        <div><strong>حساب الخزينة:</strong> {{ $voucher->treasuryAccount?->code }} · {{ $voucher->treasuryAccount?->name }}</div><div><strong>الحساب المقابل:</strong> {{ $voucher->counterAccount?->code }} · {{ $voucher->counterAccount?->name }}</div>
        <div><strong>أنشأه:</strong> {{ $voucher->creator?->display_name ?? '—' }}</div><div><strong>رحّله:</strong> {{ $voucher->poster?->display_name ?? '—' }} {{ $voucher->posted_at?->format('Y-m-d H:i') }}</div>
    </div>
    <p style="margin-top:1rem"><strong>البيان:</strong> {{ $voucher->description }}</p>
    @if($voucher->payment_proof)<p><a href="{{ route('accounting.books.vouchers.proof', $voucher) }}">تحميل إثبات الدفع الخاص</a></p>@endif
    @if($voucher->journal)<p><a href="{{ route('accounting.books.journals.show', $voucher->journal) }}">عرض القيد المحاسبي {{ $voucher->journal->number }}</a></p>@endif
    @if($voucher->reversalJournal)<p><a href="{{ route('accounting.books.journals.show', $voucher->reversalJournal) }}">قيد العكس {{ $voucher->reversalJournal->number }}</a> · {{ $voucher->reversal_reason }}</p>@endif
</div></div>
<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">الإجراءات</span></div><div class="card-body" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end">
    @if($voucher->status === 'draft')
        @can('accounting.vouchers.post')<form method="POST" action="{{ route('accounting.books.vouchers.post', $voucher) }}" onsubmit="return confirm('اعتماد السند وترحيله إلى دفتر القيود والصندوق؟')">@csrf<button class="btn btn-primary">اعتماد وترحيل</button></form>@endcan
        @can('accounting.vouchers.create')<form method="POST" action="{{ route('accounting.books.vouchers.cancel', $voucher) }}" onsubmit="return confirm('إلغاء المسودة؟')">@csrf<button class="btn btn-outline">إلغاء المسودة</button></form>@endcan
    @elseif($voucher->status === 'posted')
        @can('accounting.vouchers.reverse')<form method="POST" action="{{ route('accounting.books.vouchers.reverse', $voucher) }}" onsubmit="return confirm('سيُنشأ قيد عكسي مستقل. هل تريد المتابعة؟')">@csrf
            <label class="form-label">سبب العكس *<input name="reason" class="form-input" minlength="5" maxlength="500" required></label><button class="btn btn-outline">عكس السند</button>
        </form>@endcan
    @else<p>لا توجد إجراءات إضافية لهذا السند.</p>@endif
</div></div>
@if($voucher->journal)
<div class="card"><div class="card-header"><span class="card-title">القيد المحاسبي</span></div><div class="card-body"><div class="table-wrap"><table class="data-table"><thead><tr><th>الحساب</th><th>مدين</th><th>دائن</th></tr></thead><tbody>
    @foreach($voucher->journal->lines as $line)<tr><td>{{ $line->account?->code }} · {{ $line->account?->name }}</td><td>{{ number_format((float) $line->debit, 2) }}</td><td>{{ number_format((float) $line->credit, 2) }}</td></tr>@endforeach
    <tr><th>الإجمالي</th><th>{{ number_format((float) $voucher->journal->total_debit, 2) }}</th><th>{{ number_format((float) $voucher->journal->total_credit, 2) }}</th></tr>
</tbody></table></div></div></div>
@endif
@endsection
