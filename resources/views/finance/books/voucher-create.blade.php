@extends('layouts.app')
@section('title', 'سند قبض أو صرف جديد')
@section('page-title', 'سند قبض أو صرف جديد')
@section('content')
<div class="page-header"><div><h1 class="page-heading">سند قبض أو صرف جديد</h1><p class="page-subheading">للعمليات التي لم تسجل بالفعل في المبيعات أو العملاء أو الموظفين أو الموردين أو المصروفات؛ تكرار العملية يضاعف المحاسبة والصندوق.</p></div><a href="{{ route('accounting.books.vouchers') }}" class="btn btn-outline">العودة</a></div>
<form method="POST" action="{{ route('accounting.books.vouchers.store') }}" enctype="multipart/form-data" class="card">@csrf
    <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="card-header"><span class="card-title">بيانات السند</span></div><div class="card-body">
        <div class="form-grid">
            <div class="form-group"><label class="form-label">النوع *</label><select name="type" class="form-input" required><option value="receipt" @selected(old('type') === 'receipt')>سند قبض</option><option value="payment" @selected(old('type') === 'payment')>سند صرف</option></select></div>
            <div class="form-group"><label class="form-label">الفرع *</label><select name="location_id" class="form-input" required><option value="">اختر الفرع</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((int) old('location_id', $locationId) === $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="form-group"><label class="form-label">تاريخ السند *</label><input type="date" name="voucher_date" class="form-input" value="{{ old('voucher_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
            <div class="form-group"><label class="form-label">العملة الأساسية</label><div class="form-input">{{ $currency?->displayName() ?? 'اضبط العملة الأساسية أولًا' }}</div></div>
            <div class="form-group"><label class="form-label">المبلغ *</label><input type="number" name="amount" class="form-input" value="{{ old('amount') }}" min="0.01" step="0.01" required></div>
            <div class="form-group"><label class="form-label">طريقة الدفع *</label><select name="payment_method_id" id="voucherMethod" class="form-input" required><option value="">اختر الطريقة</option>@foreach($methods as $method)<option value="{{ $method->id }}" data-kind="{{ $method->type === 'cash' ? 'cash' : 'bank' }}" @selected((int) old('payment_method_id') === $method->id)>{{ $method->name_ar ?: $method->name }}{{ $method->requires_verification ? ' · يحتاج إثباتًا' : '' }}</option>@endforeach</select></div>
            <div class="form-group"><label class="form-label">حساب الصندوق أو البنك *</label><select name="treasury_account_id" id="voucherTreasury" class="form-input" required><option value="">اختر الحساب</option>@foreach($accounts->whereIn('subtype', ['cash', 'bank']) as $account)<option value="{{ $account->id }}" data-kind="{{ $account->subtype }}" @selected((int) old('treasury_account_id') === $account->id)>{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></div>
            <div class="form-group"><label class="form-label">الحساب المقابل *</label><select name="counter_account_id" class="form-input" required><option value="">اختر الحساب</option>@foreach($accounts->whereNull('subtype') as $account)<option value="{{ $account->id }}" @selected((int) old('counter_account_id') === $account->id)>{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></div>
            <div class="form-group"><label class="form-label">اسم الطرف *</label><input name="party_name" class="form-input" value="{{ old('party_name') }}" maxlength="160" required></div>
            <div class="form-group"><label class="form-label">مرجع خارجي فريد للفرع *</label><input name="external_reference" class="form-input" value="{{ old('external_reference') }}" maxlength="120" required placeholder="رقم تحويل أو إيصال أو مرجع إداري"></div>
            <div class="form-group"><label class="form-label">إثبات التحويل</label><input type="file" name="payment_proof" class="form-input" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
        </div>
        <div class="form-group"><label class="form-label">البيان والسبب *</label><textarea name="description" class="form-input" maxlength="500" minlength="5" required>{{ old('description') }}</textarea></div>
        <p class="page-subheading">السند النقدي يُرحَّل بتاريخ اليوم فقط وقبل إقفال صندوق الفرع. يُحفظ هذا النموذج مسودة، ويصبح نافذًا بعد اعتماده من المخوّل.</p>
        <button class="btn btn-primary" @disabled(! $currency)>حفظ المسودة</button>
    </div>
</form>
@endsection
@push('scripts')<script>
document.addEventListener('DOMContentLoaded', () => {
    const method = document.getElementById('voucherMethod');
    const treasury = document.getElementById('voucherTreasury');
    function refresh() {
        const kind = method.selectedOptions[0]?.dataset.kind;
        for (const option of treasury.options) {
            if (!option.value) continue;
            option.disabled = !!kind && option.dataset.kind !== kind;
        }
        if (treasury.selectedOptions[0]?.disabled) treasury.value = '';
    }
    method.addEventListener('change', refresh); refresh();
});
</script>@endpush
