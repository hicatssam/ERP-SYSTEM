@extends('layouts.app')
@section('title', 'تعديل أمر شراء')
@section('content')
    <div class="page-header">
        <h1 class="page-heading">تعديل {{ $purchaseOrder->purchase_order_number }}</h1>
    </div>
    @include('procurement.partials.flash')
    <form method="POST" action="{{ route('purchase-orders.update', $purchaseOrder) }}">@csrf @method('PUT')<div
            class="card">
            <div class="card-body">@include('procurement.purchase-orders.form')</div>
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1rem"><button class="btn btn-gold">حفظ التعديلات</button><a
                class="btn btn-ghost" href="{{ route('purchase-orders.show', $purchaseOrder) }}">إلغاء</a></div>
    </form>
@endsection
