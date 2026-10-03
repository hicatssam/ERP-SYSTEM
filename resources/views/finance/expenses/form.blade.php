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
        @include('finance.expenses.fields')
    </div></div>
    <div class="form-actions" style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:1rem"><a class="btn btn-ghost" href="{{ route('costing.expenses.index') }}">إلغاء</a><button class="btn btn-gold">حفظ المسودة</button></div>
</form>
@endsection
