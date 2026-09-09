@extends('layouts.app')
@section('title', 'إضافة مورد')
@section('content')
    <div class="page-header">
        <h1 class="page-heading">إضافة مورد</h1>
        <p class="page-subheading"><a href="{{ route('suppliers.index') }}">الموردون</a> &laquo; إضافة</p>
    </div>
    @include('procurement.partials.flash')
    <form action="{{ route('suppliers.store') }}" method="POST">@csrf
        <div class="card">
            <div class="card-body">@include('procurement.suppliers.form')</div>
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1rem"><button class="btn btn-gold" type="submit">حفظ المورد</button><a
                class="btn btn-ghost" href="{{ route('suppliers.index') }}">إلغاء</a></div>
    </form>
@endsection
