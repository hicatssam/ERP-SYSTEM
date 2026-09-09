@extends('layouts.app')
@section('title', 'إنشاء أمر شراء')
@section('content')
    <div class="page-header">
        <h1 class="page-heading">إنشاء أمر شراء</h1>
        <p class="page-subheading"><a href="{{ route('purchase-orders.index') }}">أوامر الشراء</a> &laquo; جديد</p>
    </div>
    @include('procurement.partials.flash')
    <form method="POST" action="{{ route('purchase-orders.store') }}">@csrf<div class="card">
            <div class="card-body">@include('procurement.purchase-orders.form')</div>
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1rem"><button class="btn btn-gold">حفظ كمسودة</button><a
                class="btn btn-ghost" href="{{ route('purchase-orders.index') }}">إلغاء</a></div>
    </form>
@endsection
