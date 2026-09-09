@extends('layouts.app')
@section('title', 'تعديل مورد')
@section('content')
    <div class="page-header">
        <h1 class="page-heading">تعديل المورد: {{ $supplier->name }}</h1>
        <p class="page-subheading"><a href="{{ route('suppliers.show', $supplier) }}">الرجوع إلى المورد</a></p>
    </div>
    @include('procurement.partials.flash')
    <form action="{{ route('suppliers.update', $supplier) }}" method="POST">@csrf @method('PUT')
        <div class="card">
            <div class="card-body">@include('procurement.suppliers.form')</div>
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1rem"><button class="btn btn-gold" type="submit">حفظ
                التعديلات</button><a class="btn btn-ghost" href="{{ route('suppliers.show', $supplier) }}">إلغاء</a></div>
    </form>
@endsection
