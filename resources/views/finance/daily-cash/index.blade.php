@extends('layouts.app')
@section('title', 'مطابقة خزينة الفرع')
@section('page-title', 'مطابقة خزينة الفرع')

@section('content')
@php
    $labels = [
        'sales' => ['+', 'مبيعات نقدية', 'دفعات الطلبات المقبوضة نقدًا، وليس قيمة جميع الفواتير'],
        'customer_receipts' => ['+', 'تحصيلات العملاء', 'دفعات العملاء النقدية المنفصلة عن دفعات الطلبات'],
        'other_income' => ['+', 'دخل نقدي آخر', 'حركات مدخلة يدويًا مع سبب ومرجع'],
        'expenses' => ['−', 'مصروفات ورواتب نقدية', 'المصروفات المرحّلة والرواتب المدفوعة نقدًا بالعملة الأساسية'],
        'supplier_payments' => ['−', 'مدفوعات الموردين', 'المدفوعة نقدًا بالعملة الأساسية فقط'],
        'refunds' => ['−', 'مردودات نقدية', 'تُنسب إلى فرع الدفعة الأصلية لعدم وجود فرع مستقل في سجل المرتجع'],
        'transfer_in' => ['+', 'تحويلات نقدية داخلة', 'نقل نقد فعلي إلى صندوق الفرع'],
        'transfer_out' => ['−', 'تحويلات نقدية خارجة', 'نقل نقد فعلي خارج صندوق الفرع'],
    ];
    $components = $closed
        ? collect(array_keys($labels))->mapWithKeys(fn ($key) => [$key => (float)$closed->$key])->all()
        : $live['components'];
    $expected = $closed?->expected_closing ?? ($opening === null ? null : app(\App\Services\Finance\DailyCashReconciliationService::class)->expected($opening, $components));
    $netMovement = app(\App\Services\Finance\DailyCashReconciliationService::class)->expected(0, $components);
    $canEditDay = ! $closed && ! $hasLaterClose;
    $needsMissingDays = $prior && ! $canCarry;
@endphp
<div class="page-header">
    <div><h1 class="page-heading">مطابقة خزينة الفرع</h1><p class="page-subheading">إقفال نقد الفرع اليومي مستقل عن جلسات الكاشير. كل بند يخص حركة نقد فعلية بالعملة الأساسية فقط.</p></div>
    @can('cash_sessions.manage')<a href="{{ route('cash-sessions.index') }}" class="btn btn-ghost">جلسات الكاشير</a>@endcan
</div>

<form method="GET" action="{{ route('daily-cash.index') }}" class="card" style="margin-bottom:1rem">
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:end">
        <div class="form-group"><label class="form-label">الفرع</label><select name="location_id" class="form-input">
            @foreach($locations as $location)<option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}</option>@endforeach
        </select></div>
        <div class="form-group"><label class="form-label">يوم العمل</label><input type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}" class="form-input" required></div>
        <button class="btn btn-outline">عرض المطابقة</button>
    </div>
</form>

@if($closed)
    <div class="alert alert-banner alert-banner-success" style="margin-bottom:1rem">تم إقفال هذا اليوم بواسطة {{ $closed->closedBy?->display_name ?? 'مستخدم' }} في {{ $closed->closed_at?->format('Y-m-d H:i') }}. الأرقام المعروضة أدناه نسخة الإقفال المحفوظة.</div>
    @if($closed->note)<p style="margin-bottom:1rem">ملاحظة الإقفال: {{ $closed->note }}</p>@endif
@elseif($hasLaterClose || $needsMissingDays)
    <div class="alert alert-banner alert-banner-warning" style="margin-bottom:1rem">لا يمكن إقفال هذا اليوم قبل ترتيب تسلسل الأيام. {{ $needsMissingDays ? 'أغلق الأيام التي بين '. $prior->business_date->toDateString() .' وهذا اليوم أولًا.' : 'يوجد يوم لاحق مقفل بالفعل.' }}</div>
@endif
@if($drift)
    <div class="alert alert-banner alert-banner-warning" style="margin-bottom:1rem">تنبيه تدقيق: تغيّرت حركة مصدر بعد إقفال اليوم. بقيت نسخة الإقفال كما هي؛ راجع القيود والعمليات اللاحقة، ولا تعتمد على إعادة الحساب لتغيير الإقفال التاريخي.</div>
