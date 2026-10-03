@extends('layouts.app')
@section('title', 'القيد ' . $journal->number)
@section('page-title', 'القيد ' . $journal->number)
@section('content')
<div class="page-header"><div><h1 class="page-heading">القيد {{ $journal->number }}</h1><p class="page-subheading">{{ $journal->location?->name }} · {{ $journal->entry_date?->format('Y-m-d') }} · {{ $journal->period?->name }} · {{ $journal->currency_code }}</p></div>
    <div><a href="{{ route('accounting.books.journals') }}" class="btn btn-outline">القيود</a> <button type="button" class="btn btn-outline" onclick="window.print()">طباعة</button></div></div>
<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">{{ $journal->description }}</span></div><div class="card-body">
    <p>النوع: {{ ['manual' => 'تسوية يدوية', 'receipt' => 'سند قبض', 'payment' => 'سند صرف', 'reversal' => 'قيد عكسي'][$journal->kind] ?? $journal->kind }} · سجله {{ $journal->creator?->display_name ?? '—' }} في {{ $journal->posted_at?->format('Y-m-d H:i') }}</p>
    @if($journal->original)<p>عكس القيد <a href="{{ route('accounting.books.journals.show', $journal->original) }}">{{ $journal->original->number }}</a></p>@endif
    @if($journal->reversal)<p>تم عكس هذا القيد عبر <a href="{{ route('accounting.books.journals.show', $journal->reversal) }}">{{ $journal->reversal->number }}</a></p>@endif
    <div class="table-wrap"><table class="data-table"><thead><tr><th>رمز الحساب</th><th>الحساب</th><th>شرح السطر</th><th>مدين</th><th>دائن</th></tr></thead><tbody>
        @foreach($journal->lines as $line)<tr><td>{{ $line->account?->code }}</td><td>{{ $line->account?->name }}</td><td>{{ $line->memo ?? '—' }}</td><td>{{ number_format((float) $line->debit, 2) }}</td><td>{{ number_format((float) $line->credit, 2) }}</td></tr>@endforeach
        <tr><th colspan="3">الإجمالي</th><th>{{ number_format((float) $journal->total_debit, 2) }}</th><th>{{ number_format((float) $journal->total_credit, 2) }}</th></tr>
    </tbody></table></div>
</div></div>
@if($journal->kind === 'manual' && ! $journal->reversal)
    @can('accounting.journals.post')<div class="card"><div class="card-header"><span class="card-title">عكس قيد يدوي</span></div><div class="card-body"><form method="POST" action="{{ route('accounting.books.journals.reverse', $journal) }}" onsubmit="return confirm('إنشاء قيد عكسي اليوم؟')">@csrf
        <div class="form-group"><label class="form-label">سبب العكس *</label><input name="reason" class="form-input" minlength="5" maxlength="500" required></div><button class="btn btn-outline">ترحيل قيد عكسي</button>
    </form></div></div>@endcan
@endif
@endsection
