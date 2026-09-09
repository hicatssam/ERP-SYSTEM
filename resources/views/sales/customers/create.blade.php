@extends('layouts.app')
@section('title', 'إضافة عميل')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">إضافة عميل</h1>
        <p class="page-subheading"><a href="{{ route('customers.index') }}">العملاء</a> &laquo; إضافة</p>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>تعذر حفظ العميل.</strong>
        <ul style="margin:.5rem 0 0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form action="{{ route('customers.store') }}" method="POST">
    @csrf
    @include('sales.customers._form')
    <div style="display:flex;justify-content:flex-end;gap:.75rem;max-width:1050px;margin-top:1.25rem">
        <a href="{{ route('customers.index') }}" class="btn btn-ghost">إلغاء</a>
        <button class="btn btn-gold" type="submit">حفظ العميل</button>
    </div>
</form>
@endsection
