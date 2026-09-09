@extends('layouts.app')
@section('title', $expense->expense_number)
@section('page-title', 'تفاصيل المصروف')

@section('content')
<div class="page-header">
    <div><h1 class="page-heading">{{ $expense->expense_number }}</h1><p class="page-subheading">{{ $expense->location?->name }} — {{ $expense->expense_date?->format('d/m/Y') }}</p></div>
    <div class="page-header-actions">
        @if($expense->isEditable()) @can('expenses.update')<a class="btn btn-outline" href="{{ route('costing.expenses.edit',$expense) }}">تعديل</a>@endcan @endif
        <a class="btn btn-ghost" href="{{ route('costing.expenses.index') }}">رجوع</a>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1rem">
    <div class="stat-card"><div class="stat-info"><div class="stat-label">القيمة</div><div class="stat-value">₪{{ number_format((float)$expense->amount,2) }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">الحالة</div><div class="stat-value" style="font-size:1rem">{{ $expense->status?->label() }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">التصنيف</div><div class="stat-value" style="font-size:1rem">{{ $expense->category?->name }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">الفترة المالية</div><div class="stat-value" style="font-size:1rem">{{ $expense->financialPeriod?->name ?? 'غير مرحّل' }}</div></div></div>
</div>

<div class="card"><div class="card-body">
    <div class="details-grid">
        <div><strong>المستفيد:</strong> {{ $expense->payee ?: '—' }}</div>
        <div><strong>المرجع:</strong> {{ $expense->reference_number ?: '—' }}</div>
        <div><strong>أنشأه:</strong> {{ $expense->creator?->display_name ?? 'غير مسجل' }}</div>
        <div><strong>تاريخ الإنشاء:</strong> {{ $expense->created_at?->format('d/m/Y H:i') }}</div>
    </div>
    <hr style="margin:1rem 0;border:0;border-top:1px solid var(--border)">
    <div><strong>الوصف:</strong><p style="margin-top:.5rem;white-space:pre-wrap">{{ $expense->description }}</p></div>

    @if($expense->rejection_reason)<div class="alert alert-danger" style="margin-top:1rem"><strong>سبب الرفض:</strong> {{ $expense->rejection_reason }}</div>@endif
    @if($expense->void_reason)<div class="alert alert-warning" style="margin-top:1rem"><strong>سبب العكس المحاسبي:</strong> {{ $expense->void_reason }}</div>@endif
</div></div>

<div class="card" style="margin-top:1rem"><div class="card-header"><span class="card-title">سير الاعتماد والترحيل</span></div><div class="card-body">
    <div style="display:flex;flex-wrap:wrap;gap:.75rem">
        @if(in_array($expense->statusValue(),['draft','rejected'],true))
            @can('expenses.submit')<form method="POST" action="{{ route('costing.expenses.submit',$expense) }}">@csrf<button class="btn btn-gold">إرسال للاعتماد</button></form>@endcan
        @endif

        @if($expense->statusValue()==='submitted')
            @can('expenses.approve')
            <form method="POST" action="{{ route('costing.expenses.review',$expense) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-gold">اعتماد</button></form>
            <form method="POST" action="{{ route('costing.expenses.review',$expense) }}" style="display:flex;gap:.5rem">@csrf<input type="hidden" name="decision" value="reject"><input class="form-input" name="reason" required minlength="3" placeholder="سبب الرفض"><button class="btn btn-danger">رفض</button></form>
            @endcan
        @endif

        @if($expense->statusValue()==='approved')
            @can('expenses.post')<form method="POST" action="{{ route('costing.expenses.post',$expense) }}">@csrf<button class="btn btn-gold">ترحيل للفترة المالية</button></form>@endcan
        @endif

        @if($expense->statusValue()==='posted')
            @can('expenses.void')<form method="POST" action="{{ route('costing.expenses.void',$expense) }}" style="display:flex;gap:.5rem">@csrf<input class="form-input" name="reason" required minlength="5" placeholder="سبب العكس المحاسبي"><button class="btn btn-danger">عكس المصروف</button></form>@endcan
        @endif
    </div>
    <p style="margin-top:1rem;color:var(--text-muted);font-size:.78rem">العكس لا يحذف المصروف الأصلي؛ ينشئ قيد عكس مستقل في الفترة المالية المفتوحة الحالية.</p>
</div></div>
@endsection
