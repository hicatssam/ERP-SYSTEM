@extends('layouts.app')
@section('title', 'تعديل عميل')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">تعديل: {{ $customer->name }}</h1>
        <p class="page-subheading"><a href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a> &laquo; تعديل</p>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>تعذر حفظ التعديلات.</strong>
        <ul style="margin:.5rem 0 0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form action="{{ route('customers.update', $customer) }}" method="POST">
    @csrf
    @method('PUT')
    @include('sales.customers._form')
    <div style="display:flex;justify-content:flex-end;gap:.75rem;max-width:1050px;margin-top:1.25rem">
        <a href="{{ route('customers.show', $customer) }}" class="btn btn-ghost">إلغاء</a>
        <button class="btn btn-gold" type="submit">حفظ التعديلات</button>
    </div>
</form>
@endsection
