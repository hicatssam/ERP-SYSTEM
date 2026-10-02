@extends('layouts.app')
@section('title', 'حساب مشتريات الموظف')
@section('page-title', 'حساب مشتريات الموظف')

@section('content')
<div class="page-header">
    <div><h1 class="page-heading">{{ $purchase->number }}</h1>
        <p class="page-subheading">{{ $purchase->employee?->full_name }} · {{ $purchase->employee?->employee_number }} · {{ $purchase->location?->name }} · {{ $purchase->purchased_at?->format('Y-m-d H:i') }}</p>
    </div>
    <a href="{{ route('accounting.employee-purchases.index') }}" class="btn btn-outline">العودة للحسابات</a>
</div>

<div class="stats-grid" style="margin-bottom:1rem">
    <div class="stat-card stat-blue"><div class="stat-info"><div class="stat-label">إجمالي المنتجات</div><div class="stat-value">{{ number_format((float) $purchase->total_amount, 2) }} {{ $purchase->currency?->code }}</div></div></div>
    <div class="stat-card stat-green"><div class="stat-info"><div class="stat-label">المسدّد المعتمد</div><div class="stat-value">{{ number_format((float) $purchase->paid_amount, 2) }}</div></div></div>
    <div class="stat-card stat-orange"><div class="stat-info"><div class="stat-label">المتبقي</div><div class="stat-value">{{ number_format((float) $purchase->outstanding_amount, 2) }}</div></div></div>
</div>
<p class="page-subheading" style="margin-bottom:1rem">{{ $purchase->payment_plan === 'installments' ? 'أقساط شهرية' : 'على الحساب' }} · {{ $purchase->status === 'settled' ? 'مسدّد بالكامل' : 'حساب مفتوح' }}. هذه الذمة منفصلة عن سلفة الموظف وراتبه، ولا تُخصم منه تلقائيًا.</p>

<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">المنتجات المصروفة</span></div><div class="card-body">
    <div class="table-wrap"><table class="data-table"><thead><tr><th>المنتج</th><th>الكمية</th><th>السعر وقت الصرف</th><th>الإجمالي</th></tr></thead><tbody>
        @foreach($purchase->items as $item)<tr><td>{{ $item->product_name }}</td><td>{{ number_format((float) $item->quantity, 3) }}</td><td>{{ number_format((float) $item->unit_price, 2) }}</td><td>{{ number_format((float) $item->line_total, 2) }}</td></tr>@endforeach
    </tbody></table></div>
    @if($purchase->notes)<p style="margin-top:1rem">ملاحظات: {{ $purchase->notes }}</p>@endif
</div></div>

<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">جدول الاستحقاقات</span></div><div class="card-body">
    <div class="table-wrap"><table class="data-table"><thead><tr><th>القسط</th><th>موعد الاستحقاق</th><th>القيمة</th><th>المدفوع</th><th>المتبقي</th><th>الحالة</th></tr></thead><tbody>
        @foreach($purchase->installments as $installment)
            @php($left = (float) $installment->amount - (float) $installment->paid_amount)
            <tr><td>{{ $installment->sequence }}</td><td>{{ $installment->due_date?->format('Y-m-d') }}</td><td>{{ number_format((float) $installment->amount, 2) }}</td><td>{{ number_format((float) $installment->paid_amount, 2) }}</td><td>{{ number_format($left, 2) }}</td>
                <td>{{ $installment->status === 'paid' ? 'مسدد' : ($left > 0 && $installment->due_date?->isBefore(today()) ? 'متأخر' : ($installment->status === 'partial' ? 'جزئي' : 'بانتظار السداد')) }}</td></tr>
        @endforeach
    </tbody></table></div>
</div></div>