@endif
@if($live['unclassified_expenses'] || $live['foreign_cash_supplier_payments'] || $live['unclassified_cash_payroll'] || $live['foreign_cash_payroll'])
    <div class="alert alert-banner alert-banner-warning" style="margin-bottom:1rem">
        بيانات تحتاج مراجعة قبل الإقفال:
        @if($live['unclassified_expenses']) {{ $live['unclassified_expenses'] }} مصروف مرحّل بلا طريقة دفع؛ @endif
        @if($live['foreign_cash_supplier_payments']) {{ $live['foreign_cash_supplier_payments'] }} دفعة مورد نقدية بعملة غير أساسية؛ @endif
        @if($live['unclassified_cash_payroll']) {{ $live['unclassified_cash_payroll'] }} دفعة راتب نقدية بلا عملة محددة؛ @endif
        @if($live['foreign_cash_payroll']) {{ $live['foreign_cash_payroll'] }} دفعة راتب نقدية بعملة غير أساسية؛ @endif
        هذه المبالغ لا تدخل الرصيد المتوقع تلقائيًا.
    </div>
@endif

@if($unclassifiedExpenses->isNotEmpty())
<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">تصنيف مصروفات اليوم غير المحددة</span></div><div class="card-body">
    @foreach($unclassifiedExpenses as $expense)
        <form action="{{ route('daily-cash.expenses.classify', $expense) }}" method="POST" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin:.5rem 0">@csrf
            <span>{{ $expense->expense_number }} — {{ $expense->payee ?: $expense->description }} — {{ $currencySymbol }}{{ number_format((float)$expense->amount,2) }}</span>
            <select name="payment_method_id" class="form-input" required><option value="">اختر طريقة الدفع</option>
                @foreach($cashPaymentMethods as $method)<option value="{{ $method->id }}">{{ $method->name_ar ?: $method->name }}</option>@endforeach
            </select>
            <button class="btn btn-outline btn-sm" @disabled(! $canEditDay)>تصنيف</button>
        </form>
    @endforeach
</div></div>
@endif

<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">حساب الرصيد المتوقع — {{ $currencySymbol }}</span></div>
    <div class="card-body">
        <div class="table-wrap"><table class="data-table"><thead><tr><th>الإشارة</th><th>البند</th><th>المصدر</th><th>القيمة</th></tr></thead><tbody>
            <tr><td></td><td><strong>الرصيد الافتتاحي</strong></td><td>{{ $closed ? 'محفوظ عند الإقفال' : ($canCarry ? 'الرصيد الفعلي لإقفال اليوم السابق' : 'يُدخل عند أول إقفال') }}</td><td>{{ $opening === null ? '—' : $currencySymbol.number_format((float)$opening,2) }}</td></tr>
            @foreach($labels as $key => [$sign, $title, $help])
                <tr><td>{{ $sign }}</td><td>{{ $title }}</td><td>{{ $help }}</td><td>{{ $currencySymbol }}{{ number_format($components[$key], 2) }}</td></tr>
            @endforeach
            <tr><td></td><td><strong>الرصيد المتوقع عند الإقفال</strong></td><td>الافتتاحي + الداخل − الخارج</td><td><strong>{{ $expected === null ? 'يظهر بعد إدخال رصيد الافتتاح' : $currencySymbol.number_format((float)$expected,2) }}</strong></td></tr>
            <tr><td></td><td><strong>الرصيد الفعلي المعدود</strong></td><td>ما تم عده في خزينة الفرع</td><td>{{ $closed ? $currencySymbol.number_format((float)$closed->actual_closing,2) : '—' }}</td></tr>
            <tr><td></td><td><strong>الفارق (الفعلي − المتوقع)</strong></td><td>السالب عجز والموجب زيادة</td><td>{{ $closed ? (($closed->variance > 0 ? '+' : '').$currencySymbol.number_format((float)$closed->variance,2)) : '—' }}</td></tr>
        </tbody></table></div>
    </div>
</div>

@if($canEditDay && $canClose)
<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">إقفال اليوم</span></div><div class="card-body">
    <form id="cashCloseForm" data-net="{{ $netMovement }}" data-opening="{{ $opening ?? '' }}" action="{{ route('daily-cash.close') }}" method="POST" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:end">@csrf
        <input type="hidden" name="location_id" value="{{ $locationId }}"><input type="hidden" name="date" value="{{ $date }}">
        @if($opening === null)
            <div class="form-group"><label class="form-label">الرصيد الافتتاحي لأول يوم *</label><input id="cashOpening" type="number" name="opening_balance" step="0.01" min="0" value="{{ old('opening_balance') }}" class="form-input" required></div>
        @else
            <div class="form-group"><label class="form-label">الرصيد الافتتاحي المرحّل</label><div class="form-input">{{ $currencySymbol }}{{ number_format((float)$opening,2) }}</div></div>
        @endif
        <div class="form-group"><label class="form-label">النقد الفعلي المعدود *</label><input id="cashActual" type="number" name="actual_closing" step="0.01" min="0" value="{{ old('actual_closing') }}" class="form-input" required></div>
        <div class="form-group"><label class="form-label">سبب الفارق (إلزامي إن وجد)</label><input id="cashNote" name="note" class="form-input" maxlength="1000" value="{{ old('note') }}" placeholder="سبب الفارق إن وجد"></div>
        <button class="btn btn-gold" @disabled($needsMissingDays || $live['unclassified_expenses'] || $live['foreign_cash_supplier_payments'] || $live['unclassified_cash_payroll'] || $live['foreign_cash_payroll'])>حفظ وإقفال اليوم</button>
    </form>
    <p id="cashPreview" style="margin-top:.75rem"></p>
    <p style="margin-top:.75rem;color:var(--text-muted)">الإقفال يحفظ نسخة ثابتة من الأرقام، ويرحّل النقد الفعلي لافتتاح اليوم التالي. أي تصحيح بعده يتطلب حركة موثقة في يوم لاحق.</p>
