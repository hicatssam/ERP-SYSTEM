@extends('layouts.app')
@section('title', $mode === 'edit' ? 'تعديل مصروف' : 'مصروف جديد')
@section('page-title', $mode === 'edit' ? 'تعديل مصروف' : 'مصروف جديد')

@section('content')
<div class="page-header">
    <div><h1 class="page-heading">{{ $mode === 'edit' ? 'تعديل مسودة المصروف' : 'إنشاء مسودة مصروف' }}</h1><p class="page-subheading">لن يؤثر المصروف على التقارير المالية قبل الاعتماد والترحيل.</p></div>
    <a class="btn btn-ghost" href="{{ route('costing.expenses.index') }}">رجوع</a>
</div>

<form method="POST" action="{{ $mode === 'edit' ? route('costing.expenses.update', $expense) : route('costing.expenses.store') }}" style="max-width:1000px">
    @csrf
    @if($mode === 'edit') @method('PUT') @endif
    <div class="card"><div class="card-body">
        <div class="form-grid">
            @if($locations->isNotEmpty())
            <div class="form-group"><label class="form-label">الموقع *</label><select class="form-input" name="location_id" required>
                <option value="">اختر الموقع</option>
                @foreach($locations as $location)<option value="{{ $location->id }}" @selected((int)old('location_id',$locationId) === (int)$location->id)>{{ $location->name }}</option>@endforeach
            </select></div>
            @else
                <input type="hidden" name="location_id" value="{{ $locationId }}">
            @endif
            <div class="form-group"><label class="form-label">تصنيف المصروف *</label><select class="form-input" name="expense_category_id" required>
                <option value="">اختر التصنيف</option>
                @foreach($categories as $category)<option value="{{ $category->id }}" @selected((int)old('expense_category_id',$expense->expense_category_id) === (int)$category->id)>{{ $category->name }}</option>@endforeach
            </select></div>
            <div class="form-group"><label class="form-label">القيمة ₪ *</label><input class="form-input" type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount',$expense->amount) }}"></div>
            <div class="form-group"><label class="form-label">تاريخ المصروف *</label><input class="form-input" type="date" name="expense_date" max="{{ now()->toDateString() }}" required value="{{ old('expense_date',$expense->expense_date?->toDateString() ?? now()->toDateString()) }}"></div>
            <div class="form-group"><label class="form-label">الجهة / المستفيد</label><input class="form-input" name="payee" maxlength="180" value="{{ old('payee',$expense->payee) }}"></div>
            <div class="form-group"><label class="form-label">رقم مرجعي</label><input class="form-input" name="reference_number" maxlength="120" value="{{ old('reference_number',$expense->reference_number) }}"></div>
            <div class="form-group" style="grid-column:1/-1"><label class="form-label">الوصف والتبرير *</label><textarea class="form-input" name="description" rows="4" required>{{ old('description',$expense->description) }}</textarea></div>
        </div>
    </div></div>
    <div class="form-actions" style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:1rem"><a class="btn btn-ghost" href="{{ route('costing.expenses.index') }}">إلغاء</a><button class="btn btn-gold">حفظ المسودة</button></div>
</form>
@endsection