@can('accounting.employee_accounts.receive')
    @if($purchase->status === 'open')
    @php($pending = $purchase->receipts->where('status', 'pending_verification')->sum('amount'))
    <div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">تسجيل دفعة</span></div><div class="card-body">
        <p class="page-subheading">المتاح للسداد الآن {{ number_format(max(0, (float) $purchase->outstanding_amount - (float) $pending), 2) }} {{ $purchase->currency?->code }} بعد حجز الدفعات المعلقة. التحويلات التي تحتاج تحقق لا تنقص الرصيد قبل اعتمادها.</p>
        <form method="POST" action="{{ route('accounting.employee-purchases.receipts.store', $purchase) }}" enctype="multipart/form-data">@csrf
            <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="form-grid">
                <div class="form-group"><label class="form-label">المبلغ *</label><input class="form-input" type="number" name="amount" step="0.01" min="0.01" max="{{ number_format(max(0, (float) $purchase->outstanding_amount - (float) $pending), 2, '.', '') }}" value="{{ old('amount') }}" required></div>
                <div class="form-group"><label class="form-label">طريقة الدفع *</label><select class="form-input" name="payment_method_id" required><option value="">اختر طريقة الدفع</option>@foreach($paymentMethods as $method)<option value="{{ $method->id }}" @selected((int) old('payment_method_id') === $method->id)>{{ $method->name_ar ?: $method->name }}{{ $method->requires_verification ? ' · يحتاج اعتماد' : '' }}</option>@endforeach</select></div>
                <div class="form-group"><label class="form-label">المرجع (إن لزم)</label><input class="form-input" name="reference" value="{{ old('reference') }}" maxlength="120"></div>
                <div class="form-group"><label class="form-label">إثبات الدفع (إن لزم)</label><input class="form-input" type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
            </div>
            <div class="form-group"><label class="form-label">ملاحظات</label><textarea class="form-input" name="notes" maxlength="1000">{{ old('notes') }}</textarea></div>
            <button class="btn btn-primary" @disabled($pending >= (float) $purchase->outstanding_amount)>تسجيل الدفعة</button>
        </form>
    </div></div>
    @endif
@endcan

<div class="card"><div class="card-header"><span class="card-title">سجل التحصيلات</span></div><div class="card-body">
    <div class="table-wrap"><table class="data-table"><thead><tr><th>التاريخ</th><th>المبلغ</th><th>طريقة الدفع</th><th>المرجع</th><th>الحالة</th><th>الإثبات</th><th>الإجراء</th></tr></thead><tbody>
        @forelse($purchase->receipts as $receipt)
            <tr><td>{{ $receipt->received_at?->format('Y-m-d H:i') }}</td><td>{{ number_format((float) $receipt->amount, 2) }}</td><td>{{ $receipt->paymentMethod?->name_ar ?: $receipt->paymentMethod?->name }}</td><td>{{ $receipt->reference ?? '—' }}</td>
                <td>{{ match ($receipt->status) { 'posted' => 'معتمدة', 'rejected' => 'مرفوضة', default => 'بانتظار التحقق' } }} @if($receipt->rejection_reason)<small>{{ $receipt->rejection_reason }}</small>@endif</td>
                <td>@if($receipt->payment_proof)<a href="{{ route('accounting.employee-purchases.receipts.proof', $receipt) }}">تحميل</a>@else — @endif</td>
                <td>@can('accounting.employee_accounts.verify')
                    @if($receipt->status === 'pending_verification')
                        <form method="POST" action="{{ route('accounting.employee-purchases.receipts.verify', $receipt) }}" style="display:inline">@csrf<button class="btn btn-primary btn-sm">اعتماد</button></form>
                        <form method="POST" action="{{ route('accounting.employee-purchases.receipts.reject', $receipt) }}" style="display:inline">@csrf<input class="form-input" name="reason" required minlength="5" maxlength="1000" placeholder="سبب الرفض" style="max-width:160px;display:inline-block"><button class="btn btn-outline btn-sm">رفض</button></form>
                    @endif
                @endcan</td>
            </tr>
        @empty<tr><td colspan="7" style="text-align:center">لم تُسجّل دفعات بعد.</td></tr>@endforelse
    </tbody></table></div>
</div></div>
@endsection