</div></div>
@endif

@if($canEditDay && $canManageMovements)
<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">تسجيل حركة نقدية أخرى</span></div><div class="card-body">
    <form action="{{ route('daily-cash.movements.store') }}" method="POST" class="form-grid">@csrf
        <input type="hidden" name="location_id" value="{{ $locationId }}"><input type="hidden" name="date" value="{{ $date }}">
        <div class="form-group"><label class="form-label">النوع *</label><select name="type" class="form-input" required>
            <option value="other_income">دخل نقدي آخر</option><option value="transfer_in">تحويل نقدي داخل</option><option value="transfer_out">تحويل نقدي خارج</option>
        </select></div>
        <div class="form-group"><label class="form-label">المبلغ *</label><input type="number" name="amount" step="0.01" min="0.01" class="form-input" required></div>
        <div class="form-group"><label class="form-label">مرجع الحركة (مطلوب للتحويل)</label><input name="reference" maxlength="120" class="form-input" placeholder="رقم إيصال أو حوالة"></div>
        <div class="form-group"><label class="form-label">السبب والتفاصيل *</label><input name="description" minlength="5" maxlength="1000" class="form-input" required></div>
        <div class="form-group"><button class="btn btn-outline">إضافة الحركة</button></div>
    </form>
    <p style="margin-top:.75rem;color:var(--text-muted)">لا تسجل دفعة طلب أو عميل أو مورد أو مصروف راتب هنا مرة ثانية. التحويل المقصود نقد مادي، وليس حوالة بنكية بين حسابات. هذا سجل مطابقة للصندوق ولا ينشئ قيد إيراد أو قيد بنك محاسبيًا.</p>
</div></div>
@endif

<div class="card"><div class="card-header"><span class="card-title">الحركات النقدية اليدوية لهذا اليوم</span></div><div class="card-body"><div class="table-wrap"><table class="data-table">
    <thead><tr><th>النوع</th><th>المبلغ</th><th>المرجع / السبب</th><th>المستخدم</th><th>الحالة</th><th></th></tr></thead><tbody>
    @forelse($movements as $movement)
        <tr><td>{{ $labels[$movement->type][1] ?? $movement->type }}</td><td>{{ $currencySymbol }}{{ number_format((float)$movement->amount,2) }}</td>
            <td>{{ $movement->reference ?: '—' }} — {{ $movement->description }}</td><td>{{ $movement->creator?->display_name ?? '—' }}</td>
            <td>{{ $movement->voided_at ? 'ملغاة: '.$movement->void_reason : 'فعّالة' }}</td>
            <td>@if($canEditDay && $canManageMovements && ! $movement->voided_at)<form method="POST" action="{{ route('daily-cash.movements.void', $movement) }}" style="display:flex;gap:.5rem">@csrf<input class="form-input" name="reason" minlength="5" maxlength="1000" required placeholder="سبب الإلغاء"><button class="btn btn-ghost btn-sm">إلغاء</button></form>@endif</td></tr>
    @empty <tr><td colspan="6">لا توجد حركات إضافية.</td></tr> @endforelse
    </tbody></table></div></div></div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('cashCloseForm');
    if (!form) return;
    const opening = document.getElementById('cashOpening');
    const actual = document.getElementById('cashActual');
    const note = document.getElementById('cashNote');
    const preview = document.getElementById('cashPreview');
    const format = value => value.toLocaleString('ar', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const update = () => {
        const start = opening ? opening.value : form.dataset.opening;
        if (start === '' || actual.value === '') {
            preview.textContent = 'أدخل الرصيد الافتتاحي والنقد الفعلي لعرض الفارق قبل الحفظ.';
            note.required = false;
            return;
        }
        const expected = Math.round((Number(start) + Number(form.dataset.net)) * 100) / 100;
        const variance = Math.round((Number(actual.value) - expected) * 100) / 100;
        preview.textContent = `المتوقع: ${format(expected)} — الفعلي: ${format(Number(actual.value))} — الفارق: ${variance > 0 ? '+' : ''}${format(variance)}`;
        note.required = variance !== 0;
    };
    opening?.addEventListener('input', update);
    actual.addEventListener('input', update);
    update();
})();
</script>
@endpush
